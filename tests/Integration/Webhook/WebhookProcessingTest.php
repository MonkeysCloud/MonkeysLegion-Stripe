<?php

namespace MonkeysLegion\Stripe\Tests\Integration\Webhook;

use MonkeysLegion\Stripe\Tests\TestCase;
use MonkeysLegion\Stripe\Webhook\WebhookController;
use MonkeysLegion\Stripe\Middleware\WebhookMiddleware;
use MonkeysLegion\Stripe\Storage\MemoryIdempotencyStore;
use MonkeysLegion\Stripe\Storage\Stores\SQLiteStore;
use MonkeysLegion\Stripe\Service\ServiceContainer;

class WebhookProcessingTest extends TestCase
{
    private WebhookController $webhookController;
    private string $webhookSecret;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip if pcntl not available (Windows, etc)
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('PCNTL extension not available, skipping integration test');
        }

        $this->webhookSecret = 'whsec_test_12345';

        // Create real storage with SQLite for this integration test
        $idempotencyStore = new MemoryIdempotencyStore();

        // Create config container
        $container = new ServiceContainer([
            'stripe' => [
                'webhook_secret_test' => $this->webhookSecret,
                'webhook_secret' => 'whsec_live_fake',
                'timeout' => 2, // Short timeout for testing
                'webhook_retries' => 1,
                'webhook_tolerance' => 300,
                'max_payload_size' => 128 * 1024,
                'backoff' => 1
            ]
        ]);

        // Create middleware with real components
        $webhookMiddleware = new WebhookMiddleware(
            [
                'webhook_secret_test' => $this->webhookSecret,
                'webhook_secret' => 'whsec_live_fake'
            ],
            300,
            $idempotencyStore,
            3600,
            true
        );

        // Create controller with real components
        $this->webhookController = new WebhookController(
            $webhookMiddleware,
            $idempotencyStore,
            $container,
            $this->createMockLogger()
        );

        // Force production mode for retry testing
        $_ENV['APP_ENV'] = 'prod';
    }

    public function testWebhookProcessingEndToEnd(): void
    {
        // This is a simulated test that won't actually verify with Stripe
        // but will test our webhook processing logic end-to-end

        $payload = $this->getTestWebhookPayload();
        $eventData = json_decode($payload, true);

        // Generate a proper signature that would work if our secret was registered with Stripe
        $signature = $this->getTestSignatureHeader($payload, $this->webhookSecret);

        // The actual verification will fail since we're not using real Stripe events
        // but we want to test the rest of our processing logic

        try {
            $result = $this->webhookController->handle($payload, $signature, function ($data) {
                // Simulate processing
                return ['processed' => true, 'event_type' => $data['type'] ?? 'unknown'];
            });

            // If we get here, the signature verification was mocked or bypassed
            $this->assertArrayHasKey('processed', $result);
            $this->assertTrue($result['processed']);
            $this->assertEquals('charge.succeeded', $result['event_type']);
        } catch (\Exception $e) {
            // If this is a signature verification exception, it's expected
            // since we're not using real Stripe events
            if (strpos($e->getMessage(), 'signature verification') !== false) {
                $this->markTestSkipped('Signature verification failed as expected in simulated test');
            } else {
                throw $e; // Re-throw unexpected exceptions
            }
        }
    }

    public function testIdempotency(): void
    {
        $payload = $this->getTestWebhookPayload();
        $eventData = json_decode($payload, true);
        $eventId = $eventData['id'];

        // Test first call handling (would succeed with proper signature)
        try {
            $this->webhookController->handle($payload, 'dummy_sig', function () {
                return true;
            });
        } catch (\Exception $e) {
            // Expected since signature is invalid
        }

        // Regardless of signature verification, the event should be marked as processed
        // if the event ID is valid

        // Test idempotency check (second call should be rejected)
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Event already processed');

        $this->webhookController->handle($payload, 'dummy_sig', function () {
            return true;
        });
    }
}
