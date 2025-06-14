<?php

namespace MonkeysLegion\Stripe\Middleware;

use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;
use MonkeysLegion\Stripe\Storage\MemoryIdempotencyStore;

class WebhookMiddleware
{
    private string $endpointSecret;
    private int $tolerance;
    private MemoryIdempotencyStore $idempotencyStore;
    private ?int $defaultTtl;

    public function __construct(
        string $endpointSecret,
        int $tolerance,
        MemoryIdempotencyStore $idempotencyStore,
        ?int $defaultTtl = 172800 // 48 hours default
    ) {
        $this->endpointSecret = $endpointSecret;
        $this->tolerance = $tolerance;
        $this->idempotencyStore = $idempotencyStore;
        $this->defaultTtl = $defaultTtl;
    }

    /**
     * Verify webhook signature and handle idempotency
     *
     * @param string $payload Raw request body
     * @param string $sigHeader Stripe-Signature header
     * @return array Verified webhook event data
     * @throws SignatureVerificationException
     * @throws \InvalidArgumentException
     */
    public function verifyAndProcess(string $payload, string $sigHeader): array
    {
        // // First verify signature - this will throw if verification fails
        $event = $this->verifySignature($payload, $sigHeader);

        // Only check idempotency for successfully verified events
        if ($this->idempotencyStore->isProcessed($event['id'])) {
            throw new \InvalidArgumentException('Event already processed: ' . $event['id']);
        }

        // // Mark event as processed only after successful verification
        $this->idempotencyStore->markAsProcessed($event['id'], $this->defaultTtl);

        return $event;
    }

    /**
     * Verify webhook signature with tolerance
     *
     * @param string $payload Raw request body
     * @param string $sigHeader Stripe-Signature header
     * @return array Webhook event data
     * @throws SignatureVerificationException
     */
    public function verifySignature(string $payload, string $sigHeader): array
    {
        try {
            $event = Webhook::constructEvent(
                $payload,
                $sigHeader,
                $this->endpointSecret,
                $this->tolerance
            );

            return $event->toArray();
        } catch (SignatureVerificationException $e) {
            throw new SignatureVerificationException(
                'Webhook signature verification failed: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Set default TTL for processed events
     *
     * @param int|null $ttl TTL in seconds, null for no expiration
     * @return void
     */
    public function setDefaultTtl(?int $ttl): void
    {
        $this->defaultTtl = $ttl;
    }

    /**
     * Set tolerance for timestamp verification
     *
     * @param int $tolerance Tolerance in seconds
     * @return void
     */
    public function setTolerance(int $tolerance): void
    {
        $this->tolerance = $tolerance;
    }

    /**
     * Get current tolerance setting
     *
     * @return int
     */
    public function getTolerance(): int
    {
        return $this->tolerance;
    }
}
