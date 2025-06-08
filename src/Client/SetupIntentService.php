<?php

namespace MonkeysLegion\Stripe\Client;

use MonkeysLegion\Stripe\Interface\SetupIntentServiceInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class SetupIntentService extends StripeGateway implements SetupIntentServiceInterface
{
    public function __construct(StripeClient $stripeClient)
    {
        $this->stripe = $stripeClient;
    }

    public function createSetupIntent(array $params): \Stripe\SetupIntent
    {
        if (empty($params['customer'])) {
            throw new \InvalidArgumentException('Customer ID is required to create a SetupIntent.');
        }

        return $this->handle(function () use ($params) {
            return $this->stripe->setupIntents->create($params);
        });
    }


    public function retrieveSetupIntent(string $setupIntentId): \Stripe\SetupIntent
    {
        return $this->handle(function () use ($setupIntentId) {
            return $this->stripe->setupIntents->retrieve($setupIntentId);
        });
    }

    public function confirmSetupIntent(string $setupIntentId, array $params): \Stripe\SetupIntent
    {
        return $this->handle(function () use ($setupIntentId, $params) {
            return $this->stripe->setupIntents->confirm($setupIntentId, $params ?: null);
        });
    }

    public function cancelSetupIntent(string $setupIntentId): \Stripe\SetupIntent
    {
        return $this->handle(function () use ($setupIntentId) {
            return $this->stripe->setupIntents->cancel($setupIntentId);
        });
    }

    public function listSetupIntents(array $params = []): \Stripe\Collection
    {
        return $this->handle(function () use ($params) {
            return $this->stripe->setupIntents->all($params ?: null);
        });
    }

    public function updateSetupIntent(string $setupIntentId, array $params): \Stripe\SetupIntent
    {
        return $this->handle(function () use ($setupIntentId, $params) {
            return $this->stripe->setupIntents->update($setupIntentId, $params ?: null);
        });
    }

    public function isValidSetupIntent(string $setupIntentId): bool
    {
        return $this->handle(function () use ($setupIntentId) {
            $setupIntent = $this->stripe->setupIntents->retrieve($setupIntentId);
            return $setupIntent->status === 'succeeded';
        });
    }
}
