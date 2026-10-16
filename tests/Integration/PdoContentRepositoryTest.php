<?php

namespace Ttpryg\ContentEngine\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Ttpryg\ContentEngine\Entities\Content;
use Ttpryg\ContentEngine\Exceptions\InvalidOrderByException;
use Ttpryg\ContentEngine\Repositories\PdoContentRepository;
use Ttpryg\ContentEngine\Services\ContentService;

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
                deleted_at DATETIME NULL DEFAULT NULL,
                UNIQUE (tenant_type, tenant_id, type, slug)
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

    // SECURITY CASE: ORDER BY whitelist menolak kolom di luar whitelist
    public function test_find_all_throws_on_non_whitelisted_order_by_column(): void
    {
        $this->expectException(InvalidOrderByException::class);

        $this->pdoContentRepository->findAll(
            criteria: [],
            limit: 20,
            offset: 0,
            orderBy: ['id; DROP TABLE contents; --' => 'ASC']
        );
    }

    // SECURITY CASE: ORDER BY whitelist mengizinkan kolom yang terdaftar
    public function test_find_all_accepts_whitelisted_order_by_column(): void
    {
        $this->pdoContentRepository->save(new Content('B Title', type: 'post'));
        $this->pdoContentRepository->save(new Content('A Title', type: 'post'));

        $results = $this->pdoContentRepository->findAll(
            criteria: ['type' => 'post'],
            orderBy: ['title' => 'ASC']
        );

        $this->assertCount(2, $results);
        $this->assertEquals('A Title', $results[0]->getTitle());
    }

    // SECURITY CASE: Limit pagination di-clamp ke batas atas maksimum (DoS prevention)
    public function test_find_all_clamps_limit_to_maximum(): void
    {
        for ($i = 1; $i <= 105; $i++) {
            $this->pdoContentRepository->save(new Content("Post {$i}", type: 'post'));
        }

        $results = $this->pdoContentRepository->findAll(criteria: ['type' => 'post'], limit: 100000);

        $this->assertCount(100, $results);
    }

    // SECURITY CASE: Limit pagination di-clamp ke minimal 1
    public function test_find_all_clamps_limit_to_minimum_one(): void
    {
        $this->pdoContentRepository->save(new Content('Post 1', type: 'post'));
        $this->pdoContentRepository->save(new Content('Post 2', type: 'post'));

        $results = $this->pdoContentRepository->findAll(criteria: ['type' => 'post'], limit: 0);

        $this->assertCount(1, $results);
    }

    // INTEGRITY CASE: Slug item soft-deleted tidak dipakai ulang, dan restore() aman dari tabrakan unique key
    public function test_slug_collision_with_soft_deleted_content_is_prevented(): void
    {
        $contentService = new ContentService($this->pdoContentRepository);

        $original = $contentService->createContent('My Unique Post', type: 'post');
        $this->assertEquals('my-unique-post', $original->getSlug());

        // Soft delete konten asli; slug 'my-unique-post' masih terpakai di tabel (deleted_at terisi).
        $contentService->delete($original->getId());

        // Konten baru dengan judul sama harus mendapat slug baru, bukan menabrak slug item trashed.
        $recreated = $contentService->createContent('My Unique Post', type: 'post');
        $this->assertEquals('my-unique-post-1', $recreated->getSlug());

        // Restore konten asli: keduanya hidup berdampingan tanpa melanggar UNIQUE (tenant_type, tenant_id, type, slug).
        $this->assertTrue($this->pdoContentRepository->restore($original->getId()));
        $this->assertNotNull($this->pdoContentRepository->findBySlug('my-unique-post', 'post'));
        $this->assertNotNull($this->pdoContentRepository->findBySlug('my-unique-post-1', 'post'));
    }
}
