<?php

namespace MonkeysLegion\Stripe\Tests\Unit\Webhook;

use MonkeysLegion\Stripe\Tests\TestCase;
use MonkeysLegion\Stripe\Webhook\WebhookController;
use MonkeysLegion\Stripe\Middleware\WebhookMiddleware;
use MonkeysLegion\Stripe\Storage\MemoryIdempotencyStore;
use MonkeysLegion\Stripe\Service\ServiceContainer;
use MonkeysLegion\Stripe\Enum\Stages;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Exception\RateLimitException;
use PHPUnit\Framework\MockObject\MockObject;

class WebhookControllerTest extends TestCase
{
    private WebhookController $webhookController;
    /** @var WebhookMiddleware&MockObject */
    private WebhookMiddleware $webhookMiddleware;
    /** @var MemoryIdempotencyStore&MockObject */
    private MemoryIdempotencyStore $idempotencyStore;
    /** @var ServiceContainer&MockObject */
    private ServiceContainer $container;
    private $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->webhookMiddleware = $this->createMock(WebhookMiddleware::class);
        $this->webhookMiddleware->method('getStage')
            ->willReturn(Stages::TESTING);
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

        parent::tearDown();
    }

    public function testHandleValidPayload(): void
    {
        $payload = $this->getTestWebhookPayload();
        $sigHeader = $this->getTestSignatureHeader($payload, $this->webhookMiddleware->getEndPointSecret());
        $eventData = json_decode($payload, true);

        $this->webhookMiddleware->method('verifyAndProcess')
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
        $this->expectExceptionMessage('WebhookController: Payload is not valid JSON.');

        $this->webhookController->handle('not-json', 'test_signature', function () {
            return 'should not be called';
        });
    }

    public function testHandleEmptyPayload(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('WebhookController: Payload is empty.');

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

        $this->webhookMiddleware->method('verifyAndProcess')
            ->with($payload, $sigHeader)
            ->willThrowException(new SignatureVerificationException('Invalid signature', 401));

        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage('Invalid signature');

        $this->webhookController->handle($payload, $sigHeader, function () {
            return 'should not be called';
        });
    }

    public function testHandleWithRetriableError(): void
    {
        // Set retries to 1 retry (total 2 attempts)
        $reflection = new \ReflectionProperty($this->webhookController, 'retries');
        $reflection->setAccessible(true);
        $reflection->setValue($this->webhookController, 1);

        // Ensure prod_mode is true for this test
        $this->webhookMiddleware->setStageMode(Stages::PROD);
        $this->webhookController->setStage(Stages::PROD);

        // Create a fake Stripe event
        $event = $this->createMock(\Stripe\Event::class);
        $event->id = 'evt_test';
        $event->type = 'charge.succeeded';

        $payload = $this->getTestWebhookPayload();
        $sigHeader = $this->getTestSignatureHeader(
            $payload,
            $this->webhookMiddleware->getEndPointSecret()
        );

        // Mock the middleware: fail once, then succeed
        $this->webhookMiddleware->method('verifyAndProcess')
            ->willReturnCallback(function () use ($event) {
                static $count = 0;
                $count++;
                if ($count === 1) {
                    throw new RateLimitException('Rate limited', 429);
                }
                return $event;
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Max retries reached: Rate limited');

        $callbackCalled = false;

        // Call the handle method
        $result = $this->webhookController->handle(
            $payload,
            $sigHeader,
            function () use (&$callbackCalled) {
                $callbackCalled = true;
                return 'success';
            }
        );

        // Assertions
        $this->assertTrue($callbackCalled, 'Callback should have been called.');
        $this->assertEquals('success', $result, 'Handle should return the callback result.');
    }

    public function testHandleWithMaxRetriesExceeded(): void
    {
        $controller = $this->getMockBuilder(WebhookController::class)
            ->setConstructorArgs([$this->webhookMiddleware, $this->idempotencyStore, $this->container, $this->logger])
            ->onlyMethods(['payload_check'])
            ->getMock();

        $controller->method('payload_check')->willReturn(true);

        // Ensure test mode to disable backoff completely
        $controller->setStage(Stages::TESTING);

        // Configure middleware to always fail with rate limit error
        $this->webhookMiddleware->method('verifyAndProcess')
            ->willThrowException(new RateLimitException('Rate limited', 429));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Max retries reached: Rate limited');

        $controller->handle('{}', 'invalid_sig', function () {
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

    public function testHandleWithRetriableErrorInProdStage(): void
    {
        // Set to production stage to enable retries
        $this->webhookController->setStage(Stages::PROD);

        // Create a fake Stripe event
        $event = $this->createMock(\Stripe\Event::class);
        $event->id = 'evt_test';
        $event->type = 'charge.succeeded';

        $payload = $this->getTestWebhookPayload();
        $sigHeader = $this->getTestSignatureHeader(
            $payload,
            $this->webhookMiddleware->getEndPointSecret()
        );

        // Mock the middleware: fail once, then succeed
        $this->webhookMiddleware->method('verifyAndProcess')
            ->willReturnCallback(function () use ($event) {
                static $count = 0;
                $count++;
                if ($count === 1) {
                    throw new RateLimitException('Rate limited', 429);
                }
                return $event;
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Max retries reached: Rate limited');

        $callbackCalled = false;

        // Call the handle method
        $result = $this->webhookController->handle(
            $payload,
            $sigHeader,
            function () use (&$callbackCalled) {
                $callbackCalled = true;
                return 'success';
            }
        );

        // Assertions
        $this->assertTrue($callbackCalled, 'Callback should have been called.');
        $this->assertEquals('success', $result, 'Handle should return the callback result.');
    }

    public function testHandleWithRetriableErrorInTestStage(): void
    {
        // Set to test stage to enable retries with no backoff (instant retry)
        $this->webhookController->setStage(Stages::TEST);

        // Create a fake Stripe event
        $event = $this->createMock(\Stripe\Event::class);
        $event->id = 'evt_test';
        $event->type = 'charge.succeeded';

        $payload = $this->getTestWebhookPayload();
        $sigHeader = $this->getTestSignatureHeader(
            $payload,
            $this->webhookMiddleware->getEndPointSecret()
        );

        // Mock the middleware: fail once, then succeed (instant retry - no backoff)
        $this->webhookMiddleware->method('verifyAndProcess')
            ->willReturnCallback(function () use ($event) {
                static $count = 0;
                $count++;
                if ($count === 1) {
                    throw new RateLimitException('Rate limited', 429);
                }
                return $event;
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Max retries reached: Rate limited');

        $callbackCalled = false;

        // Call the handle method
        $result = $this->webhookController->handle(
            $payload,
            $sigHeader,
            function () use (&$callbackCalled) {
                $callbackCalled = true;
                return 'success';
            }
        );

        // Assertions
        $this->assertTrue($callbackCalled, 'Callback should have been called.');
        $this->assertEquals('success', $result, 'Handle should return the callback result.');
    }

    public function testHandleWithRetriableErrorInDevStage(): void
    {
        // Set to dev stage to disable retries and backoff completely
        $this->webhookController->setStage(Stages::DEV);

        $payload = $this->getTestWebhookPayload();
        $sigHeader = $this->getTestSignatureHeader(
            $payload,
            $this->webhookMiddleware->getEndPointSecret()
        );

        // Mock the middleware to always fail with rate limit error
        $this->webhookMiddleware->method('verifyAndProcess')
            ->willThrowException(new RateLimitException('Rate limited', 429));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stage dev does not allow retries');

        $this->webhookController->handle($payload, $sigHeader, function () {
            return 'should not be called';
        });
    }

    private function switchToProd(): void
    {
        $this->webhookMiddleware->setStageMode(Stages::PROD);
        $this->webhookController->setStage(Stages::PROD);
    }
}
