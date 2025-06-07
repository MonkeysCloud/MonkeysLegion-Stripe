<?php

namespace MonkeysLegion\Stripe\Client;

use MonkeysLegion\Stripe\Interface\StripeGatewayInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripeGateway implements StripeGatewayInterface
{
    protected StripeClient $stripe;

    public function __construct(StripeClient $stripeClient)
    {
        $this->stripe = $stripeClient;
    }

    public function createPaymentIntent(int $amount, string $currency = 'usd', bool $automatic_payment_methods = true): \Stripe\PaymentIntent
    {
        try {
            return $this->stripe->paymentIntents->create([
                'amount' => $amount,
                'currency' => $currency,
                'automatic_payment_methods' => ['enabled' => $automatic_payment_methods],
            ]);
        } catch (ApiErrorException $e) {
            throw new \Exception('Failed to create PaymentIntent: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function retrievePaymentIntent(string $paymentIntentId, array $options = []): \Stripe\PaymentIntent
    {
        try {
            return $this->stripe->paymentIntents->retrieve($paymentIntentId);
        } catch (ApiErrorException $e) {
            throw new \Exception('Failed to retrieve PaymentIntent: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function confirmPaymentIntent(string $paymentIntentId, array $options = []): \Stripe\PaymentIntent
    {
        try {
            return $this->stripe->paymentIntents->confirm($paymentIntentId, $options);
        } catch (ApiErrorException $e) {
            throw new \Exception('Failed to confirm PaymentIntent: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function cancelPaymentIntent(string $paymentIntentId, array $options = []): \Stripe\PaymentIntent
    {
        try {
            return $this->stripe->paymentIntents->cancel($paymentIntentId);
        } catch (ApiErrorException $e) {
            throw new \Exception('Failed to cancel PaymentIntent: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function capturePaymentIntent(string $paymentIntentId, array $options = []): \Stripe\PaymentIntent
    {
        try {
            return $this->stripe->paymentIntents->capture($paymentIntentId, $options);
        } catch (ApiErrorException $e) {
            throw new \Exception('Failed to capture PaymentIntent: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function refundPaymentIntent(string $paymentIntentId, array $options = []): \Stripe\Refund
    {
        try {
            return $this->stripe->refunds->create([
                'payment_intent' => $paymentIntentId,
                ...$options,
            ]);
        } catch (ApiErrorException $e) {
            throw new \Exception('Failed to refund PaymentIntent: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function listPaymentIntent(array $params = []): \Stripe\Collection
    {
        try {
            return $this->stripe->paymentIntents->all($params);
        } catch (ApiErrorException $e) {
            throw new \Exception('Failed to list PaymentIntents: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function updatePaymentIntent(string $paymentIntentId, array $params): \Stripe\PaymentIntent
    {
        try {
            return $this->stripe->paymentIntents->update($paymentIntentId, $params);
        } catch (ApiErrorException $e) {
            throw new \Exception('Failed to update PaymentIntent: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function incrementAuthorization(string $paymentIntentId, int $amount): \Stripe\PaymentIntent
    {
        try {
            return $this->stripe->paymentIntents->incrementAuthorization($paymentIntentId, ['amount' => $amount]);
        } catch (ApiErrorException $e) {
            throw new \Exception('Failed to increment authorization: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function searchPaymentIntent(array $params): \Stripe\SearchResult
    {
        if (empty($params['query'])) {
            throw new \InvalidArgumentException('The "query" parameter is required for searching PaymentIntents.');
        }
        
        try {
            return $this->stripe->paymentIntents->search($params);
        } catch (ApiErrorException $e) {
            throw new \Exception('Failed to search PaymentIntents: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }
}
