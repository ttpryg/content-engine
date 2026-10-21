<?php

declare(strict_types=1);

use Ttpryg\DatabaseMigrator\Migration;

/**
 * Enforce unique slugs per content type and tenant.
 *
 * Fresh installs already create this index in the baseline migration; this
 * migration upgrades databases created before the unique key existed.
 *
 * `previouslyRecordedAs()` lets existing deployments keep the tracker entry
 * from the former SQL-based migration so the index is not created twice.
 */
return new class extends Migration
{
    private const TABLE = 'contents';

    private const INDEX = 'uk_tenant_slug';

    public function up(PDO $pdo): void
    {
        if ($this->indexExists($pdo)) {
            return;
        }

        $pdo->exec(sprintf(
            'CREATE UNIQUE INDEX %s ON %s (tenant_type, tenant_id, type, slug)',
            self::INDEX,
            self::TABLE
        ));
    }

    public function down(PDO $pdo): void
    {
        if (! $this->indexExists($pdo)) {
            return;
        }

        $pdo->exec($this->isMysql($pdo)
            ? sprintf('DROP INDEX %s ON %s', self::INDEX, self::TABLE)
            : sprintf('DROP INDEX %s', self::INDEX));
    }

    public function previouslyRecordedAs(): array
    {
        return ['001_add_unique_tenant_slug'];
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

    private function isMysql(PDO $pdo): bool
    {
        return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    }
};
