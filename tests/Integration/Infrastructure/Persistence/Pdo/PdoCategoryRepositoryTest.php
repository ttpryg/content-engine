<?php

declare(strict_types=1);

namespace Ttpryg\ContentEngine\Tests\Integration\Infrastructure\Persistence\Pdo;

use PDO;
use PHPUnit\Framework\TestCase;
use Ttpryg\ContentEngine\Domain\Entity\Category;
use Ttpryg\ContentEngine\Infrastructure\Persistence\Pdo\PdoCategoryRepository;

class PdoCategoryRepositoryTest extends TestCase
{
    private PDO $pdo;

    private PdoCategoryRepository $pdoCategoryRepository;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create in-memory SQLite tables (categories + pivot many-to-many)
        $this->pdo->exec("
            CREATE TABLE categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) NOT NULL,
                slug VARCHAR(100) NOT NULL UNIQUE,
                type VARCHAR(30) NOT NULL DEFAULT 'category',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $this->pdo->exec('
            CREATE TABLE content_category (
                content_id INTEGER NOT NULL,
                category_id INTEGER NOT NULL,
                PRIMARY KEY (content_id, category_id)
            )
        ');

        $this->pdoCategoryRepository = new PdoCategoryRepository($this->pdo);
    }

    // POSITIVE CASE: Save and Find By Id
    public function test_save_and_find_category_by_id(): void
    {
        $category = new Category(name: 'Technology', slug: 'technology');

        $saved = $this->pdoCategoryRepository->save($category);
        $found = $this->pdoCategoryRepository->findById($this->idOf($saved));
        $this->assertNotNull($found);
        $this->assertEquals('Technology', $found->getName());
        $this->assertEquals('technology', $found->getSlug());
        $this->assertEquals('category', $found->getType());
        $this->assertNotNull($found->getCreatedAt());
    }

    // NEGATIVE CASE: Find By Id / Find By Slug dengan data tidak ada menghasilkan null
    public function test_find_by_missing_id_and_slug_returns_null(): void
    {
        $this->assertNull($this->pdoCategoryRepository->findById(999));
        $this->assertNull($this->pdoCategoryRepository->findBySlug('does-not-exist'));
    }

    // POSITIVE CASE: Find By Slug
    public function test_find_category_by_slug(): void
    {
        $this->pdoCategoryRepository->save(new Category(name: 'Lifestyle', slug: 'lifestyle'));

        $found = $this->pdoCategoryRepository->findBySlug('lifestyle');
        $this->assertNotNull($found);
        $this->assertEquals('Lifestyle', $found->getName());
    }

    // POSITIVE CASE: Find All difilter per type dan diurutkan berdasarkan nama ASC
    public function test_find_all_categories_by_type_ordered_by_name(): void
    {
        $this->pdoCategoryRepository->save(new Category(name: 'Zette', slug: 'zette'));
        $this->pdoCategoryRepository->save(new Category(name: 'Alpha', slug: 'alpha'));
        $this->pdoCategoryRepository->save(new Category(name: 'Beta', slug: 'beta', type: 'tag'));

        $categories = $this->pdoCategoryRepository->findAll('category');
        $this->assertCount(2, $categories);
        $this->assertEquals('Alpha', $categories[0]->getName());
        $this->assertEquals('Zette', $categories[1]->getName());

        $tags = $this->pdoCategoryRepository->findAll('tag');
        $this->assertCount(1, $tags);
        $this->assertEquals('Beta', $tags[0]->getName());
    }

    // POSITIVE CASE: Operasi pivot many-to-many attach & getCategoriesByContent
    public function test_attach_category_to_content_and_fetch_back(): void
    {
        $tech = $this->pdoCategoryRepository->save(new Category(name: 'Technology', slug: 'technology'));
        $news = $this->pdoCategoryRepository->save(new Category(name: 'News', slug: 'news'));

        $this->assertTrue($this->pdoCategoryRepository->attachToContent(1, $this->idOf($tech)));
        $this->assertTrue($this->pdoCategoryRepository->attachToContent(1, $this->idOf($news)));

        $categories = $this->pdoCategoryRepository->getCategoriesByContent(1);
        $this->assertCount(2, $categories);
        // Diurutkan berdasarkan nama ASC
        $this->assertEquals('News', $categories[0]->getName());
        $this->assertEquals('Technology', $categories[1]->getName());
    }

    // EDGE CASE: Attach duplikat tidak menggandakan baris pivot (idempotent)
    public function test_attach_duplicate_category_is_idempotent(): void
    {
        $tech = $this->pdoCategoryRepository->save(new Category(name: 'Technology', slug: 'technology'));

        $this->assertTrue($this->pdoCategoryRepository->attachToContent(1, $this->idOf($tech)));
        $this->assertTrue($this->pdoCategoryRepository->attachToContent(1, $this->idOf($tech)));

        $this->assertCount(1, $this->pdoCategoryRepository->getCategoriesByContent(1));
    }

    // POSITIVE CASE: Detach kategori dari konten
    public function test_detach_category_from_content(): void
    {
        $tech = $this->pdoCategoryRepository->save(new Category(name: 'Technology', slug: 'technology'));
        $news = $this->pdoCategoryRepository->save(new Category(name: 'News', slug: 'news'));

        $this->pdoCategoryRepository->attachToContent(1, $this->idOf($tech));
        $this->pdoCategoryRepository->attachToContent(1, $this->idOf($news));

        $this->assertTrue($this->pdoCategoryRepository->detachFromContent(1, $this->idOf($tech)));

        $categories = $this->pdoCategoryRepository->getCategoriesByContent(1);
        $this->assertCount(1, $categories);
        $this->assertEquals('News', $categories[0]->getName());
    }

    // NEGATIVE CASE: Konten tanpa kategori menghasilkan array kosong
    public function test_get_categories_for_content_without_categories_returns_empty_array(): void
    {
        $this->assertEquals([], $this->pdoCategoryRepository->getCategoriesByContent(42));
    }

    private function idOf(Category $category): int|string
    {
        $id = $category->getId();
        if ($id === null) {
            throw new \RuntimeException('Expected a persisted category ID.');
        }

        return $id;
    }
}
