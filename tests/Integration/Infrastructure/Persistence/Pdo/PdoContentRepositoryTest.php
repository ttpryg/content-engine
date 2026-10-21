<?php

namespace Ttpryg\ContentEngine\Tests\Integration\Infrastructure\Persistence\Pdo;

use PDO;
use PHPUnit\Framework\TestCase;
use Ttpryg\ContentEngine\Application\DTO\CreateContentRequest;
use Ttpryg\ContentEngine\Application\Service\ContentService;
use Ttpryg\ContentEngine\Domain\Entity\Content;
use Ttpryg\ContentEngine\Domain\Exception\InvalidOrderByException;
use Ttpryg\ContentEngine\Infrastructure\Persistence\Pdo\PdoContentRepository;
use Ttpryg\ContentEngine\Utilities\NativeSlugGenerator;

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

    // SECURITY CASE: findBySlug tidak membocorkan konten tenant lain saat scope global
    public function test_find_by_slug_scopes_to_the_exact_tenant(): void
    {
        $global = $this->pdoContentRepository->save(new Content('Global Promo', type: 'post', slug: 'promo'));
        $store = $this->pdoContentRepository->save(new Content('Store Promo', type: 'post', slug: 'promo', tenantType: 'store', tenantId: '101'));

        $this->assertEquals($this->idOf($global), $this->idOf($this->pdoContentRepository->findBySlug('promo', 'post') ?? throw new \RuntimeException('Expected global content.')));
        $this->assertEquals($this->idOf($store), $this->idOf($this->pdoContentRepository->findBySlug('promo', 'post', tenantType: 'store', tenantId: '101') ?? throw new \RuntimeException('Expected store content.')));
        $this->assertNull($this->pdoContentRepository->findBySlug('promo', 'post', tenantType: 'store', tenantId: '202'));
        $this->assertNull($this->pdoContentRepository->findBySlug('promo', 'post', tenantType: 'company', tenantId: '101'));
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
        $this->pdoContentRepository->incrementViews($this->idOf($publishedPosts[0]));
        $updated = $this->pdoContentRepository->findById($this->idOf($publishedPosts[0]))
            ?? throw new \RuntimeException('Expected updated content.');
        $this->assertEquals(1, $updated->getViewCount());
    }

    public function test_rejects_unsafe_table_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PdoContentRepository($this->pdo, 'contents; DROP TABLE contents');
    }

    public function test_search_treats_like_wildcards_as_literals(): void
    {
        $this->pdoContentRepository->save(new Content('Diskon 100% Spesial', type: 'post', slug: 'diskon'));

        $this->assertCount(1, $this->pdoContentRepository->findAll(['search' => '100%']));
        $this->assertCount(0, $this->pdoContentRepository->findAll(['search' => '100_']));
        $this->assertSame(1, $this->pdoContentRepository->count(['search' => '100%']));
    }

    // NEGATIVE/SOFT DELETE CASE
    public function test_soft_delete_content(): void
    {
        $content = new Content('ToDelete', type: 'post');
        $saved = $this->pdoContentRepository->save($content);
        $id = $this->idOf($saved);

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
        $contentService = new ContentService($this->pdoContentRepository, new NativeSlugGenerator);

        $original = $contentService->createContent(new CreateContentRequest(title: 'My Unique Post', type: 'post'));
        $this->assertEquals('my-unique-post', $original->getSlug());

        // Soft delete konten asli; slug 'my-unique-post' masih terpakai di tabel (deleted_at terisi).
        $contentService->delete($this->idOf($original));

        // Konten baru dengan judul sama harus mendapat slug baru, bukan menabrak slug item trashed.
        $recreated = $contentService->createContent(new CreateContentRequest(title: 'My Unique Post', type: 'post'));
        $this->assertEquals('my-unique-post-1', $recreated->getSlug());

        // Restore konten asli: keduanya hidup berdampingan tanpa melanggar UNIQUE (tenant_type, tenant_id, type, slug).
        $this->assertTrue($this->pdoContentRepository->restore($this->idOf($original)));
        $this->assertNotNull($this->pdoContentRepository->findBySlug('my-unique-post', 'post'));
        $this->assertNotNull($this->pdoContentRepository->findBySlug('my-unique-post-1', 'post'));
    }

    // INTEGRITY CASE: Soft-deleted content tersembunyi dari semua query baca, kembali setelah restore()
    public function test_soft_delete_hides_content_from_queries_until_restored(): void
    {
        $kept = $this->pdoContentRepository->save(new Content('Kept Post', type: 'post', slug: 'kept-post'));
        $trashed = $this->pdoContentRepository->save(new Content('Trashed Post', type: 'post', slug: 'trashed-post'));

        // Soft delete satu konten
        $this->pdoContentRepository->delete($this->idOf($trashed), softDelete: true);

        // Semua query baca harus mengabaikan konten ter-soft-delete
        $this->assertCount(1, $this->pdoContentRepository->findAll(criteria: ['type' => 'post']));
        $this->assertEquals(1, $this->pdoContentRepository->count(['type' => 'post']));
        $this->assertNull($this->pdoContentRepository->findBySlug('trashed-post', 'post'));
        $this->assertNull($this->pdoContentRepository->findById($this->idOf($trashed)));
        $this->assertEquals(
            ['kept-post'],
            array_map(fn (\Ttpryg\ContentEngine\Domain\Entity\Content $c): ?string => $c->getSlug(), $this->pdoContentRepository->findAll(criteria: ['type' => 'post']))
        );

        // Konten yang tersisa tetap terbaca normal
        $this->assertNotNull($this->pdoContentRepository->findById($this->idOf($kept)));

        // Restore mengembalikan konten ke semua query baca
        $this->assertTrue($this->pdoContentRepository->restore($this->idOf($trashed)));

        $this->assertCount(2, $this->pdoContentRepository->findAll(criteria: ['type' => 'post']));
        $this->assertEquals(2, $this->pdoContentRepository->count(['type' => 'post']));
        $this->assertNotNull($this->pdoContentRepository->findBySlug('trashed-post', 'post'));
        $this->assertNotNull($this->pdoContentRepository->findById($this->idOf($trashed)));
    }

    // EDGE CASE: Hard delete (softDelete=false) menghapus baris permanen, restore tidak berpengaruh
    public function test_hard_deleted_content_cannot_be_restored(): void
    {
        $content = $this->pdoContentRepository->save(new Content('Hard Deleted', type: 'post', slug: 'hard-deleted'));
        $id = $this->idOf($content);

        $this->pdoContentRepository->delete($id, softDelete: false);

        $this->assertNull($this->pdoContentRepository->findById($id, includeTrashed: true));

        // restore() pada baris yang sudah tidak ada: UPDATE tidak mengenai baris apa pun.
        // Pdo tidak error, tapi konten tetap tidak ditemukan.
        $this->pdoContentRepository->restore($id);
        $this->assertNull($this->pdoContentRepository->findById($id, includeTrashed: true));
    }

    private function idOf(Content $content): int|string
    {
        $id = $content->getId();
        if ($id === null) {
            throw new \RuntimeException('Expected a persisted content ID.');
        }

        return $id;
    }
}
