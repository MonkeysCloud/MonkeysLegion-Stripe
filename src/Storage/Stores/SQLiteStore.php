<?php

namespace MonkeysLegion\Stripe\Storage\Stores;

use MonkeysLegion\Stripe\Interface\IdempotencyStoreInterface;

class SQLiteStore implements IdempotencyStoreInterface
{
    private \PDO $pdo;
    private string $dbPath;

    public function __construct(?string $dbPath = null)
    {
        if ($dbPath !== null) {
            // Accept ':memory:' and any custom path directly
            $resolved = $dbPath;
        } else {
            $defaultPath = __DIR__ . '/../../../database/idempotency_store.sqlite';
            $resolved = realpath($defaultPath);
            if (!$resolved) throw new \RuntimeException('Database path could not be resolved.');
        }
        $this->dbPath = $resolved;
        $this->pdo = new \PDO("sqlite:{$this->dbPath}");
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->createTable();
    }

    private function createTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS idempotency_store (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_id TEXT UNIQUE NOT NULL,
                processed_at DATETIME NOT NULL,
                expiry DATETIME,
                data TEXT
            )
        ";
        $this->pdo->exec($sql);
    }

    public function isProcessed(string $eventId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM idempotency_store 
            WHERE event_id = ? AND (expiry IS NULL OR expiry > datetime('now'))
        ");
        $stmt->execute([$eventId]);
        return $stmt->fetch() !== false;
    }

    /**
     * Mark an event as processed
     *
     * @param string $eventId Stripe event ID
     * @param int|null $ttl Time-to-live for the event
     * @param array<string, mixed> $eventData Additional event data
     */
    public function markAsProcessed(string $eventId, ?int $ttl = null, array $eventData = []): void
    {
        $expiry = $ttl ? date('Y-m-d H:i:s', time() + $ttl) : null;

        $stmt = $this->pdo->prepare("
            INSERT OR REPLACE INTO idempotency_store (event_id, processed_at, expiry, data) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $eventId,
            date('Y-m-d H:i:s'),
            $expiry,
            json_encode($eventData, JSON_THROW_ON_ERROR)
        ]);
    }

    public function removeEvent(string $eventId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM idempotency_store WHERE event_id = ?");
        $stmt->execute([$eventId]);
    }

    public function clearAll(): void
    {
        $this->pdo->exec("DELETE FROM idempotency_store");
    }

    public function cleanupExpired(): void
    {
        $this->pdo->exec("DELETE FROM idempotency_store WHERE expiry IS NOT NULL AND expiry < datetime('now')");
    }

    /**
     * Get all processed events
     *
     * @return array<int, array<mixed>> List of processed events
     */
    public function getAllEvents(): array
    {
        $stmt = $this->pdo->query("SELECT data FROM idempotency_store WHERE data IS NOT NULL");
        if (!$stmt) {
            throw new \RuntimeException('Failed to retrieve processed events.');
        }

        $events = [];

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $data = is_array($row) && isset($row['data']) && is_string($row['data'])
                ? json_decode($row['data'], true)
                : null;

            if (is_array($data)) {
                $events[] = $data;
            }
        }

        return $events;
    }
}
