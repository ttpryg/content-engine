<?php

namespace Ttpryg\ContentEngine\Database;

use PDO;

class DatabaseMigrator
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function migrate(): void
    {
        $sql = file_get_contents(__DIR__ . '/../../database/schema.sql');
        if ($sql === false) {
            throw new \RuntimeException("Unable to load content-engine schema.sql file.");
        }

        $this->pdo->exec($sql);
    }
}
