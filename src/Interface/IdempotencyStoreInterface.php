<?php

namespace MonkeysLegion\Stripe\Interface;

interface IdempotencyStoreInterface
{
    /**
     * Check if an event has been processed
     *
     * @param string $eventId Stripe event ID
     * @return bool
     */
    public function isProcessed(string $eventId): bool;

    /**
     * Mark an event as processed
     *
     * @param string $eventId Stripe event ID
     * @param int|null $ttl Time to live in seconds (optional)
     * @return void
     */
    public function markAsProcessed(string $eventId, ?int $ttl = null, array $eventData = []): void;

    /**
     * Remove a specific event from processed list
     *
     * @param string $eventId Stripe event ID
     * @return void
     */
    public function removeEvent(string $eventId): void;

    /**
     * Clear all processed events
     *
     * @return void
     */
    public function clearAll(): void;

    /**
     * Clean up expired events
     *
     * @return void
     */
    public function cleanupExpired(): void;

    /**
     * Get all processed events (for demo/debugging purposes)
     *
     * @return array
     */
    public function getAllEvents(): array;
}
