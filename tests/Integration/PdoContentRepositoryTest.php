<?php

namespace Ttpryg\ContentEngine\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Ttpryg\ContentEngine\Entities\Content;
use Ttpryg\ContentEngine\Repositories\PdoContentRepository;

class PdoContentRepositoryTest extends TestCase
{
    private PDO $pdo;

    private PdoContentRepository $pdoContentRepository;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create in-memory SQLite table
        $this->pdo->exec("
            CREATE TABLE contents (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tenant_type VARCHAR(50) NULL,
                tenant_id VARCHAR(100) NULL,
                author_id INT NULL,
                type VARCHAR(30) NOT NULL,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NULL,
                summary VARCHAR(500) NULL,
                body TEXT NULL,
                meta TEXT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'draft',
                sort_order INT NOT NULL DEFAULT 0,
                view_count INT NOT NULL DEFAULT 0,
                published_at DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                deleted_at DATETIME NULL DEFAULT NULL
            )
        ");

        $this->pdoContentRepository = new PdoContentRepository($this->pdo);
    }

    // POSITIVE CASE: Save and Find
    public function test_save_and_find_content(): void
    {
        $content = new Content(
            title: 'Welcome to CMS',
            type: 'page',
            slug: 'welcome-to-cms',
            summary: 'Short introduction',
            body: '<p>Full content page</p>',
            meta: ['author_name' => 'Admin'],
            status: 'published'
        );

        $saved = $this->pdoContentRepository->save($content);
        $this->assertNotNull($saved->getId());

        $found = $this->pdoContentRepository->findBySlug('welcome-to-cms', 'page');
        $this->assertNotNull($found);
        $this->assertEquals('Welcome to CMS', $found->getTitle());
        $this->assertEquals(['author_name' => 'Admin'], $found->getMeta());
    }

    // POSITIVE CASE: Find By Tenant (Multi-Store / Multi-Tenant Scoping)
    public function test_find_by_tenant(): void
    {
        $globalPost = new Content('Global Announcement', type: 'post', status: 'published');
        $storePost1 = new Content('Store 101 Promo', type: 'post', status: 'published', tenantType: 'store', tenantId: '101');
        $storePost2 = new Content('Store 202 Promo', type: 'post', status: 'published', tenantType: 'store', tenantId: '202');

        $this->pdoContentRepository->save($globalPost);
        $this->pdoContentRepository->save($storePost1);
        $this->pdoContentRepository->save($storePost2);

        $store101Posts = $this->pdoContentRepository->findByTenant('store', '101', 'post');
        $this->assertCount(1, $store101Posts);
        $this->assertEquals('Store 101 Promo', $store101Posts[0]->getTitle());
        $this->assertEquals('store', $store101Posts[0]->getTenantType());
        $this->assertEquals('101', $store101Posts[0]->getTenantId());
    }

    // POSITIVE CASE: Query All with Filter & Increments
    public function test_find_all_and_increment_views(): void
    {
        $c1 = new Content('Post 1', type: 'post', status: 'published');
        $c2 = new Content('Post 2', type: 'post', status: 'draft');
        $c3 = new Content('Page 1', type: 'page', status: 'published');

        $this->pdoContentRepository->save($c1);
        $this->pdoContentRepository->save($c2);
        $this->pdoContentRepository->save($c3);

        $publishedPosts = $this->pdoContentRepository->findAll(['type' => 'post', 'status' => 'published']);
        $this->assertCount(1, $publishedPosts);
        $this->assertEquals('Post 1', $publishedPosts[0]->getTitle());

        // Test Increment Views
        $this->pdoContentRepository->incrementViews($publishedPosts[0]->getId());
        $updated = $this->pdoContentRepository->findById($publishedPosts[0]->getId());
        $this->assertEquals(1, $updated->getViewCount());
    }

    // NEGATIVE/SOFT DELETE CASE
    public function test_soft_delete_content(): void
    {
        $content = new Content('ToDelete', type: 'post');
        $saved = $this->pdoContentRepository->save($content);
        $id = $saved->getId();

        $this->pdoContentRepository->delete($id, softDelete: true);

        $this->assertNull($this->pdoContentRepository->findById($id, includeTrashed: false));
        $this->assertNotNull($this->pdoContentRepository->findById($id, includeTrashed: true));
    }
}
