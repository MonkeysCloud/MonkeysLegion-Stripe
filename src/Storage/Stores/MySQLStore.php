<?php

namespace MonkeysLegion\Stripe\Storage\Stores;

use MonkeysLegion\Core\Contracts\FrameworkLoggerInterface;
use MonkeysLegion\Query\QueryBuilder;
use MonkeysLegion\Stripe\Interface\IdempotencyStoreInterface;

class MySQLStore extends AbstractStore implements IdempotencyStoreInterface
{
    public function __construct(QueryBuilder $queryBuilder, string $tableName = 'idempotency_store', private ?FrameworkLoggerInterface $logger = null)
    {
        $this->queryBuilder = $queryBuilder;
        $this->tableName = $tableName;
        $this->createTable();
    }

    /**
     * Check if an event ID has been processed
     */
    public function isProcessed(string $eventId): bool
    {
        $result = $this->queryBuilder
            ->select()
            ->from($this->tableName)
            ->where('event_id', '=', $eventId)
            ->fetch();

        if ($result && (!isset($result->expiry) || !is_string($result->expiry) || strtotime($result->expiry) > time())) {
            return true;
        }
        return false;
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
        $expiry = $ttl ? time() + $ttl : null;
        $expiry = $expiry ? date('Y-m-d H:i:s', $expiry) : null;
        $eventRow = [
            'event_id' => $eventId,
            'processed_at' => date('Y-m-d H:i:s', time()),
            'expiry' => $expiry,
            'data' => json_encode($eventData, JSON_THROW_ON_ERROR)
        ];

        $insertId = $this->queryBuilder->insert($this->tableName, $eventRow);

        // Debug: log insert result
        if (!$insertId) {
            $this->logger?->error("Failed to insert event into idempotency_store: ", $eventRow);
        } else {
            $this->logger?->info("Inserted event into idempotency_store with ID: $insertId and event_id: $eventId");
        }
    }

    /**
     * Remove an event from the processed list
     */
    public function removeEvent(string $eventId): void
    {
        $this->queryBuilder
            ->delete($this->tableName)
            ->where('event_id', '=', $eventId)
            ->execute();
    }

    /**
     * Clear all processed events
     */
    public function clearAll(): void
    {
        $this->queryBuilder
            ->delete($this->tableName)
            ->execute();
    }

    /**
     * Clean up expired events
     */
    public function cleanupExpired(): void
    {
        $now = time();
        $this->queryBuilder
            ->delete($this->tableName)
            ->where('expiry', '<', $now)
            ->execute();
    }

    /**
     * Get all processed events (for demo/debugging purposes)
     *
     * @return array<int, mixed> List of processed events
     */
    public function getAllEvents(): array
    {
        $rows = $this->queryBuilder
            ->select()
            ->from($this->tableName)
            ->fetchAll();

        $events = [];
        foreach ($rows as $row) {
            if (is_object($row) && isset($row->data)) {
                $data = is_string($row->data) ? json_decode($row->data, true) : (array)($row->data ?? []);
                if (is_array($data)) {
                    $events[] = $data;
                }
            }
        }
        return $events;
    }
}
