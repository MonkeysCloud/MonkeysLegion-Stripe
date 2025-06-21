<?php

namespace MonkeysLegion\Stripe\Client;

use MonkeysLegion\Stripe\Interface\StripeGatewayInterface;
use Stripe\StripeClient;

class StripeGateway extends StripeWrapper implements StripeGatewayInterface
{
    public function createPaymentIntent(int $amount, string $currency = 'usd', bool $automatic_payment_methods = true): \Stripe\PaymentIntent
    {
        return $this->handle(function () use ($amount, $currency, $automatic_payment_methods) {
            return $this->stripe->paymentIntents->create([
                'amount' => $amount,
                'currency' => $currency,
                'automatic_payment_methods' => ['enabled' => $automatic_payment_methods],
            ]);
        });
    }

    public function retrievePaymentIntent(string $paymentIntentId): \Stripe\PaymentIntent
    {
        return $this->handle(function () use ($paymentIntentId) {
            return $this->stripe->paymentIntents->retrieve($paymentIntentId);
        });
    }

    public function confirmPaymentIntent(string $paymentIntentId, array $options = []): \Stripe\PaymentIntent
    {
        return $this->handle(function () use ($paymentIntentId, $options) {
            return $this->stripe->paymentIntents->confirm($paymentIntentId, $options ?: null);
        });
    }

    public function cancelPaymentIntent(string $paymentIntentId): \Stripe\PaymentIntent
    {
        return $this->handle(function () use ($paymentIntentId) {
            return $this->stripe->paymentIntents->cancel($paymentIntentId);
        });
    }

    public function capturePaymentIntent(string $paymentIntentId): \Stripe\PaymentIntent
    {
        return $this->handle(function () use ($paymentIntentId) {
            return $this->stripe->paymentIntents->capture($paymentIntentId);
        });
    }

    public function refundPaymentIntent(string $paymentIntentId, array $options = []): \Stripe\Refund
    {
        return $this->handle(function () use ($paymentIntentId, $options) {
            $params = ['payment_intent' => $paymentIntentId];
            if (!empty($options)) {
                $params = array_merge($params, $options);
            }
            return $this->stripe->refunds->create($params);
        });
    }

    public function listPaymentIntent(array $params = []): \Stripe\Collection
    {
        return $this->handle(function () use ($params) {
            return $this->stripe->paymentIntents->all($params ?: null);
        });
    }

    public function updatePaymentIntent(string $paymentIntentId, array $params): \Stripe\PaymentIntent
    {
        return $this->handle(function () use ($paymentIntentId, $params) {
            return $this->stripe->paymentIntents->update($paymentIntentId, $params ?: null);
        });
    }

    public function incrementAuthorization(string $paymentIntentId, int $amount): \Stripe\PaymentIntent
    {
        return $this->handle(function () use ($paymentIntentId, $amount) {
            return $this->stripe->paymentIntents->incrementAuthorization($paymentIntentId, ['amount' => $amount]);
        });
    }

    public function searchPaymentIntent(array $params): \Stripe\SearchResult
    {
        if (empty($params['query'])) {
            throw new \InvalidArgumentException('The "query" parameter is required for searching PaymentIntents.');
        }

        return $this->handle(function () use ($params) {
            return $this->stripe->paymentIntents->search($params);
        });
    }

    public function isValidPaymentIntent(string $paymentIntentId): bool
    {
        return $this->handle(function () use ($paymentIntentId) {
            $paymentIntent = $this->stripe->paymentIntents->retrieve($paymentIntentId);
            return in_array($paymentIntent->status, ['succeeded', 'requires_capture']);
        });
    }
}
