<?php

namespace MonkeysLegion\Stripe\Client;

use MonkeysLegion\Stripe\Interface\CheckoutSessionInterface;

class CheckoutSession extends StripeWrapper implements CheckoutSessionInterface
{
    public function createCheckoutSession(array $params): \Stripe\Checkout\Session
    {
        $this->validateRequired($params, ['line_items', 'mode']);

        return $this->handle(function () use ($params) {
            $this->ensureStripeClient();
            return $this->stripe->checkout->sessions->create($params);
        });
    }

    public function retrieveCheckoutSession(string $sessionId): \Stripe\Checkout\Session
    {
        return $this->handle(function () use ($sessionId) {
            $this->ensureStripeClient();
            return $this->stripe->checkout->sessions->retrieve($sessionId);
        });
    }

    public function listCheckoutSessions(array $params = []): \Stripe\Collection
    {
        return $this->handle(function () use ($params) {
            $this->ensureStripeClient();
            return $this->stripe->checkout->sessions->all($params ?: null);
        });
    }

    public function expireCheckoutSession(string $sessionId): \Stripe\Checkout\Session
    {
        return $this->handle(function () use ($sessionId) {
            $this->ensureStripeClient();
            return $this->stripe->checkout->sessions->expire($sessionId);
        });
    }

    public function listLineItems(string $sessionId, array $params = []): \Stripe\Collection
    {
        return $this->handle(function () use ($sessionId, $params) {
            $this->ensureStripeClient();
            return $this->stripe->checkout->sessions->allLineItems($sessionId, $params ?: null);
        });
    }

    public function getCheckoutUrl(array $params): string
    {
        $session = $this->createCheckoutSession($params);
        return $session->url;
    }

    public function isValidCheckoutSession(string $sessionId): bool
    {
        return $this->handle(function () use ($sessionId) {
            $this->ensureStripeClient();
            $session = $this->stripe->checkout->sessions->retrieve($sessionId);
            return in_array($session->status, ['complete', 'open']);
        });
    }

    public function isExpiredCheckoutSession(string $sessionId): bool
    {
        return $this->handle(function () use ($sessionId) {
            $this->ensureStripeClient();
            $session = $this->stripe->checkout->sessions->retrieve($sessionId);
            return $session->status === 'expired';
        });
    }
}
