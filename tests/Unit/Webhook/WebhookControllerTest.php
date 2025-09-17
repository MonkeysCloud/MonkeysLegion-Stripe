<?php

namespace MonkeysLegion\Stripe\Tests\Unit\Webhook;

use MonkeysLegion\Stripe\Tests\TestCase;
use MonkeysLegion\Stripe\Webhook\WebhookController;
use MonkeysLegion\Stripe\Middleware\WebhookMiddleware;
use MonkeysLegion\Stripe\Storage\MemoryIdempotencyStore;
use MonkeysLegion\Stripe\Service\ServiceContainer;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Exception\RateLimitException;
use PHPUnit\Framework\MockObject\Stub\ConsecutiveCalls;

class WebhookControllerTest extends TestCase
{
    private WebhookController $webhookController;
    private WebhookMiddleware $webhookMiddleware;
    private MemoryIdempotencyStore $idempotencyStore;
    private ServiceContainer $container;
    private $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->webhookMiddleware = $this->createMock(WebhookMiddleware::class);
        $this->idempotencyStore = $this->createMock(MemoryIdempotencyStore::class);
        $this->container = $this->createMock(ServiceContainer::class);
        $this->container->method('getConfig')
            ->willReturnCallback(function ($name) {
                if ($name === 'stripe') {
                    return [
                        'timeout' => 5,
                        'webhook_retries' => 2,
                        'backoff' => 1,
                        'max_payload_size' => 131072,
                    ];
                }
                return []; // always return array
            });
        $this->logger = $this->createMockLogger();

        $this->webhookController = new WebhookController(
            $this->webhookMiddleware,
            $this->idempotencyStore,
            $this->container,
            $this->logger
        );

        // Set production mode for retries
        $_ENV['APP_ENV'] = 'production';

        // Ensure production mode is set in the controller using reflection
        $reflection = new \ReflectionProperty($this->webhookController, 'prod_mode');
        $reflection->setAccessible(true);
        $reflection->setValue($this->webhookController, true);
    }

    /**
     * Clear rate limit and other state after each test
     */
    protected function tearDown(): void
    {
        // Reset backoff time to initial value
        $reflection = new \ReflectionProperty($this->webhookController, 'backoff');
        $reflection->setAccessible(true);
        $reflection->setValue($this->webhookController, 60); // Reset to default value

        // Clear the processed events to avoid interference between tests
        $this->webhookController->clearProcessedEvents();

        // Reset environment mode
        $_ENV['APP_ENV'] = 'testing';

        // Reset production mode
        $reflection = new \ReflectionProperty($this->webhookController, 'prod_mode');
        $reflection->setAccessible(true);
        $reflection->setValue($this->webhookController, false);

        parent::tearDown();
    }

    public function testHandleValidPayload(): void
    {
        $payload = $this->getTestWebhookPayload();
        $sigHeader = 'test_signature';
        $eventData = json_decode($payload, true);

        $this->webhookMiddleware->expects($this->once())
            ->method('verifyAndProcess')
            ->with($payload, $sigHeader)
            ->willReturn($eventData);

        $callbackCalled = false;
        $result = $this->webhookController->handle($payload, $sigHeader, function ($data) use (&$callbackCalled, $eventData) {
            $callbackCalled = true;
            $this->assertEquals($eventData, $data);
            return 'success';
        });

        $this->assertTrue($callbackCalled);
        $this->assertEquals('success', $result);
    }

    public function testHandleInvalidPayload(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid payload type received');

        $this->webhookController->handle('not-json', 'test_signature', function () {
            return 'should not be called';
        });
    }

    public function testHandleEmptyPayload(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Empty payload received');

        $this->webhookController->handle('', 'test_signature', function () {
            return 'should not be called';
        });
    }

    public function testHandlePayloadTooLarge(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Payload exceeds maximum size');

        // Generate a payload larger than allowed
        $largePayload = json_encode([
            'data' => str_repeat('a', 129 * 1024)
        ]);

        $this->webhookController->handle($largePayload, 'test_signature', function () {
            return 'should not be called';
        });
    }

    public function testHandleWithSignatureVerificationFailure(): void
    {
        $payload = $this->getTestWebhookPayload();
        $sigHeader = 'invalid_signature';

        $this->webhookMiddleware->expects($this->once())
            ->method('verifyAndProcess')
            ->with($payload, $sigHeader)
            ->willThrowException(new SignatureVerificationException('Invalid signature', 401));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid signature');

        $this->webhookController->handle($payload, $sigHeader, function () {
            return 'should not be called';
        });
    }

    public function testHandleWithRetriableError(): void
    {
        $payload = $this->getTestWebhookPayload();
        $sigHeader = 'test_signature';

        // Ensure prod_mode is true for this specific test
        $reflection = new \ReflectionProperty($this->webhookController, 'prod_mode');
        $reflection->setAccessible(true);
        $reflection->setValue($this->webhookController, true);

        // Configure middleware to fail twice with rate limit error, then succeed
        $this->webhookMiddleware->expects($this->exactly(2))
            ->method('verifyAndProcess')
            ->with($payload, $sigHeader)
            ->will(new ConsecutiveCalls(
                [
                    $this->throwException(new RateLimitException('Rate limited', 429)),
                    // $this->throwException(new RateLimitException('Rate limited', 429)),
                    json_decode($payload, true) // must be actual array, not closure
                ]
            ));

        $callbackCalled = false;
        $result = $this->webhookController->handle($payload, $sigHeader, function () use (&$callbackCalled) {
            $callbackCalled = true;
            return 'success';
        });

        $this->assertTrue($callbackCalled);
        $this->assertEquals('success', $result);
    }

    public function testHandleWithMaxRetriesExceeded(): void
    {
        $payload = $this->getTestWebhookPayload();
        $sigHeader = 'test_signature';

        // Ensure prod_mode is true for this specific test
        $reflection = new \ReflectionProperty($this->webhookController, 'prod_mode');
        $reflection->setAccessible(true);
        $reflection->setValue($this->webhookController, true);

        // Configure middleware to always fail with rate limit error
        $this->webhookMiddleware->expects($this->exactly(2))
            ->method('verifyAndProcess')
            ->willThrowException(new RateLimitException('Rate limited', 429));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Max retries reached');

        $this->webhookController->handle($payload, $sigHeader, function () {
            return 'should not be called';
        });
    }

    public function testIsEventProcessed(): void
    {
        $eventId = 'evt_test123';

        $this->idempotencyStore->expects($this->once())
            ->method('isProcessed')
            ->with($eventId)
            ->willReturn(true);

        $this->assertTrue($this->webhookController->isEventProcessed($eventId));
    }

    public function testRemoveProcessedEvent(): void
    {
        $eventId = 'evt_test123';

        $this->idempotencyStore->expects($this->once())
            ->method('removeEvent')
            ->with($eventId);

        $this->webhookController->removeProcessedEvent($eventId);
    }
}
