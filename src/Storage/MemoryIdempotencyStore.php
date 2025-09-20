<?php

namespace MonkeysLegion\Stripe\Storage;

use MonkeysLegion\Core\Contracts\FrameworkLoggerInterface;
use MonkeysLegion\Query\QueryBuilder;
use MonkeysLegion\Stripe\Interface\IdempotencyStoreInterface;
use MonkeysLegion\Stripe\Storage\Stores\InMemoryStore;
use MonkeysLegion\Stripe\Storage\Stores\MySQLStore;
use MonkeysLegion\Stripe\Storage\Stores\SQLiteStore;

class MemoryIdempotencyStore implements IdempotencyStoreInterface
{
    private IdempotencyStoreInterface $store;

    public function __construct(?QueryBuilder $queryBuilder = null, ?FrameworkLoggerInterface $logger = null, string $tableName = 'idempotency_store')
    {
        $appEnv = $_ENV['APP_ENV'] ?? 'dev';
        $this->store = match ($appEnv) {
            'prod', 'production' => $queryBuilder ? new MySQLStore($queryBuilder, $tableName, $logger) : throw new \RuntimeException('QueryBuilder is required for production environment'),
            'test', 'testing' => new SQLiteStore($tableName),
            default => new InMemoryStore()
        };
    }

    public function useProdStore(QueryBuilder $queryBuilder, string $tableName = 'idempotency_store', ?FrameworkLoggerInterface $logger = null): void
    {
        $this->store = new MySQLStore($queryBuilder, $tableName, $logger);
    }

    public function useLiteStore(string $tableName = 'idempotency_store'): void
    {
        $this->store = new SQLiteStore($tableName);
    }

    public function useMemoStore(): void
    {
        $this->store = new InMemoryStore();
    }

    public function getStore(): IdempotencyStoreInterface
    {
        return $this->store;
    }

    public function setStore(IdempotencyStoreInterface $store): void
    {
        $this->store = $store;
    }

    /**
     * Check if an event ID has been processed
     */
    public function isProcessed(string $eventId): bool
    {
        return $this->store->isProcessed($eventId);
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
        $this->store->markAsProcessed($eventId, $ttl, $eventData);
    }

    /**
     * Remove an event from the processed list
     */
    public function removeEvent(string $eventId): void
    {
        $this->store->removeEvent($eventId);
    }

    /**
     * Clear all processed events
     */
    public function clearAll(): void
    {
        $this->store->clearAll();
    }

    /**
     * Clean up expired events
     */
    public function cleanupExpired(): void
    {
        $this->store->cleanupExpired();
    }

    /**
     * Get all processed events (for demo/debugging purposes)
     *
     * @return array<int, mixed> List of processed events
     */
    public function getAllEvents(): array
    {
        return $this->store->getAllEvents();
    }
}
