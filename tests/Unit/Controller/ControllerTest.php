<?php

namespace MonkeysLegion\Stripe\Tests\Unit\Controller;

use MonkeysLegion\Stripe\Tests\TestCase;
use MonkeysLegion\Stripe\Controller\Controller;

class ControllerTest extends TestCase
{
    private Controller $controller;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip tests if pcntl not available
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('PCNTL extension not available, skipping timeout tests');
        }

        $this->controller = new Controller();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Kill stray child processes if pcntl is used
        if (function_exists('pcntl_wait')) {
            while (pcntl_wait($status, WNOHANG) > 0) {
            }
        }
    }

    public function testExecuteWithTimeoutCompletesSuccessfully(): void
    {
        $result = $this->controller->executeWithTimeout(function () {
            return 'success';
        }, 2);

        $this->assertEquals('success', $result);
    }

    public function testExecuteWithTimeoutThrowsExceptionOnTimeout(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Timeout reached while processing');

        $this->controller->executeWithTimeout(function () {
            sleep(3); // Sleep longer than timeout
            return 'should never return';
        }, 1);
    }

    public function testExecuteWithTimeoutHandlesExceptions(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Test exception');

        $this->controller->executeWithTimeout(function () {
            throw new \InvalidArgumentException('Test exception');
        }, 2);
    }

    public function testExecuteWithTimeoutHandlesEdgeCaseZeroTimeout(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Timeout must be > 0');

        $this->controller->executeWithTimeout(function () {
            sleep(1);
            return 'should never return';
        }, 0);
    }
}
