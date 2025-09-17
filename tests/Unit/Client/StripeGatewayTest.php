<?php

namespace MonkeysLegion\Stripe\Tests\Unit\Client;

use MonkeysLegion\Stripe\Tests\TestCase;
use MonkeysLegion\Stripe\Client\StripeGateway;
use PHPUnit\Framework\MockObject\MockObject;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Collection;
use Stripe\SearchResult;
use Stripe\Exception\CardException;
use Stripe\Exception\ApiErrorException;
use Stripe\Service\PaymentIntentService;
use Stripe\StripeClient;

class StripeGatewayTest extends TestCase
{
    private StripeGateway $gateway;
    /** @var \Stripe\StripeClient|MockObject */
    private $stripeMock;
    /** @var \Stripe\Service\PaymentIntentService|MockObject */
    private $paymentIntentServiceMock;
    /** @var \Stripe\Service\RefundService|MockObject */
    private $refundServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $loggerMock = $this->createMock(\MonkeysLegion\Core\Contracts\FrameworkLoggerInterface::class);

        $this->paymentIntentServiceMock = $this->getMockBuilder(\Stripe\Service\PaymentIntentService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create', 'retrieve', 'confirm', 'cancel', 'capture', 'search'])
            ->getMock();

        $this->refundServiceMock = $this->getMockBuilder(\Stripe\Service\RefundService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();

        $this->stripeMock = $this->getMockBuilder(StripeClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__get'])
            ->getMock();

        $this->stripeMock->method('__get')
            ->willReturnMap([
                ['paymentIntents', $this->paymentIntentServiceMock],
                ['refunds', $this->refundServiceMock],
            ]);

        $this->gateway = $this->getMockBuilder(StripeGateway::class)
            ->setConstructorArgs([[1 => $this->stripeMock], true, $loggerMock])
            ->onlyMethods(['getStripeClient'])
            ->getMock();

        $this->gateway->method('getStripeClient')
            ->willReturn($this->stripeMock);
    }

    public function testCreatePaymentIntent(): void
    {
        // Create mock payment intent
        $mockPaymentIntent = $this->createMock(PaymentIntent::class);

        // Configure payment intent service mock
        $this->paymentIntentServiceMock->expects($this->once())
            ->method('create')
            ->with([
                'amount' => 2000,
                'currency' => 'usd',
                'automatic_payment_methods' => ['enabled' => true],
            ])
            ->willReturn($mockPaymentIntent);

        // Call method
        $result = $this->gateway->createPaymentIntent(2000);

        // Verify result
        $this->assertSame($mockPaymentIntent, $result);
    }

    public function testRetrievePaymentIntent(): void
    {
        // Create mock payment intent
        $mockPaymentIntent = $this->createMock(PaymentIntent::class);
        $piId = 'pi_test123';

        // Configure payment intent service mock
        $this->paymentIntentServiceMock->expects($this->once())
            ->method('retrieve')
            ->with($piId)
            ->willReturn($mockPaymentIntent);

        // Call method
        $result = $this->gateway->retrievePaymentIntent($piId);

        // Verify result
        $this->assertSame($mockPaymentIntent, $result);
    }

    public function testConfirmPaymentIntent(): void
    {
        // Create mock payment intent
        $mockPaymentIntent = $this->createMock(PaymentIntent::class);
        $piId = 'pi_test123';
        $options = ['payment_method' => 'pm_card_visa'];

        // Configure payment intent service mock
        $this->paymentIntentServiceMock->expects($this->once())
            ->method('confirm')
            ->with($piId, $options)
            ->willReturn($mockPaymentIntent);

        // Call method
        $result = $this->gateway->confirmPaymentIntent($piId, $options);

        // Verify result
        $this->assertSame($mockPaymentIntent, $result);
    }

    public function testRefundPaymentIntent(): void
    {
        // Create mock refund
        $mockRefund = $this->createMock(Refund::class);
        $piId = 'pi_test123';

        // Configure refund service mock
        $this->refundServiceMock->expects($this->once())
            ->method('create')
            ->with(['payment_intent' => $piId])
            ->willReturn($mockRefund);

        // Call method
        $result = $this->gateway->refundPaymentIntent($piId);

        // Verify result
        $this->assertSame($mockRefund, $result);
    }

    public function testSearchPaymentIntent(): void
    {
        // Create mock search result
        $mockSearchResult = $this->createMock(SearchResult::class);
        $query = 'status:"succeeded" AND metadata["order_id"]:"123"';

        // Configure payment intent service mock
        $this->paymentIntentServiceMock->expects($this->once())
            ->method('search')
            ->with(['query' => $query])
            ->willReturn($mockSearchResult);

        // Call method
        $result = $this->gateway->searchPaymentIntent(['query' => $query]);

        // Verify result
        $this->assertSame($mockSearchResult, $result);
    }

    public function testSearchPaymentIntentWithoutQuery(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "query" parameter is required');

        $this->gateway->searchPaymentIntent([]);
    }

    public function testIsValidPaymentIntent(): void
    {
        // Create mock PaymentIntent service
        $paymentIntentServiceMock = $this->createMock(PaymentIntentService::class);

        // Mock retrieve() to return a PaymentIntent with status 'succeeded'
        $mockPaymentIntent = new \Stripe\PaymentIntent();
        $mockPaymentIntent->status = 'succeeded';

        $paymentIntentServiceMock = $this->createMock(\Stripe\Service\PaymentIntentService::class);
        $paymentIntentServiceMock->expects($this->once())
            ->method('retrieve')
            ->with('pi_test123')
            ->willReturn($mockPaymentIntent);

        // Mock StripeClient to return the service
        $stripeMock = $this->getMockBuilder(\Stripe\StripeClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__get'])
            ->getMock();

        $stripeMock->method('__get')
            ->willReturnMap([
                ['paymentIntents', $paymentIntentServiceMock],
            ]);


        // Inject into gateway
        $reflection = new \ReflectionProperty($this->gateway, 'stripe');
        $reflection->setAccessible(true);
        $reflection->setValue($this->gateway, $stripeMock);

        // Run test
        $result = $this->gateway->isValidPaymentIntent('pi_test123');
        $this->assertTrue($result);
    }

    public function testHandleErrorsFromStripe(): void
    {
        $piId = 'pi_test123';

        $this->paymentIntentServiceMock->expects($this->once())
            ->method('retrieve')
            ->with($piId)
            ->willThrowException(new \Stripe\Exception\CardException('Card declined', 402));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stripe error: Card declined');

        try {
            $this->gateway->retrievePaymentIntent($piId);
        } catch (\RuntimeException $e) {
            // You can also assert the previous exception is CardException
            $this->assertInstanceOf(\Stripe\Exception\CardException::class, $e->getPrevious());
            throw $e; // rethrow so PHPUnit sees it
        }
    }
}
