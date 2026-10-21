<?php

declare(strict_types=1);

use Ttpryg\DatabaseMigrator\Migration;

/**
 * Create the baseline content tables and their lookup indexes.
 *
 * Table definitions are driver-aware so the same migration works on SQLite
 * (tests/embedded) and MySQL/MariaDB.
 */
return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private const INDEXES = [
        'idx_tenant_content' => 'tenant_type, tenant_id, type, status',
        'idx_author_content' => 'author_id, status',
        'idx_type_status_published' => 'type, status, published_at',
        'idx_slug_type' => 'slug, type',
        'idx_sort' => 'type, sort_order',
    ];

    public function up(PDO $pdo): void
    {
        $pdo->exec($this->contentsTable($pdo));
        $pdo->exec($this->categoriesTable($pdo));
        $pdo->exec($this->contentCategoryTable($pdo));

        foreach (self::INDEXES as $name => $columns) {
            if ($this->indexExists($pdo, 'contents', $name)) {
                continue;
            }

            $pdo->exec(sprintf('CREATE INDEX %s ON contents (%s)', $name, $columns));
        }
    }

    public function down(PDO $pdo): void
    {
        // Drop the pivot table first because it owns foreign keys to both
        // contents and categories.
        $pdo->exec('DROP TABLE IF EXISTS content_category');
        $pdo->exec('DROP TABLE IF EXISTS categories');
        $pdo->exec('DROP TABLE IF EXISTS contents');
    }

    private function contentsTable(PDO $pdo): string
    {
        if ($this->isMysql($pdo)) {
            return 'CREATE TABLE IF NOT EXISTS contents (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_type VARCHAR(50) NULL,
                tenant_id VARCHAR(100) NULL,
                author_id BIGINT UNSIGNED NULL,
                type VARCHAR(30) NOT NULL,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NULL,
                summary VARCHAR(500) NULL,
                body LONGTEXT NULL,
                meta JSON NULL,
                status ENUM(\'draft\', \'published\', \'archived\') NOT NULL DEFAULT \'draft\',
                sort_order INT NOT NULL DEFAULT 0,
                view_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
                published_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        }

        return 'CREATE TABLE IF NOT EXISTS contents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tenant_type VARCHAR(50) NULL,
            tenant_id VARCHAR(100) NULL,
            author_id INTEGER NULL,
            type VARCHAR(30) NOT NULL,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NULL,
            summary VARCHAR(500) NULL,
            body TEXT NULL,
            meta TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'draft\',
            sort_order INTEGER NOT NULL DEFAULT 0,
            view_count INTEGER NOT NULL DEFAULT 0,
            published_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL DEFAULT NULL
        )';
    }

    private function categoriesTable(PDO $pdo): string
    {
        $idColumn = $this->isMysql($pdo)
            ? 'id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY'
            : 'id INTEGER PRIMARY KEY AUTOINCREMENT';

        $createdAtColumn = $this->isMysql($pdo)
            ? 'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
            : 'created_at DATETIME DEFAULT CURRENT_TIMESTAMP';

        $suffix = $this->isMysql($pdo)
            ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            : '';

        return sprintf(
            'CREATE TABLE IF NOT EXISTS categories (
                %s,
                name VARCHAR(100) NOT NULL,
                slug VARCHAR(100) NOT NULL UNIQUE,
                type VARCHAR(30) NOT NULL DEFAULT \'category\',
                %s
            )%s',
            $idColumn,
            $createdAtColumn,
            $suffix
        );
    }

    private function contentCategoryTable(PDO $pdo): string
    {
        if ($this->isMysql($pdo)) {
            return 'CREATE TABLE IF NOT EXISTS content_category (
                content_id BIGINT UNSIGNED NOT NULL,
                category_id BIGINT UNSIGNED NOT NULL,
                PRIMARY KEY (content_id, category_id),
                CONSTRAINT fk_content_category_content FOREIGN KEY (content_id) REFERENCES contents(id) ON DELETE CASCADE,
                CONSTRAINT fk_content_category_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        }

        return 'CREATE TABLE IF NOT EXISTS content_category (
            content_id INTEGER NOT NULL,
            category_id INTEGER NOT NULL,
            PRIMARY KEY (content_id, category_id),
            FOREIGN KEY (content_id) REFERENCES contents(id) ON DELETE CASCADE,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
        )';
    }

    private function indexExists(PDO $pdo, string $table, string $index): bool
    {
        if ($this->isMysql($pdo)) {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.statistics
                 WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :index'
            );
            $stmt->execute(['table' => $table, 'index' => $index]);

            return (int) $stmt->fetchColumn() > 0;
        }

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM sqlite_master WHERE type = 'index' AND name = :index AND tbl_name = :table"
            );
            $stmt->execute(['index' => $index, 'table' => $table]);

            return (int) $stmt->fetchColumn() > 0;
        }

        return false;
    }

    private function isMysql(PDO $pdo): bool
    {
        return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    }
};
