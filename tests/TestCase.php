<?php

namespace MonkeysLegion\Stripe\Tests;

use MonkeysLegion\Core\Contracts\FrameworkLoggerInterface;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use MonkeysLegion\Stripe\Service\ServiceContainer;

abstract class TestCase extends PHPUnitTestCase
{
    /** @var ServiceContainer|\PHPUnit\Framework\MockObject\MockObject */
    protected $mockContainer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockContainer = $this->createMockContainer();
    }

    protected function createMockContainer(): ServiceContainer
    {
        $config = [
            'secret_key' => 'sk_test_51HqmkKLknLFzzbcKcgSE',
            'test_key' => 'sk_test_51HqmkKLknLFzzbcKcgSE',
            'publishable_key' => 'pk_test_51HqmkKLknLFzz',
            'webhook_secret' => 'whsec_prod_fake',
            'webhook_secret_test' => 'whsec_test_12345',
            'api_version' => '2025-04-30',
            'currency' => 'usd',
            'currency_limit' => 100000,
            'webhook_tolerance' => 20,
            'webhook_default_ttl' => 172800,
            'timeout' => 5,
            'webhook_retries' => 2,
            'max_payload_size' => 128 * 1024,
            'backoff' => 1,
        ];

        $container = $this->getMockBuilder(ServiceContainer::class)
            ->disableOriginalConstructor()
            ->getMock();

        $container->method('getConfig')
            ->willReturnCallback(fn($key) => $config[$key] ?? null);

        return $container;
    }

    protected function createMockLogger(): FrameworkLoggerInterface
    {
        return $this->getMockBuilder(FrameworkLoggerInterface::class)->getMock();
    }

    protected function getTestWebhookPayload(): string
    {
        return json_encode([
            'id' => 'evt_' . uniqid(),
            'object' => 'event',
            'api_version' => '2025-04-30',
            'created' => time(),
            'data' => [
                'object' => [
                    'id' => 'ch_' . uniqid(),
                    'object' => 'charge',
                    'amount' => 2000,
                    'currency' => 'usd',
                ]
            ],
            'type' => 'charge.succeeded',
            'livemode' => false,
        ], JSON_THROW_ON_ERROR);
    }

    protected function getTestSignatureHeader(string $payload, string $secret): string
    {
        $timestamp = time();
        $signedPayload = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signedPayload, $secret);

        return "t={$timestamp},v1={$signature}";
    }
}
