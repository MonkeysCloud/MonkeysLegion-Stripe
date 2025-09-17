<?php

namespace MonkeysLegion\Stripe\Client;

use MonkeysLegion\Stripe\Interface\SetupIntentServiceInterface;

class SetupIntentService extends StripeWrapper implements SetupIntentServiceInterface
{
    public function createSetupIntent(array $params): \Stripe\SetupIntent
    {
        if (!isset($params['payment_method_types'])) {
            $params['payment_method_types'] = ['card'];
        }

        return $this->handle(function () use ($params) {
            $this->ensureStripeClient();
            return $this->stripe->setupIntents->create($params);
        });
    }


    public function retrieveSetupIntent(string $setupIntentId): \Stripe\SetupIntent
    {
        return $this->handle(function () use ($setupIntentId) {
            $this->ensureStripeClient();
            return $this->stripe->setupIntents->retrieve($setupIntentId);
        });
    }

    public function confirmSetupIntent(string $setupIntentId, array $params): \Stripe\SetupIntent
    {
        return $this->handle(function () use ($setupIntentId, $params) {
            $this->ensureStripeClient();
            return $this->stripe->setupIntents->confirm($setupIntentId, $params ?: null);
        });
    }

    public function cancelSetupIntent(string $setupIntentId): \Stripe\SetupIntent
    {
        return $this->handle(function () use ($setupIntentId) {
            $this->ensureStripeClient();
            return $this->stripe->setupIntents->cancel($setupIntentId);
        });
    }

    public function listSetupIntents(array $params = []): \Stripe\Collection
    {
        return $this->handle(function () use ($params) {
            $this->ensureStripeClient();
            return $this->stripe->setupIntents->all($params ?: null);
        });
    }

    public function updateSetupIntent(string $setupIntentId, array $params): \Stripe\SetupIntent
    {
        return $this->handle(function () use ($setupIntentId, $params) {
            $this->ensureStripeClient();
            return $this->stripe->setupIntents->update($setupIntentId, $params ?: null);
        });
    }

    public function isValidSetupIntent(string $setupIntentId): bool
    {
        return $this->handle(function () use ($setupIntentId) {
            $this->ensureStripeClient();
            $setupIntent = $this->stripe->setupIntents->retrieve($setupIntentId);
            return $setupIntent->status === 'succeeded';
        });
    }
}
