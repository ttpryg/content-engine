<?php

declare(strict_types=1);

use Ttpryg\DatabaseMigrator\Migration;

/**
 * Enforce unique slugs for global content, where nullable tenant columns would
 * otherwise bypass the composite unique index on MySQL and SQLite.
 */
return new class extends Migration
{
    private const TABLE = 'contents';

    private const COLUMN = 'global_slug_key';

    private const INDEX = 'uk_global_slug';

    public function up(PDO $pdo): void
    {
        $this->addGlobalKeyColumn($pdo);

        if ($this->indexExists($pdo)) {
            return;
        }

        $pdo->exec(sprintf(
            'CREATE UNIQUE INDEX %s ON %s (type, %s)',
            self::INDEX,
            self::TABLE,
            self::COLUMN
        ));
    }

    public function down(PDO $pdo): void
    {
        if ($this->indexExists($pdo)) {
            $pdo->exec($this->isMysql($pdo)
                ? sprintf('DROP INDEX %s ON %s', self::INDEX, self::TABLE)
                : sprintf('DROP INDEX %s', self::INDEX));
        }

        if ($this->columnExists($pdo)) {
            $pdo->exec(sprintf('ALTER TABLE %s DROP COLUMN %s', self::TABLE, self::COLUMN));
        }
    }

    private function addGlobalKeyColumn(PDO $pdo): void
    {
        if ($this->columnExists($pdo)) {
            return;
        }

        $pdo->exec(sprintf(
            'ALTER TABLE %s ADD COLUMN %s VARCHAR(255) GENERATED ALWAYS AS '
            .'(CASE WHEN tenant_type IS NULL AND tenant_id IS NULL THEN slug ELSE NULL END) VIRTUAL',
            self::TABLE,
            self::COLUMN
        ));
    }

    private function indexExists(PDO $pdo): bool
    {
        if ($this->isMysql($pdo)) {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.statistics
                 WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :index'
            );
            $stmt->execute(['table' => self::TABLE, 'index' => self::INDEX]);

            return (int) $stmt->fetchColumn() > 0;
        }

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM sqlite_master WHERE type = 'index' AND name = :index AND tbl_name = :table"
            );
            $stmt->execute(['index' => self::INDEX, 'table' => self::TABLE]);

            return (int) $stmt->fetchColumn() > 0;
        }

        return false;
    }

    private function columnExists(PDO $pdo): bool
    {
        if ($this->isMysql($pdo)) {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column'
            );
            $stmt->execute(['table' => self::TABLE, 'column' => self::COLUMN]);

            return (int) $stmt->fetchColumn() > 0;
        }

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $stmt = $pdo->query(sprintf('PRAGMA table_xinfo(%s)', self::TABLE));
            if ($stmt === false) {
                return false;
            }

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
                if (($column['name'] ?? null) === self::COLUMN) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isMysql(PDO $pdo): bool
    {
        return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    }
};
