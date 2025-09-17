<?php

namespace MonkeysLegion\Stripe\Client;

use MonkeysLegion\Stripe\Interface\StripeGatewayInterface;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Collection;
use Stripe\SearchResult;

class StripeGateway extends StripeWrapper implements StripeGatewayInterface
{
    public function createPaymentIntent(int $amount, string $currency = 'usd', bool $automatic_payment_methods = true): PaymentIntent
    {
        return $this->handle(function () use ($amount, $currency, $automatic_payment_methods) {
            $this->ensureStripeClient();
            return $this->stripe->paymentIntents->create([
                'amount' => $amount,
                'currency' => $currency,
                'automatic_payment_methods' => ['enabled' => $automatic_payment_methods],
            ]);
        });
    }

    public function retrievePaymentIntent(string $paymentIntentId): PaymentIntent
    {
        return $this->handle(function () use ($paymentIntentId) {
            $this->ensureStripeClient();
            return $this->stripe->paymentIntents->retrieve($paymentIntentId);
        });
    }

    public function confirmPaymentIntent(string $paymentIntentId, array $options = []): PaymentIntent
    {
        return $this->handle(function () use ($paymentIntentId, $options) {
            $this->ensureStripeClient();
            return $this->stripe->paymentIntents->confirm($paymentIntentId, $options ?: null);
        });
    }

    public function cancelPaymentIntent(string $paymentIntentId): PaymentIntent
    {
        return $this->handle(function () use ($paymentIntentId) {
            $this->ensureStripeClient();
            return $this->stripe->paymentIntents->cancel($paymentIntentId);
        });
    }

    public function capturePaymentIntent(string $paymentIntentId): PaymentIntent
    {
        return $this->handle(function () use ($paymentIntentId) {
            $this->ensureStripeClient();
            return $this->stripe->paymentIntents->capture($paymentIntentId);
        });
    }

    public function refundPaymentIntent(string $paymentIntentId, array $options = []): Refund
    {
        return $this->handle(function () use ($paymentIntentId, $options) {
            $this->ensureStripeClient();
            $params = ['payment_intent' => $paymentIntentId];
            if (!empty($options)) {
                $params = array_merge($params, $options);
            }
            return $this->stripe->refunds->create($params);
        });
    }

    public function listPaymentIntent(array $params = []): Collection
    {
        return $this->handle(function () use ($params) {
            $this->ensureStripeClient();
            return $this->stripe->paymentIntents->all($params ?: null);
        });
    }

    public function updatePaymentIntent(string $paymentIntentId, array $params): PaymentIntent
    {
        return $this->handle(function () use ($paymentIntentId, $params) {
            $this->ensureStripeClient();
            return $this->stripe->paymentIntents->update($paymentIntentId, $params ?: null);
        });
    }

    public function incrementAuthorization(string $paymentIntentId, int $amount): PaymentIntent
    {
        return $this->handle(function () use ($paymentIntentId, $amount) {
            $this->ensureStripeClient();
            return $this->stripe->paymentIntents->incrementAuthorization($paymentIntentId, ['amount' => $amount]);
        });
    }

    public function searchPaymentIntent(array $params): SearchResult
    {
        if (empty($params['query'])) {
            throw new \InvalidArgumentException('The "query" parameter is required for searching PaymentIntents.');
        }

        return $this->handle(function () use ($params) {
            $this->ensureStripeClient();
            return $this->stripe->paymentIntents->search($params);
        });
    }

    public function isValidPaymentIntent(string $paymentIntentId): bool
    {
        return $this->handle(function () use ($paymentIntentId) {
            $this->ensureStripeClient();
            $paymentIntent = $this->stripe->paymentIntents->retrieve($paymentIntentId);
            return in_array($paymentIntent->status, ['succeeded', 'requires_capture'], true);
        });
    }
}
