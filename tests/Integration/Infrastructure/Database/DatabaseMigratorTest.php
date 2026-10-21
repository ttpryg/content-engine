<?php

declare(strict_types=1);

namespace Ttpryg\ContentEngine\Tests\Integration\Infrastructure\Database;

use PDO;
use PHPUnit\Framework\TestCase;
use Ttpryg\ContentEngine\Infrastructure\Database\DatabaseMigrator;

class DatabaseMigratorTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function test_migrate_creates_schema_and_records_migrations(): void
    {
        $migrator = new DatabaseMigrator($this->pdo);

        $executed = $migrator->migrate();

        $this->assertSame([
            '202609170001_create_content_tables',
            '202610160001_add_unique_tenant_slug',
        ], $executed);
        $this->assertSame($executed, $migrator->appliedMigrations());

        $this->assertTrue($this->tableExists('contents'));
        $this->assertTrue($this->tableExists('categories'));
        $this->assertTrue($this->tableExists('content_category'));
        $this->assertTrue($this->indexExists('uk_tenant_slug'));
        $this->assertTrue($this->indexExists('idx_tenant_content'));
    }

    public function test_migrate_is_idempotent(): void
    {
        $migrator = new DatabaseMigrator($this->pdo);
        $migrator->migrate();

        $this->assertSame([], $migrator->migrate());
        $this->assertCount(2, $migrator->appliedMigrations());
    }

    public function test_rollback_reverts_migrations_in_reverse_order(): void
    {
        $migrator = new DatabaseMigrator($this->pdo);
        $migrator->migrate();

        $this->assertSame(['202610160001_add_unique_tenant_slug'], $migrator->rollback());
        $this->assertFalse($this->indexExists('uk_tenant_slug'));
        $this->assertTrue($this->tableExists('contents'));

        $this->assertSame(['202609170001_create_content_tables'], $migrator->rollback());
        $this->assertFalse($this->tableExists('contents'));
        $this->assertFalse($this->tableExists('categories'));
        $this->assertFalse($this->tableExists('content_category'));
    }

    public function test_legacy_sql_migration_name_is_treated_as_already_applied(): void
    {
        $migrator = new DatabaseMigrator($this->pdo);
        $migrator->migrate();

        // Simulate a database tracked by the former SQL-based runner.
        $this->pdo->exec('DROP TABLE content_engine_migrations');
        $this->pdo->exec(
            'CREATE TABLE content_engine_migrations (
                migration VARCHAR(191) NOT NULL PRIMARY KEY,
                applied_at TIMESTAMP NULL
            )'
        );
        $this->pdo->exec(
            "INSERT INTO content_engine_migrations (migration, applied_at)
             VALUES ('001_add_unique_tenant_slug', CURRENT_TIMESTAMP)"
        );

        $executed = $migrator->migrate();

        $this->assertSame(['202609170001_create_content_tables'], $executed);
        $this->assertNotContains('202610160001_add_unique_tenant_slug', $migrator->appliedMigrations());
        $this->assertTrue($this->indexExists('uk_tenant_slug'));
    }

    public function test_mark_all_as_migrated_records_baseline_without_creating_tables(): void
    {
        $migrator = new DatabaseMigrator($this->pdo);

        $recorded = $migrator->markAllAsMigrated();

        $this->assertCount(2, $recorded);
        $this->assertFalse($this->tableExists('contents'));
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = :table");
        $stmt->execute(['table' => $table]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function indexExists(string $index): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type = 'index' AND name = :index");
        $stmt->execute(['index' => $index]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
