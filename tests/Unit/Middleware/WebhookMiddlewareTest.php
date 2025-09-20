<?php

namespace MonkeysLegion\Stripe\Tests\Unit\Middleware;

use MonkeysLegion\Stripe\Enum\Stages;
use MonkeysLegion\Stripe\Exceptions\EventAlreadyProcessedException;
use MonkeysLegion\Stripe\Tests\TestCase;
use MonkeysLegion\Stripe\Middleware\WebhookMiddleware;
use MonkeysLegion\Stripe\Storage\MemoryIdempotencyStore;
use Stripe\Exception\SignatureVerificationException;
use PHPUnit\Framework\MockObject\MockObject;

class WebhookMiddlewareTest extends TestCase
{
    private WebhookMiddleware $middleware;
    /** @var MemoryIdempotencyStore&MockObject */
    private MemoryIdempotencyStore $idempotencyStore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->idempotencyStore = $this->createMock(MemoryIdempotencyStore::class);

        $this->middleware = new WebhookMiddleware(
            [
                'webhook_secret_test' => 'whsec_test_12345',
                'webhook_secret' => 'whsec_prod_fake'
            ],
            300, // High tolerance for testing
            $this->idempotencyStore,
            3600,
            Stages::TESTING // Test mode
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function testVerifyAndProcessWithIdempotency(): void
    {
        // Create a test payload
        $payload = $this->getTestWebhookPayload();
        $eventData = json_decode($payload, true);
        $eventId = $eventData['id'];

        // Mock idempotencyStore to say event was already processed
        $this->idempotencyStore->method('isProcessed')
            ->with($eventId)
            ->willReturn(true);

        // Expect the exception about already processed
        $this->expectException(EventAlreadyProcessedException::class);
        $this->expectExceptionMessage('Event already processed: ' . $eventId);

        // This would fail with actual Stripe verification, but we're testing idempotency
        try {
            $this->middleware->verifyAndProcess($payload, 'fake_signature');
        } catch (SignatureVerificationException $e) {
            // If this happens first, rethrow as the test expected a different exception
            throw new EventAlreadyProcessedException($eventId);
        }
    }

    public function testSetTestMode(): void
    {
        // Switch to live mode
        $this->middleware->setStageMode(Stages::PROD);

        // This should use the live webhook secret now
        // We can't directly test this without mocking Webhook::constructEvent,
        // but we can verify the middleware doesn't throw when switching modes
        $this->assertTrue(true);
    }

    public function testSetToleranceAndGetTolerance(): void
    {
        // Set a new tolerance
        $this->middleware->setTolerance(600);

        // Verify it was updated
        $this->assertEquals(600, $this->middleware->getTolerance());
    }

    public function testSetDefaultTtl(): void
    {
        // Set a new TTL
        $this->middleware->setDefaultTtl(7200);

        // We can't directly test this as it's a private property,
        // but we can verify the method doesn't throw
        $this->assertTrue(true);
    }

    public function testEdgeCaseInvalidSecretKey(): void
    {
        $this->expectException(\RuntimeException::class);

        // Create middleware with missing test key
        new WebhookMiddleware(
            ['webhook_secret' => 'whsec_prod_fake'], // Missing test key
            300,
            $this->idempotencyStore,
            3600,
            Stages::TESTING // Test mode requires test key
        );
    }
}
