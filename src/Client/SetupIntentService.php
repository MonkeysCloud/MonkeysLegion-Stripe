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
        if (!$params['customer']) {
            throw new \InvalidArgumentException('Customer ID is required to create a SetupIntent.');
        }

        try {
            return $this->stripe->setupIntents->create($params);
        } catch (ApiErrorException $e) {
            throw new \Exception('Failed to create SetupIntent: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }


    public function retrieveSetupIntent(string $setupIntentId, array $options = []): \Stripe\SetupIntent
    {
        try {
            return $this->stripe->setupIntents->retrieve($setupIntentId, $options);
        } catch (ApiErrorException $e) {
            throw new \Exception('Failed to retrieve SetupIntent: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }


    public function confirmSetupIntent(string $setupIntentId, array $params): \Stripe\SetupIntent
    {
        try {
            return $this->stripe->setupIntents->confirm($setupIntentId, $params);
        } catch (ApiErrorException $e) {
            throw new \Exception('Failed to confirm SetupIntent: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function cancelSetupIntent(string $setupIntentId, array $options = []): \Stripe\SetupIntent
    {
        try {
            return $this->stripe->setupIntents->cancel($setupIntentId);
        } catch (ApiErrorException $e) {
            throw new \Exception('Failed to cancel SetupIntent: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function listSetupIntents(array $params = []): \Stripe\Collection
    {
        try {
            return $this->stripe->setupIntents->all($params);
        } catch (ApiErrorException $e) {
            throw new \Exception('Failed to list SetupIntents: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function updateSetupIntent(string $setupIntentId, array $params): \Stripe\SetupIntent
    {
        try {
            return $this->stripe->setupIntents->update($setupIntentId, $params);
        } catch (ApiErrorException $e) {
            throw new \Exception('Failed to update SetupIntent: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function isValidSetupIntent(string $setupIntentId): bool
    {
        try {
            $setupIntent = $this->stripe->setupIntents->retrieve($setupIntentId);
            return $setupIntent->status === 'succeeded';
        } catch (ApiErrorException $e) {
            throw new \Exception('Failed to verify SetupIntent: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }
}
