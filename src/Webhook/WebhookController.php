<?php

namespace MonkeysLegion\Stripe\Webhook;

use MonkeysLegion\Core\Contracts\FrameworkLoggerInterface;
use MonkeysLegion\Stripe\Controller\Controller;
use MonkeysLegion\Stripe\Middleware\WebhookMiddleware;
use MonkeysLegion\Stripe\Service\ServiceContainer;
use MonkeysLegion\Stripe\Storage\MemoryIdempotencyStore;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\CardException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Exception\RateLimitException;
use Stripe\Exception\AuthenticationException;
use Stripe\Exception\SignatureVerificationException;

class WebhookController extends Controller
{
    protected WebhookMiddleware $webhookMiddleware;
    private MemoryIdempotencyStore $idempotencyStore;
    private ?FrameworkLoggerInterface $logger;
    private int $timeout = 60; // Default timeout for webhook processing
    private int $retries = 3; // Default number of retries for webhook processing
    private int $backoff = 60; // Initial backoff time in seconds
    private bool $prod_mode = false; // Flag to indicate production mode
    private int $maxPayloadSize = 128 * 1024;

    /**
     * WebhookController constructor.
     *
     * @param WebhookMiddleware $webhookMiddleware Middleware for handling webhook verification
     * @param MemoryIdempotencyStore $idempotencyStore Store for idempotency checks
     * @param ServiceContainer $c Service container for dependency injection
     */
    public function __construct(WebhookMiddleware $webhookMiddleware, MemoryIdempotencyStore $idempotencyStore, ServiceContainer $c, ?FrameworkLoggerInterface $logger = null)
    {
        $this->webhookMiddleware = $webhookMiddleware;
        $this->idempotencyStore = $idempotencyStore;
        $this->logger = $logger ?? null;
        $config = $c->getConfig('stripe');
        $this->timeout = is_numeric($config['timeout']) ? (int)$config['timeout'] : $this->timeout;
        $this->retries = is_numeric($config['webhook_retries']) ? (int)$config['webhook_retries'] : $this->retries;
        $this->backoff = isset($config['backoff']) && is_numeric($config['backoff']) ? (int)$config['backoff'] : $this->backoff;
        $this->maxPayloadSize = is_numeric($config['max_payload_size']) ? (int)$config['max_payload_size'] : $this->maxPayloadSize;
        $this->prod_mode = isset($_ENV['APP_ENV']) && is_string($_ENV['APP_ENV']) && strpos($_ENV['APP_ENV'], 'prod') !== false;
    }

    public function setLogger(FrameworkLoggerInterface $logger): void
    {
        $this->logger = $logger;
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
            'sig_header' => $sigHeader
        ]);
        $retryCount = 0;
        while ($retryCount < $this->retries) {
            try {
                if (!function_exists('pcntl_fork')) {
                    $this->logger?->notice("pcntl not available, skipping timeout logic.");
                }

                if (function_exists('pcntl_fork') && $this->prod_mode) {
                    // Use a timeout to enforce processing limits
                    $result = $this->executeWithTimeout(function () use ($payload, $sigHeader) {
                        return $this->webhookMiddleware->verifyAndProcess($payload, $sigHeader);
                    }, $this->timeout);
                } else {
                    // Fallback: run without timeout on platforms like Windows
                    $result = $this->webhookMiddleware->verifyAndProcess($payload, $sigHeader);
                }

                $this->logger?->smartLog("WebhookController: Event data verified successfully.");

                // Pass event data to callback
                return $callback($result);
            } catch (RateLimitException | ApiConnectionException | ApiErrorException $e) {
                // Retry on transient errors or server errors only in production mode
                if ($this->prod_mode && $retryCount < $this->retries - 1) {
                    $this->logger?->error("WebhookController: Retriable error occurred", [
                        'error' => $e->getMessage(),
                        'retry_count' => $retryCount,
                        'backoff_time' => $this->backoff
                    ]);
                    $this->logger?->smartLog("Retrying in {$this->backoff} seconds...");
                    sleep($this->backoff);
                    $this->backoff += 60; // Increment backoff time
                    $retryCount++;
                } else {
                    // In non-prod mode or max retries reached, throw immediately
                    $errorMsg = $this->prod_mode ? 'Max retries reached: ' : 'Non-production mode, no retries: ';
                    $this->logger?->error("WebhookController: " . $errorMsg . $e->getMessage());
                    throw new \RuntimeException($errorMsg . $e->getMessage(), $e->getCode(), $e);
                }
            } catch (\Exception $e) {
                // No retry for unexpected errors
                $this->logger?->error("WebhookController : An unexpected error occurred", [
                    'error' => $e->getMessage()
                ]);
                throw new \RuntimeException('An unexpected error occurred: ' . $e->getMessage(), $e->getCode(), $e);
            }
        }

        $this->logger?->error("WebhookController: Maximum retries reached. Processing failed.");
        throw new \RuntimeException('Webhook processing failed after maximum retries.');
    }


    private function payload_check(string $payload): bool
    {
        if (empty($payload)) {
            $this->logger?->error("WebhookController: Empty payload received.");
            throw new \InvalidArgumentException('Empty payload received.');
        }
        if (strlen($payload) > $this->maxPayloadSize) {
            $this->logger?->error("WebhookController: Payload exceeds maximum size.");
            throw new \InvalidArgumentException('Payload exceeds maximum size.');
        }
        if (!json_decode($payload, true)) {
            $this->logger?->error("WebhookController: Invalid payload type received.");
            throw new \InvalidArgumentException('Invalid payload type received.');
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
