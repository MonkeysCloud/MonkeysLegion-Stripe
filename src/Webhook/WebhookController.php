<?php

namespace MonkeysLegion\Stripe\Webhook;

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
    private int $timeout = 60; // Default timeout for webhook processing
    private int $retries = 3; // Default number of retries for webhook processing
    private int $backoff = 60; // Initial backoff time in seconds
    private bool $prod_mode = false; // Flag to indicate production mode

    /**
     * WebhookController constructor.
     *
     * @param WebhookMiddleware $webhookMiddleware Middleware for handling webhook verification
     * @param MemoryIdempotencyStore $idempotencyStore Store for idempotency checks
     * @param ServiceContainer $c Service container for dependency injection
     */
    public function __construct(WebhookMiddleware $webhookMiddleware, MemoryIdempotencyStore $idempotencyStore, ServiceContainer $c)
    {
        $this->webhookMiddleware = $webhookMiddleware;
        $this->idempotencyStore = $idempotencyStore;

        $config = $c->getConfig('stripe') ?? [];
        $this->timeout = $config['timeout'] ?? $this->timeout;
        $this->retries = $config['webhook_retries'] ?? $this->retries;
        $this->backoff = $config['backoff'] ?? $this->backoff;

        $this->prod_mode = isset($_ENV['APP_ENV']) && strpos($_ENV['APP_ENV'], 'prod') !== false;
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
        $retryCount = 0;

        while ($retryCount < $this->retries) {
            try {
                if (!function_exists('pcntl_fork')) error_log("pcntl not available, skipping timeout logic.");
                if (function_exists('pcntl_fork') && $this->prod_mode) {
                    // Use a timeout to enforce processing limits
                    $result = $this->executeWithTimeout(function () use ($payload, $sigHeader) {
                        return $this->webhookMiddleware->verifyAndProcess($payload, $sigHeader);
                    }, $this->timeout);
                } else {
                    // Fallback: run without timeout on platforms like Windows
                    $result = $this->webhookMiddleware->verifyAndProcess($payload, $sigHeader);
                }

                error_log("WebhookController: Event data verified successfully.");

                // Pass event data to callback
                return $callback($result);
            } catch (RateLimitException | ApiConnectionException | ApiErrorException $e) {
                // Retry on transient errors or server errors only in production mode
                if ($this->prod_mode && $retryCount < $this->retries - 1) {
                    error_log("WebhookController: Retriable error occurred: " . $e->getMessage());
                    error_log("Retrying in {$this->backoff} seconds...");
                    sleep($this->backoff);
                    $this->backoff += 60; // Increment backoff time
                    $retryCount++;
                } else {
                    // In non-prod mode or max retries reached, throw immediately
                    $errorMsg = $this->prod_mode ? 'Max retries reached: ' : 'Non-production mode, no retries: ';
                    throw new \RuntimeException($errorMsg . $e->getMessage(), $e->getCode(), $e);
                }
            } catch (CardException $e) {
                // No retry for card errors
                error_log("WebhookController: Card error occurred: {$e->getError()->message}");
                throw new \RuntimeException('Card error occurred: ' . $e->getMessage(), $e->getCode(), $e);
            } catch (InvalidRequestException $e) {
                // No retry for invalid requests
                error_log("WebhookController: Invalid request: " . $e->getMessage());
                throw new \RuntimeException('Invalid request: ' . $e->getMessage(), $e->getCode(), $e);
            } catch (AuthenticationException $e) {
                // No retry for authentication errors
                error_log("WebhookController: Authentication failed: " . $e->getMessage());
                throw new \RuntimeException('Authentication failed: ' . $e->getMessage(), $e->getCode(), $e);
            } catch (\Exception $e) {
                // No retry for unexpected errors
                error_log("WebhookController: An unexpected error occurred: " . $e->getMessage());
                throw new \RuntimeException('An unexpected error occurred: ' . $e->getMessage(), $e->getCode(), $e);
            }
        }

        error_log("WebhookController: Maximum retries reached. Processing failed.");
        throw new \RuntimeException('Webhook processing failed after maximum retries.');
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
