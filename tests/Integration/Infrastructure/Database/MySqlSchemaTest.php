<?php

declare(strict_types=1);

namespace Ttpryg\ContentEngine\Tests\Integration\Infrastructure\Database;

use PDO;
use PHPUnit\Framework\TestCase;
use Ttpryg\ContentEngine\Domain\Entity\Content;
use Ttpryg\ContentEngine\Domain\Exception\ContentSlugAlreadyExistsException;
use Ttpryg\ContentEngine\Infrastructure\Database\DatabaseMigrator;
use Ttpryg\ContentEngine\Infrastructure\Persistence\Pdo\PdoContentRepository;

class MySqlSchemaTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $dsn = getenv('CONTENT_ENGINE_MYSQL_DSN');
        if (! is_string($dsn) || $dsn === '') {
            $this->markTestSkipped('CONTENT_ENGINE_MYSQL_DSN is not configured.');
        }

        if (! extension_loaded('pdo_mysql')) {
            $this->markTestSkipped('pdo_mysql is not available.');
        }

        $this->pdo = new PDO(
            $dsn,
            getenv('CONTENT_ENGINE_MYSQL_USER') ?: 'root',
            getenv('CONTENT_ENGINE_MYSQL_PASSWORD') ?: ''
        );
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->resetSchema();
        (new DatabaseMigrator($this->pdo))->migrate();
    }

    public function test_migrations_enforce_global_slug_uniqueness_on_mysql(): void
    {
        $repository = new PdoContentRepository($this->pdo);

        $repository->save(new Content('Global', type: 'post', slug: 'promo'));
        $repository->save(new Content('Store 101', type: 'post', slug: 'promo', tenantType: 'store', tenantId: '101'));

        $this->assertSame('Global', $repository->findBySlug('promo', 'post')?->getTitle());
        $this->assertSame('Store 101', $repository->findBySlug('promo', 'post', tenantType: 'store', tenantId: '101')?->getTitle());

        $this->expectException(ContentSlugAlreadyExistsException::class);
        $repository->save(new Content('Duplicate Global', type: 'post', slug: 'promo'));
    }

    private function resetSchema(): void
    {
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach (['content_category', 'categories', 'contents', 'content_engine_migrations'] as $table) {
            $this->pdo->exec("DROP TABLE IF EXISTS {$table}");
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }
}
