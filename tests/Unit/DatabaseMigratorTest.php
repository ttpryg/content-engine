<?php

namespace Ttpryg\ContentEngine\Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;
use Ttpryg\ContentEngine\Database\DatabaseMigrator;

class DatabaseMigratorTest extends TestCase
{
    // POSITIVE CASE: migrate() membaca schema.sql dan mengeksekusinya via PDO::exec
    public function test_migrate_executes_schema_sql_via_pdo(): void
    {
        $pdo = $this->createMock(PDO::class);
        $executedSql = null;

        $pdo->expects($this->once())
            ->method('exec')
            ->willReturnCallback(function (string $sql) use (&$executedSql): int {
                $executedSql = $sql;

                return 0;
            });

        $migrator = new DatabaseMigrator($pdo);
        $migrator->migrate();

        $this->assertIsString($executedSql);
        // Skema yang dieksekusi harus berisi ketiga tabel inti modul.
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS contents', $executedSql);
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS categories', $executedSql);
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS content_category', $executedSql);
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
        $pdo->expects($this->once())
            ->method('exec')
            ->willThrowException(new \PDOException('Syntax error in schema'));

        $migrator = new DatabaseMigrator($pdo);

        $this->expectException(\PDOException::class);

        $migrator->migrate();
    }
}
