<?php

namespace MonkeysLegion\Stripe\Storage\Stores;

use MonkeysLegion\Stripe\Interface\IdempotencyStoreInterface;

class InMemoryStore implements IdempotencyStoreInterface
{
    /**
     * In-memory storage of processed events
     *
     * @var array<string, array<string, mixed>>
     */
    private array $processedEvents = [];

    public function __construct()
    {
        // Nothing to initialize for in-memory storage
    }

    /**
     * Check if an event ID has been processed
     */
    public function isProcessed(string $eventId): bool
    {
        if (!isset($this->processedEvents[$eventId])) {
            return false;
        }

        $eventData = $this->processedEvents[$eventId];

        // Check if event has expired
        if (isset($eventData['expiry']) && $eventData['expiry'] != null && $eventData['expiry'] <= time()) {
            unset($this->processedEvents[$eventId]);
            return false;
        }

        return true;
    }

    /**
     * Mark an event as processed with optional TTL
     *
     * @param string $eventId Stripe event ID
     * @param int|null $ttl Time-to-live for the event
     * @param array<string, mixed> $eventData Additional event data
     */
    public function markAsProcessed(string $eventId, ?int $ttl = null, array $eventData = []): void
    {
        $expiry = $ttl !== null ? time() + $ttl : null;

        $this->processedEvents[$eventId] = [
            'processed_at' => time(),
            'expiry' => $expiry,
            'data' => $eventData
        ];
    }

    /**
     * Remove an event from the processed list
     */
    public function removeEvent(string $eventId): void
    {
        unset($this->processedEvents[$eventId]);
    }

    /**
     * Clear all processed events
     */
    public function clearAll(): void
    {
        $this->processedEvents = [];
    }

    /**
     * Clean up expired events
     */
    public function cleanupExpired(): void
    {
        $now = time();
        foreach ($this->processedEvents as $eventId => $eventData) {
            if (isset($eventData['expiry']) && $eventData['expiry'] != null && $eventData['expiry'] < $now) {
                unset($this->processedEvents[$eventId]);
            }
        }
    }

    /**
     * Get all processed events (for demo/debugging purposes)
     *
     * @return array<int, mixed> List of processed events
     */
    public function getAllEvents(): array
    {
        $events = [];
        foreach ($this->processedEvents as $eventData) {
            if (isset($eventData['data']) && is_array($eventData['data'])) {
                $events[] = $eventData['data'];
            }
        }
        return $events;
    }
}
