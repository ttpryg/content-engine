<?php

declare(strict_types=1);

namespace Ttpryg\ContentEngine\Database;

use PDO;

class DatabaseMigrator
{
    public function __construct(private readonly PDO $pdo) {}

    public function migrate(): void
    {
        $sql = file_get_contents(__DIR__.'/../../database/schema.sql');
        if ($sql === false) {
            throw new \RuntimeException('Unable to load content-engine schema.sql file.');
        }

        $this->pdo->exec($sql);
    }
}
