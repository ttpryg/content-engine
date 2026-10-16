<?php

namespace Ttpryg\ContentEngine\Tests\Unit;

use ArrayObject;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Ttpryg\ContentEngine\Database\DatabaseMigrator;

class DatabaseMigratorTest extends TestCase
{
    /**
     * @return array{0: PDO&\PHPUnit\Framework\MockObject\MockObject, 1: ArrayObject<int, string>, 2: ArrayObject<int, string>, 3: PDOStatement&\PHPUnit\Framework\MockObject\MockObject}
     */
    private function createPdoMock(): array
    {
        // ArrayObject agar perubahan dari callback terlihat di test
        // (array biasa di-return by value dan terputus dari closure karena copy-on-write).
        $executedSql = new ArrayObject;
        $preparedSql = new ArrayObject;

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('exec')->willReturnCallback(function (string $sql) use ($executedSql): int {
            $executedSql->append($sql);

            return 0;
        });
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use ($preparedSql, $stmt): PDOStatement {
            $preparedSql->append($sql);

            return $stmt;
        });

        return [$pdo, $executedSql, $preparedSql, $stmt];
    }

    // POSITIVE CASE: migrate() membaca schema.sql dan mengeksekusinya via PDO::exec
    public function test_migrate_executes_schema_sql_via_pdo(): void
    {
        [$pdo, $executedSql, $preparedSql, $stmt] = $this->createPdoMock();

        // query() melempar exception → dianggap fresh install (tabel contents belum ada).
        $pdo->method('query')->willThrowException(new PDOException('no such table: contents'));
        $stmt->method('fetchColumn')->willReturn(false); // tracker kosong

        $migrator = new DatabaseMigrator($pdo);
        $migrator->migrate();

        // Skema yang dieksekusi berisi ketiga tabel inti modul.
        $this->assertGreaterThanOrEqual(1, $executedSql->count());
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS contents', $executedSql[0]);
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS categories', $executedSql[0]);
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS content_category', $executedSql[0]);

        // Fresh install: tracker table dibuat, lalu semua migrasi dicatat sebagai baseline
        // tanpa mengeksekusi SQL migrasi (schema.sql sudah mencakup definisi terkini).
        $this->assertCount(2, $executedSql);
        $this->assertStringContainsString('content_engine_migrations', $executedSql[1]);

        $this->assertCount(2, $preparedSql);
        $this->assertStringContainsString('SELECT migration FROM content_engine_migrations', $preparedSql[0]);
        $this->assertStringContainsString('INSERT INTO content_engine_migrations', $preparedSql[1]);
    }

    // POSITIVE CASE: Database existing → migrasi inkremental yang belum tercatat dieksekusi
    public function test_migrate_runs_pending_incremental_migrations_on_existing_database(): void
    {
        [$pdo, $executedSql, $preparedSql, $stmt] = $this->createPdoMock();

        // query() sukses → tabel contents sudah ada (database existing).
        $pdo->method('query')->willReturn($this->createMock(PDOStatement::class));
        // Tracker masih kosong → 001_add_unique_tenant_slug dijalankan.
        $stmt->method('fetchColumn')->willReturn(false);

        $migrator = new DatabaseMigrator($pdo);
        $migrator->migrate();

        $this->assertCount(3, $executedSql);
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS contents', $executedSql[0]);
        $this->assertStringContainsString('content_engine_migrations', $executedSql[1]);
        // Migrasi inkremental benar-benar dieksekusi (ALTER untuk database lama).
        $this->assertStringContainsString('ALTER TABLE contents ADD UNIQUE KEY uk_tenant_slug', $executedSql[2]);

        // Migrasi tercatat di tracker setelah sukses.
        $this->assertCount(2, $preparedSql);
        $this->assertStringContainsString('INSERT INTO content_engine_migrations', $preparedSql[1]);
    }

    // POSITIVE CASE: Database existing dengan migrasi sudah tercatat → tidak dieksekusi ulang
    public function test_migrate_skips_already_applied_migrations_on_existing_database(): void
    {
        [$pdo, $executedSql, $preparedSql, $stmt] = $this->createPdoMock();

        $pdo->method('query')->willReturn($this->createMock(PDOStatement::class));
        // Tracker sudah mencatat 001 → tidak ada eksekusi ALTER.
        $stmt->method('fetchColumn')->willReturnOnConsecutiveCalls('001_add_unique_tenant_slug', false);

        $migrator = new DatabaseMigrator($pdo);
        $migrator->migrate();

        $this->assertCount(2, $executedSql);
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS contents', $executedSql[0]);
        $this->assertStringContainsString('content_engine_migrations', $executedSql[1]);

        // Hanya SELECT tracker (tanpa INSERT record) karena tidak ada migrasi baru.
        $this->assertCount(1, $preparedSql);
        $this->assertStringContainsString('SELECT migration FROM content_engine_migrations', $preparedSql[0]);
    }

    // POSITIVE CASE: Setiap statement CREATE TABLE memakai IF NOT EXISTS (idempotency migrasi)
    public function test_migrate_schema_uses_create_table_if_not_exists(): void
    {
        $sql = file_get_contents(__DIR__.'/../../database/schema.sql');
        $this->assertIsString($sql);

        preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?(\w+)/i', $sql, $matches);
        $this->assertNotEmpty($matches[1]);

        foreach ($matches[1] as $tableName) {
            $this->assertStringContainsString(
                "CREATE TABLE IF NOT EXISTS {$tableName}",
                $sql,
                "Table '{$tableName}' must use CREATE TABLE IF NOT EXISTS for idempotency."
            );
        }
    }

    // NEGATIVE CASE: PDO error saat eksekusi skema diteruskan sebagai exception oleh PDO (ERRMODE_EXCEPTION)
    public function test_migrate_propagates_pdo_failure(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('query')->willThrowException(new PDOException('no such table: contents'));
        $pdo->expects($this->once())
            ->method('exec')
            ->willThrowException(new PDOException('Syntax error in schema'));

        $migrator = new DatabaseMigrator($pdo);

        $this->expectException(PDOException::class);

        $migrator->migrate();
    }
}
