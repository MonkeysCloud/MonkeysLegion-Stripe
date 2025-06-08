<?php

namespace MonkeysLegion\Stripe\Interface;

interface SetupIntentServiceInterface
{

    /**
     * Create a new Setup Intent
     *
     * @param array<string, mixed> $params Parameters for the Setup Intent
     * @return \Stripe\SetupIntent
     */
    public function createSetupIntent(array $params): \Stripe\SetupIntent;

    /**
     * Retrieve a Setup Intent by its ID
     *
     * @param string $setupIntentId The ID of the Setup Intent to retrieve
     * @return \Stripe\SetupIntent
     */
    public function retrieveSetupIntent(string $setupIntentId): \Stripe\SetupIntent;

    /**
     * Confirm a Setup Intent
     *
     * @param string $setupIntentId The ID of the Setup Intent to confirm
     * @param array<string, mixed> $params Parameters for confirming the Setup Intent
     * @return \Stripe\SetupIntent
     */
    public function confirmSetupIntent(string $setupIntentId, array $params): \Stripe\SetupIntent;

    /**
     * Cancel a Setup Intent
     *
     * @param string $setupIntentId The ID of the Setup Intent to cancel
     * @return \Stripe\SetupIntent
     */
    public function cancelSetupIntent(string $setupIntentId): \Stripe\SetupIntent;


    /**
     * List Setup Intents
     *
     * @param array<string, mixed> $params Optional parameters for listing Setup Intents
     * @return \Stripe\Collection<\Stripe\SetupIntent>
     */
    public function listSetupIntents(array $params = []): \Stripe\Collection;

    /**
     * Update a Setup Intent
     *
     * @param string $setupIntentId The ID of the Setup Intent to update
     * @param array<string, mixed> $params Parameters for updating the Setup Intent
     * @return \Stripe\SetupIntent
     */
    public function updateSetupIntent(string $setupIntentId, array $params): \Stripe\SetupIntent;

    /**
     * Verify if a Setup Intent is valid
     *
     * @param string $setupIntentId The ID of the Setup Intent to verify
     * @return bool
     */
    public function isValidSetupIntent(string $setupIntentId): bool;
}
