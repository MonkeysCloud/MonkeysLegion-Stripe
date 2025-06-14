<?php

namespace MonkeysLegion\Stripe\Webhook;

use MonkeysLegion\Stripe\Middleware\WebhookMiddleware;
use MonkeysLegion\Stripe\Storage\MemoryIdempotencyStore;
use Stripe\Exception\CardException;
use Stripe\Exception\SignatureVerificationException;

class WebhookController
{
    protected WebhookMiddleware $webhookMiddleware;
    private MemoryIdempotencyStore $idempotencyStore;

    public function __construct(WebhookMiddleware $webhookMiddleware, MemoryIdempotencyStore $idempotencyStore)
    {
        $this->webhookMiddleware = $webhookMiddleware;
        $this->idempotencyStore = $idempotencyStore;
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
        try {
            // Get verified event data from middleware
            $eventData = $this->webhookMiddleware->verifyAndProcess($payload, $sigHeader);
            error_log("WebhookController: Event data verified successfully.");
            // Pass event data to callback
            return $callback($eventData);
        } catch (SignatureVerificationException $e) {
            error_log("WebhookController: Signature verification failed: " . $e->getMessage());
            throw $e;
        } catch (\InvalidArgumentException $e) {
            error_log("WebhookController: Invalid argument: " . $e->getMessage());
            throw $e;
        } catch (CardException $e) {
            error_log("A payment error occurred: {$e->getError()->message}");
            throw new \RuntimeException('Card error occurred: ' . $e->getMessage(), $e->getCode(), $e);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            error_log("An invalid request occurred.");
            throw new \RuntimeException('Invalid request: ' . $e->getMessage(), $e->getCode(), $e);
        } catch (\Exception $e) {
            error_log("WebhookController: An error occurred while processing the webhook: " . $e->getMessage());
            throw new \RuntimeException('An error occurred while processing the webhook: ' . $e->getMessage(), $e->getCode(), $e);
        }
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
