<?php

declare(strict_types=1);

namespace MonkeysLegion\Stripe\Storage\Stores;

use MonkeysLegion\Query\QueryBuilder;

abstract class AbstractStore
{
    protected QueryBuilder $queryBuilder;
    protected string $tableName = 'idempotency_store';

    protected function createTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS `$this->tableName` (
                `id` INTEGER PRIMARY KEY,
                `event_id` TEXT NOT NULL,
                `data` TEXT NOT NULL,
                `expiry` DATETIME DEFAULT NULL,
                `processed_at` DATETIME DEFAULT NULL,
                UNIQUE(`event_id`),
                CHECK (json_valid(`data`))
            );";

        $this->queryBuilder->exec($sql);
    }
}
