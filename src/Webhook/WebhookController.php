<?php

namespace MonkeysLegion\Stripe\Webhook;

use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

class WebhookController
{
    protected string $webhookSecret;

    public function __construct(string $webhookSecret)
    {
        $this->webhookSecret = $webhookSecret;
    }

    /**
     * Handle incoming webhook from Stripe
     *
     * @param string $payload The raw request body
     * @param string $signature The Stripe-Signature header
     * @return array The verified event data
     * @throws SignatureVerificationException
     */
    public function handleWebhook(string $payload, string $signature): array
    {
        $event = Webhook::constructEvent($payload, $signature, $this->webhookSecret);

        return [
            'id' => $event->id,
            'type' => $event->type,
            'data' => $event->data
        ];
    }

    /**
     * Verify webhook signature without processing
     *
     * @param string $payload The raw request body
     * @param string $signature The Stripe-Signature header
     * @return bool
     */
    public function verifySignature(string $payload, string $signature): bool
    {
        try {
            Webhook::constructEvent($payload, $signature, $this->webhookSecret);
            return true;
        } catch (SignatureVerificationException $e) {
            return false;
        }
    }
}
