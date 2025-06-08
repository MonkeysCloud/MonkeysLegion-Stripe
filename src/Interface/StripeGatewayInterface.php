<?php

namespace MonkeysLegion\Stripe\Interface;

interface StripeGatewayInterface
{
    /**
     * Create a new PaymentIntent.
     *
     * @param int $amount The amount to charge in the smallest currency unit (e.g., cents for USD).
     * @param string $currency The currency to use for the payment (default is 'usd').
     * @param bool $automatic_payment_methods Whether to enable automatic payment methods (default is true).
     * @return \Stripe\PaymentIntent
     * @throws \Exception if the creation fails
     */
    public function createPaymentIntent(int $amount, string $currency = 'usd', bool $automatic_payment_methods = true): \Stripe\PaymentIntent;

    /**
     * Retrieve a PaymentIntent by its ID.
     *
     * @param string $paymentIntentId The ID of the PaymentIntent to retrieve.
     * @return \Stripe\PaymentIntent
     * @throws \Exception if the retrieval fails
     */
    public function retrievePaymentIntent(string $paymentIntentId): \Stripe\PaymentIntent;

    /**
     * Confirm a PaymentIntent.
     *
     * @param string $paymentIntentId The ID of the PaymentIntent to confirm.
     * @param array<string, mixed> $options Optional parameters for the confirmation.
     * @return \Stripe\PaymentIntent
     * @throws \Exception if the confirmation fails
     */
    public function confirmPaymentIntent(string $paymentIntentId, array $options = []): \Stripe\PaymentIntent;

    /**
     * Refund a PaymentIntent.
     *
     * @param string $paymentIntentId The ID of the PaymentIntent to refund.
     * @param array<string, mixed> $options Optional parameters for the refund.
     * @return \Stripe\Refund
     * @throws \Exception if the refund fails
     */
    public function refundPaymentIntent(string $paymentIntentId, array $options = []): \Stripe\Refund;

    /**
     * List all PaymentIntents.
     *
     * @param array<string, mixed> $params Optional parameters for listing PaymentIntents.
     * @return \Stripe\Collection<\Stripe\PaymentIntent>
     * @throws \Exception if the listing fails
     */
    public function listPaymentIntent(array $params = []): \Stripe\Collection;

    /**
     * Update a PaymentIntent.
     *
     * @param string $paymentIntentId The ID of the PaymentIntent to update.
     * @param array<string, mixed> $params The parameters to update the PaymentIntent with.
     * @return \Stripe\PaymentIntent
     * @throws \Exception if the update fails
     */
    public function updatePaymentIntent(string $paymentIntentId, array $params): \Stripe\PaymentIntent;

    /**
     * Search for PaymentIntents using a query.
     *
     * @param array<string, mixed> $params The search parameters, including the query string.
     * @return \Stripe\SearchResult<\Stripe\PaymentIntent>
     * @throws \Exception if the search fails
     */
    public function searchPaymentIntent(array $params): \Stripe\SearchResult;

    /**
     * Check if a PaymentIntent is valid.
     *
     * @param string $paymentIntentId The ID of the PaymentIntent to check.
     * @return bool
     */
    public function isValidPaymentIntent(string $paymentIntentId): bool;
}
