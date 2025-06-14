<?php

namespace MonkeysLegion\Stripe\Storage;

use MonkeysLegion\Database\MySQL\Connection;
use MonkeysLegion\Query\QueryBuilder;
use MonkeysLegion\Stripe\Interface\IdempotencyStoreInterface;

use function PHPUnit\Framework\isNull;

class MemoryIdempotencyStore implements IdempotencyStoreInterface
{
    /**
     * In-memory storage of processed events
     */
    private array $processedEvents = [];
    private QueryBuilder $queryBuilder;

    public function __construct(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;

        $rows = $this->queryBuilder
            ->select()
            ->from('idempotency_store')
            ->fetchAll();

        $this->processedEvents = [];
        foreach ($rows as $row) {
            $eventId = $row->event_id;
            $this->processedEvents[$eventId] = [
                'processed_at' => $row->processed_at,
                'expiry' => $row->expiry,
                'data' => is_string($row->data) ? json_decode($row->data, true) : (array)($row->data ?? [])
            ];
        }
    }

    /**
     * Check if an event ID has been processed
     */
    public function isProcessed(string $eventId): bool
    {
        $this->processedEvents = [];
        $result = $this->queryBuilder
            ->select()
            ->from('idempotency_store')
            ->where('event_id', '=', $eventId)
            ->fetch();
        if ($result && (!isset($result->expiry) || strtotime($result->expiry) > time())) {
            $this->processedEvents[$eventId] = [
                'processed_at' => $result->processed_at,
                'expiry' => $result->expiry,
                'data' => is_string($result->data) ? json_decode($result->data, true) : (array)($result->data ?? [])
            ];
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

        // Reload memory from DB
        $this->reloadProcessedEvents();
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
        // Reload memory from DB
        $this->reloadProcessedEvents();
    }

    /**
     * Clear all processed events
     */
    public function clearAll(): void
    {
        $this->queryBuilder
            ->delete('idempotency_store')
            ->execute();
        // Reload memory from DB
        $this->reloadProcessedEvents();
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
        // Reload memory from DB
        $this->reloadProcessedEvents();
    }

    private function reloadProcessedEvents(): void
    {
        $rows = $this->queryBuilder
            ->select()
            ->from('idempotency_store')
            ->fetchAll();
        $this->processedEvents = [];
        foreach ($rows as $row) {
            $eventId = $row->event_id;
            $this->processedEvents[$eventId] = [
                'processed_at' => $row->processed_at,
                'expiry' => $row->expiry,
                'data' => is_string($row->data) ? json_decode($row->data, true) : (array)($row->data ?? [])
            ];
        }
    }

    /**
     * Get all processed events (for demo/debugging purposes)
     */
    public function getAllEvents(): array
    {
        $events = [];
        foreach ($this->processedEvents as $eventId => $data) {
            if (isset($data['data']) && is_array($data['data'])) {
                $events[] = $data['data'];
            }
        }
        return $events;
    }
}
