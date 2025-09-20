<?php

namespace MonkeysLegion\Stripe\Storage\Stores;

use MonkeysLegion\Database\SQLite\Connection;
use MonkeysLegion\Query\QueryBuilder;
use MonkeysLegion\Stripe\Interface\IdempotencyStoreInterface;
use PDO;

class SQLiteStore extends AbstractStore implements IdempotencyStoreInterface
{
    /**
     * @param string|null $filePath Path to SQLite file. Use ':memory:' for in-memory DB.
     *                              Use 'shared' for shared in-memory cache.
     */
    public function __construct(string $tableName = 'idempotency_store', ?string $filePath = null)
    {
        if ($filePath === 'shared') {
            // Shared in-memory cache accessible across multiple connections
            $dsn = 'sqlite:file::memory:?cache=shared';
        } elseif ($filePath === null || $filePath === ':memory:') {
            // Pure in-memory DB, unique per connection
            $dsn = 'sqlite::memory:';
        } else {
            // Persistent file-based SQLite
            $dsn = 'sqlite:' . $filePath;
        }

        $this->queryBuilder = new QueryBuilder(new Connection([
            'dsn' => $dsn,
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ],
        ]));

        $this->tableName = $tableName;
        $this->createTable();
    }

    public function isProcessed(string $eventId): bool
    {
        $result = $this->queryBuilder
            ->select()
            ->from($this->tableName)
            ->where('event_id', '=', $eventId)
            ->andWhere('expiry', 'IS', null)
            ->orWhere('expiry', '>', date('Y-m-d H:i:s'))
            ->fetch();

        return $result !== false;
    }

    public function markAsProcessed(string $eventId, ?int $ttl = null, array $eventData = []): void
    {
        $expiry = $ttl ? date('Y-m-d H:i:s', time() + $ttl) : null;

        $data = [
            'event_id' => $eventId,
            'processed_at' => date('Y-m-d H:i:s'),
            'expiry' => $expiry,
            'data' => json_encode($eventData, JSON_THROW_ON_ERROR),
        ];

        try {
            $this->queryBuilder->insert($this->tableName, $data);
        } catch (\Exception $e) {
            $this->queryBuilder
                ->update($this->tableName, $data)
                ->where('event_id', '=', $eventId)
                ->execute();
        }
    }

    public function removeEvent(string $eventId): void
    {
        $this->queryBuilder
            ->delete($this->tableName)
            ->where('event_id', '=', $eventId)
            ->execute();
    }

    public function clearAll(): void
    {
        $this->queryBuilder
            ->delete($this->tableName)
            ->execute();
    }

    public function cleanupExpired(): void
    {
        $this->queryBuilder
            ->delete($this->tableName)
            ->where('expiry', 'IS NOT', null)
            ->andWhere('expiry', '<', date('Y-m-d H:i:s'))
            ->execute();
    }

    public function getAllEvents(): array
    {
        $rows = $this->queryBuilder
            ->select(['data'])
            ->from($this->tableName)
            ->whereNotNull('data')
            ->fetchAll();

        $events = [];
        foreach ($rows as $row) {
            if (isset($row['data']) && is_string($row['data'])) {
                $data = json_decode($row['data'], true);
                if (is_array($data)) {
                    $events[] = $data;
                }
            }
        }

        return $events;
    }
}
