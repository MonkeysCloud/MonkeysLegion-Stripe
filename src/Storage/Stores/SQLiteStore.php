<?php

namespace MonkeysLegion\Stripe\Storage\Stores;

use MonkeysLegion\Stripe\Interface\IdempotencyStoreInterface;

class SQLiteStore implements IdempotencyStoreInterface
{
    private \PDO $pdo;
    private string $dbPath;

    public function __construct(?string $dbPath = null)
    {
        $this->dbPath = $dbPath ?? realpath(__DIR__ . '/../../../database/idempotency_store.sqlite');
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
            json_encode($eventData)
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

    public function getAllEvents(): array
    {
        $stmt = $this->pdo->query("SELECT data FROM idempotency_store WHERE data IS NOT NULL");
        $events = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $data = json_decode($row['data'], true);
            if (is_array($data)) {
                $events[] = $data;
            }
        }
        return $events;
    }
}
