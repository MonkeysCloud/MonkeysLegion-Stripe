<?php

namespace MonkeysLegion\Stripe\Interface;

interface CheckoutSessionInterface
{
    /**
     * Create a new Checkout Session.
     *
     * @param array<string, mixed> $params Parameters for creating the Checkout Session.
     * @return \Stripe\Checkout\Session
     * @throws \Exception if the creation fails
     */
    public function createCheckoutSession(array $params): \Stripe\Checkout\Session;

    /**
     * Retrieve a Checkout Session by its ID.
     *
     * @param string $sessionId The ID of the Checkout Session to retrieve.
     * @return \Stripe\Checkout\Session
     * @throws \Exception if the retrieval fails
     */
    public function retrieveCheckoutSession(string $sessionId): \Stripe\Checkout\Session;

    /**
     * List all Checkout Sessions.
     *
     * @param array<string, mixed> $params Optional parameters for listing Checkout Sessions.
     * @return \Stripe\Collection<\Stripe\Checkout\Session>
     * @throws \Exception if the listing fails
     */
    public function listCheckoutSessions(array $params = []): \Stripe\Collection;

    /**
     * Expire a Checkout Session.
     *
     * @param string $sessionId The ID of the Checkout Session to expire.
     * @return \Stripe\Checkout\Session
     * @throws \Exception if the expiration fails
     */
    public function expireCheckoutSession(string $sessionId): \Stripe\Checkout\Session;

    /**
     * Retrieve line items for a Checkout Session.
     *
     * @param string $sessionId The ID of the Checkout Session.
     * @param array<string, mixed> $params Optional parameters for listing line items.
     * @return \Stripe\Collection<\Stripe\LineItem>
     * @throws \Exception if the retrieval fails
     */
    public function listLineItems(string $sessionId, array $params = []): \Stripe\Collection;

    /**
     * Check if a Checkout Session is valid and completed.
     *
     * @param string $sessionId The ID of the Checkout Session to check.
     * @return bool
     */
    public function isValidCheckoutSession(string $sessionId): bool;

    /**
     * Check if a Checkout Session is expired.
     *
     * @param string $sessionId The ID of the Checkout Session to check.
     * @return bool
     */
    public function isExpiredCheckoutSession(string $sessionId): bool;

    /**
     * Create a Checkout Session and return its URL.
     *
     * @param array<string, mixed> $params Parameters for creating the Checkout Session.
     * @return string The checkout URL
     * @throws \Exception if the creation fails
     */
    public function getCheckoutUrl(array $params): string;
}
