<?php

namespace MonkeysLegion\Stripe\Webhook;

use MonkeysLegion\Core\Contracts\FrameworkLoggerInterface;
use MonkeysLegion\Stripe\Controller\Controller;
use MonkeysLegion\Stripe\Enum\Stages;
use MonkeysLegion\Stripe\Exceptions\EventAlreadyProcessedException;
use MonkeysLegion\Stripe\Middleware\WebhookMiddleware;
use MonkeysLegion\Stripe\Service\ServiceContainer;
use MonkeysLegion\Stripe\Storage\MemoryIdempotencyStore;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\RateLimitException;
use Stripe\Exception\SignatureVerificationException;

class WebhookController extends Controller
{
    private int $timeout = 60; // Default timeout for webhook processing
    private int $retries = 3; // Default number of retries for webhook processing
    private int $backoff = 60; // Initial backoff time in seconds
    private Stages $stage;
    private int $maxPayloadSize = 128 * 1024;

    /**
     * WebhookController constructor.
     *
     * @param WebhookMiddleware $webhookMiddleware Middleware for handling webhook verification
     * @param MemoryIdempotencyStore $idempotencyStore Store for idempotency checks
     * @param ServiceContainer $c Service container for dependency injection
     */
    public function __construct(
        private WebhookMiddleware $webhookMiddleware,
        private MemoryIdempotencyStore $idempotencyStore,
        ServiceContainer $c,
        private ?FrameworkLoggerInterface $logger = null
    ) {
        // Set the app stage
        $this->stage = $this->webhookMiddleware->getStage();

        // Set configurations from service container
        $config = $c->getConfig('stripe');
        $this->timeout = is_numeric($config['timeout']) ? (int)$config['timeout'] : $this->timeout;
        $this->retries = is_numeric($config['webhook_retries']) ? (int)$config['webhook_retries'] : $this->retries;
        $this->backoff = isset($config['backoff']) && is_numeric($config['backoff']) ? (int)$config['backoff'] : $this->backoff;
        $this->maxPayloadSize = is_numeric($config['max_payload_size']) ? (int)$config['max_payload_size'] : $this->maxPayloadSize;
    }

    /**
     * Set the stage for this controller
     *
     * @param Stages $stage
     */
    public function setStage(Stages $stage): void
    {
        $this->stage = $stage;
    }

    /**
     * Get current stage
     *
     * @return Stages
     */
    public function getStage(): Stages
    {
        return $this->stage;
    }

    public function setIdempotencyStore(MemoryIdempotencyStore $store): void
    {
        $this->idempotencyStore = $store;
    }

    public function getIdempotencyStore(): MemoryIdempotencyStore
    {
        return $this->idempotencyStore;
    }

    /**
     * Check if retries should be enabled for current stage
     * Dev stage: no retries, Test stage: yes retries (instant), Prod stage: yes retries (with backoff)
     *
     * @return bool
     */
    private function shouldRetry(): bool
    {
        return in_array($this->stage, [Stages::TEST, Stages::TESTING, Stages::PROD, Stages::PRODUCTION], true);
    }

    /**
     * Check if backoff should be used for retries
     * Dev stage: no backoff (no retries), Test stage: no backoff (instant retry), Prod stage: yes backoff
     *
     * @return bool
     */
    private function shouldUseBackoff(): bool
    {
        return in_array($this->stage, [Stages::PROD, Stages::PRODUCTION], true);
    }

    /**
     * Handle incoming webhook payload and signature header
     *
     * @param string $payload Raw request body
     * @param string $sigHeader Stripe-Signature header
     * @param callable $callback Callback function to process the event data
     * @return mixed Result of the callback function
     * @throws SignatureVerificationException If signature verification fails
     * @throws \InvalidArgumentException If event is already processed or invalid
     * @throws \RuntimeException If an error occurs during processing
     */
    public function handle(string $payload, string $sigHeader, callable $callback): mixed
    {
        $this->payload_check($payload);
        $this->logger?->smartLog("WebhookController: Received payload for processing.", [
            'payload_length' => strlen($payload),
            'sig_header' => $sigHeader,
            'stage' => $this->stage->value
        ]);

        $retryCount = 0;
        $maxRetries = $this->shouldRetry() ? $this->retries : 0;

        while ($retryCount <= $maxRetries) {
            try {
                // Use timeout if pcntl is available, regardless of stage
                if (function_exists('pcntl_fork')) {
                    $this->logger?->smartLog("WebhookController: Using timeout for stage {$this->stage->value}");
                    $result = $this->executeWithTimeout(function () use ($payload, $sigHeader) {
                        return $this->webhookMiddleware->verifyAndProcess($payload, $sigHeader);
                    }, $this->timeout);
                } else {
                    $this->logger?->smartLog("WebhookController: No timeout (pcntl not available) for stage {$this->stage->value}");
                    $result = $this->webhookMiddleware->verifyAndProcess($payload, $sigHeader);
                }

                $this->logger?->smartLog("WebhookController: Event data verified successfully.");

                // Pass event data to callback
                return $callback($result);
            } catch (SignatureVerificationException $e) {
                throw $e; // Let this bubble up directly
            } catch (EventAlreadyProcessedException $e) {
                throw $e; // Let this bubble up directly
            } catch (RateLimitException | ApiConnectionException | ApiErrorException $e) {
                // Only retry if retries are enabled for this stage
                if ($this->shouldRetry() && $retryCount < $maxRetries) {
                    $this->logger?->error("WebhookController: Retriable error occurred", [
                        'error' => $e->getMessage(),
                        'retry_count' => $retryCount,
                        'backoff_time' => $this->shouldUseBackoff() ? $this->backoff : 0,
                        'stage' => $this->stage->value
                    ]);

                    if ($this->shouldUseBackoff()) {
                        $this->logger?->smartLog("Retrying in {$this->backoff} seconds...");
                        sleep($this->backoff);
                        $this->backoff += 60; // Increment backoff time
                    } else {
                        $this->logger?->smartLog("Retrying instantly (no backoff for stage {$this->stage->value})...");
                        // No sleep for test stage - instant retry
                    }

                    $retryCount++;
                } else {
                    // No retries for this stage or max retries reached
                    $errorMsg = $this->shouldRetry()
                        ? 'Max retries reached: '
                        : "Stage {$this->stage->value} does not allow retries: ";
                    $this->logger?->error("WebhookController: " . $errorMsg . $e->getMessage());
                    throw new \RuntimeException($errorMsg . $e->getMessage(), $e->getCode(), $e);
                }
            } catch (\Exception $e) {
                // No retry for unexpected errors
                $this->logger?->error("WebhookController: An unexpected error occurred", [
                    'error' => $e->getMessage(),
                    'stage' => $this->stage->value
                ]);
                throw new \RuntimeException('An unexpected error occurred: ' . $e->getMessage(), $e->getCode(), $e);
            }
        }

        $this->logger?->error("WebhookController: Maximum retries reached. Processing failed.");
        throw new \RuntimeException('Webhook processing failed after maximum retries.');
    }

    public function payload_check(string $payload): bool
    {
        $messages = [];

        if (empty($payload)) $messages[] = 'Payload is empty.';
        if (strlen($payload) > $this->maxPayloadSize) $messages[] = 'Payload exceeds maximum size.';
        if (!json_decode($payload, true)) $messages[] = 'Payload is not valid JSON.';

        if (!empty($messages)) {
            $this->logger?->error("WebhookController: " . $messages[0]);
            throw new \InvalidArgumentException("WebhookController: " . $messages[0]);
        }
        return true;
    }

    /**
     * Check if event has already been processed
     *
     * @param string $eventId Stripe event ID
     * @return bool
     */
    public function isEventProcessed(string $eventId): bool
    {
        return $this->idempotencyStore->isProcessed($eventId);
    }

    /**
     * Remove a specific event from processed list
     *
     * @param string $eventId Stripe event ID
     * @return void
     */
    public function removeProcessedEvent(string $eventId): void
    {
        $this->idempotencyStore->removeEvent($eventId);
    }

    /**
     * Clear processed events cache
     *
     * @return void
     */
    public function clearProcessedEvents(): void
    {
        $this->idempotencyStore->clearAll();
    }

    /**
     * Clean up expired events
     *
     * @return void
     */
    public function cleanupExpiredEvents(): void
    {
        $this->idempotencyStore->cleanupExpired();
    }
}
