<?php

namespace MonkeysLegion\Stripe\Client;
use Stripe\StripeClient;

use MonkeysLegion\Stripe\Interface\SubscriptionInterface;

class Subscription extends StripeWrapper implements SubscriptionInterface
{
    private StripeClient $stripe;
    public function __construct(StripeClient $stripeClient)
    {
        $this->stripe = $stripeClient;
    }

    public function createSubscription(string $customerId, string $priceId, array $options = []): \Stripe\Subscription
    {
        return $this->handle(function () use ($customerId, $priceId, $options) {
            return $this->stripe->subscriptions->create(array_merge([
                'customer' => $customerId,
                'items' => [['price' => $priceId]],
            ], $options));
        });
    }

    public function retrieveSubscription(string $subscriptionId, array $options = []): \Stripe\Subscription
    {
        return $this->handle(function () use ($subscriptionId, $options) {
            return $this->stripe->subscriptions->retrieve($subscriptionId, $options);
        });
    }

    public function updateSubscription(string $subscriptionId, array $params): \Stripe\Subscription
    {
        return $this->handle(function () use ($subscriptionId, $params) {
            return $this->stripe->subscriptions->update($subscriptionId, $params);
        });
    }

    public function cancelSubscription(string $subscriptionId, array $options = []): \Stripe\Subscription
    {
        return $this->handle(function () use ($subscriptionId, $options) {
            return $this->stripe->subscriptions->cancel($subscriptionId, $options);
        });
    }

    public function listSubscriptions(string $customerId, array $params = []): \Stripe\Collection
    {
        return $this->handle(function () use ($customerId, $params) {
            return $this->stripe->subscriptions->all(array_merge(['customer' => $customerId], $params));
        });
    }

    public function resumeSubscription(string $subscriptionId, array $params = []): \Stripe\Subscription
    {
        return $this->handle(function () use ($subscriptionId, $params) {
            return $this->stripe->subscriptions->update($subscriptionId, array_merge(['pause_collection' => null], $params));
        });
    }

    public function searchSubscriptions(array $params): \Stripe\SearchResult
    {
        return $this->handle(function () use ($params) {
            return $this->stripe->subscriptions->search($params);
        });
    }
}
