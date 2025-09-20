<?php

namespace MonkeysLegion\Stripe\Interface;

interface SubscriptionInterface
{
    /**
     * Create a new subscription.
     *
     * @param string $customerId The ID of the customer to subscribe.
     * @param string $priceId The ID of the price to subscribe to.
     * @param array<string, mixed> $options Optional parameters for the subscription.
     * @return \Stripe\Subscription
     * @throws \Exception if the creation fails
     */
    public function createSubscription(string $customerId, string $priceId, array $options = []): \Stripe\Subscription;

    /**
     * Retrieve a subscription by its ID.
     *
     * @param string $subscriptionId The ID of the subscription to retrieve.
     * @param array<string, mixed> $options Optional parameters for retrieval.
     * @return \Stripe\Subscription
     * @throws \Exception if the retrieval fails
     */
    public function retrieveSubscription(string $subscriptionId, array $options = []): \Stripe\Subscription;

    /**
     * Update a subscription.
     *
     * @param string $subscriptionId The ID of the subscription to update.
     * @param array<string, mixed> $params The parameters to update the subscription with.
     * @return \Stripe\Subscription
     * @throws \Exception if the update fails
     */
    public function updateSubscription(string $subscriptionId, array $params): \Stripe\Subscription;

    /**
     * Cancel a subscription.
     *
     * @param string $subscriptionId The ID of the subscription to cancel.
     * @param array<string, mixed> $options Optional parameters for cancellation.
     * @return \Stripe\Subscription
     * @throws \Exception if the cancellation fails
     */
    public function cancelSubscription(string $subscriptionId, array $options = []): \Stripe\Subscription;

    /**
     * List all subscriptions for a customer.
     *
     * @param string $customerId The ID of the customer whose subscriptions to list.
     * @param array<string, mixed> $params Optional parameters for listing subscriptions.
     * @return \Stripe\Collection<\Stripe\Subscription>
     * @throws \Exception if the listing fails
     */
    public function listSubscriptions(string $customerId, array $params = []): \Stripe\Collection;

    /**
     *  Resume a canceled subscription.
     *  @param string $subscriptionId The ID of the subscription to resume.
     *  @param array<string, mixed> $params Optional parameters for resuming the subscription.
     *  @return \Stripe\Subscription
     */
    public function resumeSubscription(string $subscriptionId, array $params = []): \Stripe\Subscription;

    /**
     * Search for subscriptions using a query.
     *
     * @param array<string, mixed> $params The search parameters, including the query string.
     * @return \Stripe\SearchResult<\Stripe\Subscription>
     * @throws \Exception if the search fails
     */
    public function searchSubscriptions(array $params): \Stripe\SearchResult;
}
