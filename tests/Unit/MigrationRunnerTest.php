<?php

namespace Ttpryg\ContentEngine\Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;
use Ttpryg\ContentEngine\Database\MigrationRunner;

class MigrationRunnerTest extends TestCase
{
    private PDO $pdo;

    private string $migrationsPath;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->migrationsPath = sys_get_temp_dir().'/content_engine_migrations_'.bin2hex(random_bytes(4));
        mkdir($this->migrationsPath, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->migrationsPath.'/*.sql') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($this->migrationsPath)) {
            rmdir($this->migrationsPath);
        }
    }

    private function writeMigration(string $name, string $sql): void
    {
        file_put_contents($this->migrationsPath.'/'.$name.'.sql', $sql);
    }

    private function tableExists(string $table): bool
    {
        try {
            $stmt = $this->pdo->query("SELECT 1 FROM {$table} LIMIT 1");
        } catch (\Throwable) {
            return false;
        }

        return $stmt !== false;
    }

    // POSITIVE CASE: Migrasi berjalan sesuai urutan nama dan tercatat di tracker
    public function test_migrate_runs_pending_migrations_in_order_and_records_them(): void
    {
        // Ditulis terbalat untuk membuktikan urutan berdasarkan nama, bukan waktu file.
        $this->writeMigration('002_add_views_to_posts', 'ALTER TABLE posts ADD COLUMN views INTEGER DEFAULT 0;');
        $this->writeMigration('001_create_posts', 'CREATE TABLE posts (id INTEGER PRIMARY KEY);');

        $runner = new MigrationRunner($this->pdo, $this->migrationsPath);
        $executed = $runner->migrate();

        $this->assertSame(['001_create_posts', '002_add_views_to_posts'], $executed);
        $this->assertSame(['001_create_posts', '002_add_views_to_posts'], $runner->appliedMigrations());
        $this->assertTrue($this->tableExists('posts'));

        // Kolom hasil migrasi 002 benar-benar diterapkan.
        $this->pdo->exec('INSERT INTO posts (id, views) VALUES (1, 5)');
        $this->assertEquals(5, (int) $this->pdo->query('SELECT views FROM posts WHERE id = 1')->fetchColumn());
    }

    // POSITIVE CASE: Run kedua tidak mengeksekusi ulang migrasi yang sudah tercatat (idempotent)
    public function test_migrate_is_idempotent_across_runs(): void
    {
        $this->writeMigration('001_create_posts', 'CREATE TABLE posts (id INTEGER PRIMARY KEY);');

        $runner = new MigrationRunner($this->pdo, $this->migrationsPath);

        $this->assertSame(['001_create_posts'], $runner->migrate());
        $this->assertSame([], $runner->migrate());
        $this->assertSame(['001_create_posts'], $runner->appliedMigrations());
    }

    // NEGATIVE CASE: Migrasi gagal TIDAK dicatat, migrasi sebelumnya tetap tercatat, run berikutnya resume
    public function test_failed_migration_is_not_recorded_and_run_resumes(): void
    {
        $this->writeMigration('001_create_posts', 'CREATE TABLE posts (id INTEGER PRIMARY KEY);');
        $this->writeMigration('002_broken', 'INSERT INTO table_that_does_not_exist VALUES (1);');

        $runner = new MigrationRunner($this->pdo, $this->migrationsPath);

        try {
            $runner->migrate();
            $this->fail('Migrasi rusak seharusnya melempar exception.');
        } catch (\PDOException) {
            // Diharapkan: SQL invalid.
        }

        // 001 sukses & tercatat; 002 gagal & tidak dicatat.
        $this->assertSame(['001_create_posts'], $runner->appliedMigrations());
        $this->assertTrue($this->tableExists('posts'));

        // Perbaiki migrasi 002 → run berikutnya hanya menjalankan 002 (resume, bukan ulang dari awal).
        $this->writeMigration('002_broken', 'CREATE TABLE fixed_table (id INTEGER PRIMARY KEY);');
        $this->assertSame(['002_broken'], $runner->migrate());
        $this->assertTrue($this->tableExists('fixed_table'));
        $this->assertSame(['001_create_posts', '002_broken'], $runner->appliedMigrations());
    }

    // POSITIVE CASE: markAllAsMigrated mencatat baseline TANPA mengeksekusi SQL migrasi
    public function test_mark_all_as_migrated_records_baseline_without_executing(): void
    {
        $this->writeMigration('001_create_posts', 'CREATE TABLE posts (id INTEGER PRIMARY KEY);');
        $this->writeMigration('002_create_tags', 'CREATE TABLE tags (id INTEGER PRIMARY KEY);');

        $runner = new MigrationRunner($this->pdo, $this->migrationsPath);

        $recorded = $runner->markAllAsMigrated();

        $this->assertSame(['001_create_posts', '002_create_tags'], $recorded);
        $this->assertSame(['001_create_posts', '002_create_tags'], $runner->appliedMigrations());
        // Tabel migrasi TIDAK dibuat — membuktikan SQL tidak dieksekusi.
        $this->assertFalse($this->tableExists('posts'));
        $this->assertFalse($this->tableExists('tags'));

        // Setelah baseline, migrate() tidak menjalankan apa-apa.
        $this->assertSame([], $runner->migrate());
    }

    // EDGE CASE: Direktori migrasi tidak ada → runner tidak error dan tidak menjalankan apa-apa
    public function test_migrate_with_missing_directory_returns_empty(): void
    {
        $runner = new MigrationRunner($this->pdo, $this->migrationsPath.'/does_not_exist');

        $this->assertSame([], $runner->migrate());
        $this->assertSame([], $runner->appliedMigrations());
    }

    // INTEGRITY CASE: File migrasi nyata di database/migrations ditemukan oleh path default runner
    public function test_default_migrations_path_discovers_bundled_migrations(): void
    {
        $runner = new MigrationRunner($this->pdo);

        $recorded = $runner->markAllAsMigrated();

        $this->assertContains('001_add_unique_tenant_slug', $recorded);
    }
}
