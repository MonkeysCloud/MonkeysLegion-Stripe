<?php

namespace MonkeysLegion\Stripe\Storage\Stores;

use MonkeysLegion\Query\QueryBuilder;
use MonkeysLegion\Stripe\Interface\IdempotencyStoreInterface;

class MySQLStore implements IdempotencyStoreInterface
{
    private QueryBuilder $queryBuilder;

    public function __construct(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }

    /**
     * Check if an event ID has been processed
     */
    public function isProcessed(string $eventId): bool
    {
        $result = $this->queryBuilder
            ->select()
            ->from('idempotency_store')
            ->where('event_id', '=', $eventId)
            ->fetch();

        if ($result && (!isset($result->expiry) || strtotime($result->expiry) > time())) {
            return true;
        }
        return false;
    }

    /**
     * Mark an event as processed with optional TTL
     */
    public function markAsProcessed(string $eventId, ?int $ttl = null, array $eventData = []): void
    {
        $expiry = $ttl ? time() + $ttl : null;
        $expiry = $expiry ? date('Y-m-d H:i:s', $expiry) : null;
        $eventRow = [
            'event_id' => $eventId,
            'processed_at' => date('Y-m-d H:i:s', time()),
            'expiry' => $expiry,
            'data' => json_encode($eventData, JSON_THROW_ON_ERROR)
        ];

        $insertId = $this->queryBuilder->insert('idempotency_store', $eventRow);

        // Debug: log insert result
        if (!$insertId) {
            error_log("Failed to insert event into idempotency_store: " . json_encode($eventRow));
        } else {
            error_log("Inserted event into idempotency_store with ID: $insertId and event_id: $eventId");
        }
    }

    /**
     * Remove an event from the processed list
     */
    public function removeEvent(string $eventId): void
    {
        $this->queryBuilder
            ->delete('idempotency_store')
            ->where('event_id', '=', $eventId)
            ->execute();
    }

    /**
     * Clear all processed events
     */
    public function clearAll(): void
    {
        $this->queryBuilder
            ->delete('idempotency_store')
            ->execute();
    }

    /**
     * Clean up expired events
     */
    public function cleanupExpired(): void
    {
        $now = time();
        $this->queryBuilder
            ->delete('idempotency_store')
            ->where('expiry', '<', $now)
            ->execute();
    }

    /**
     * Get all processed events (for demo/debugging purposes)
     */
    public function getAllEvents(): array
    {
        $rows = $this->queryBuilder
            ->select()
            ->from('idempotency_store')
            ->fetchAll();

        $events = [];
        foreach ($rows as $row) {
            if (isset($row->data)) {
                $data = is_string($row->data) ? json_decode($row->data, true) : (array)($row->data ?? []);
                if (is_array($data)) {
                    $events[] = $data;
                }
            }
        }
        return $events;
    }
}
