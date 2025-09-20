<?php

namespace MonkeysLegion\Stripe\Middleware;

use MonkeysLegion\Stripe\Enum\Stages;
use MonkeysLegion\Stripe\Exceptions\EventAlreadyProcessedException;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;
use MonkeysLegion\Stripe\Storage\MemoryIdempotencyStore;

class WebhookMiddleware
{
    private string $endpointSecret;
    private Stages $stage;
    private const TEST_SECRET_KEY = 'webhook_secret_test';
    private const LIVE_SECRET_KEY = 'webhook_secret';

    /**
     * WebhookMiddleware constructor.
     *
     * @param array<string, string> $endpointSecrets
     * @param int $tolerance
     * @param MemoryIdempotencyStore $idempotencyStore
     * @param int|null $defaultTtl
     * @param Stages|null $stage
     */
    public function __construct(
        private array $endpointSecrets,
        private int $tolerance,
        private MemoryIdempotencyStore $idempotencyStore,
        private ?int $defaultTtl = 172800, // 48 hours default
        ?Stages $stage = null
    ) {
        $this->setStageMode($stage);
    }

    /**
     * Set the stage mode for the webhook middleware.
     *
     * @param Stages $stage
     */
    public function setStageMode(?Stages $stage): void
    {
        if ($stage === null) {
            $envStage = $_ENV['APP_ENV'] ?? 'dev';
            $this->stage = Stages::tryFrom($envStage) ?? Stages::DEV;
        } else {
            $this->stage = $stage;
        }

        // Dev and Test stages use test secrets (same behavior as prod mode for secrets)
        // Prod stage uses live secrets
        $key_name = ($this->stage === Stages::PROD || $this->stage === Stages::PRODUCTION)
            ? self::LIVE_SECRET_KEY
            : self::TEST_SECRET_KEY;

        if (!isset($this->endpointSecrets[$key_name])) {
            throw new \RuntimeException("{$key_name} for stage {$this->stage->value} is missing.");
        }

        $this->endpointSecret = $this->endpointSecrets[$key_name];
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
     * Check if current stage is production
     *
     * @return bool
     */
    public function isProductionStage(): bool
    {
        return in_array($this->stage, [Stages::PROD, Stages::PRODUCTION], true);
    }

    /**
     * Verify webhook signature and handle idempotency
     *
     * @param string $payload Raw request body
     * @param string $sigHeader Stripe-Signature header
     * @return array<string, mixed> Verified webhook event data
     * @throws SignatureVerificationException
     * @throws \InvalidArgumentException
     */
    public function verifyAndProcess(string $payload, string $sigHeader): array
    {
        // First verify signature - this will throw if verification fails
        $event = $this->verifySignature($payload, $sigHeader);

        // Only check idempotency for successfully verified events
        if ($this->idempotencyStore->isProcessed($event['id'])) {
            throw new EventAlreadyProcessedException($event['id']);
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
     * @return array<string, mixed> Webhook event data
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

            /** @var array<string, mixed> $eventArr */
            $eventArr = $event->toArray();
            return $eventArr;
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

    public function getEndPointSecret(): string
    {
        return $this->endpointSecret;
    }
}
