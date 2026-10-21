<?php

declare(strict_types=1);

namespace Ttpryg\ContentEngine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ttpryg\ContentEngine\Domain\Entity\Category;
use Ttpryg\ContentEngine\Domain\Entity\Content;
use Ttpryg\ContentEngine\Domain\Exception\InvalidOrderByException;
use Ttpryg\ContentEngine\Infrastructure\Persistence\InMemory\InMemoryCategoryRepository;
use Ttpryg\ContentEngine\Infrastructure\Persistence\InMemory\InMemoryContentRepository;

class InMemoryRepositoriesTest extends TestCase
{
    public function test_content_repository_saves_and_finds_content(): void
    {
        $repository = new InMemoryContentRepository;

        $global = $repository->save(new Content('Global', type: 'post', slug: 'global'));
        $store = $repository->save(new Content('Store', type: 'post', slug: 'store', tenantType: 'store', tenantId: '101'));

        $this->assertSame(1, $global->getId());
        $this->assertSame(2, $store->getId());
        $this->assertSame($global, $repository->findById(1));
        $this->assertSame($store, $repository->findById('2'));
        $this->assertSame($store, $repository->findBySlug('store', 'post', tenantType: 'store', tenantId: '101'));
        $this->assertNull($repository->findBySlug('store', 'post', tenantType: 'store', tenantId: '202'));
        $this->assertNull($repository->findById(999));
    }

    public function test_content_repository_filters_counts_and_searches(): void
    {
        $repository = new InMemoryContentRepository;

        $repository->save(new Content('Draft Post', type: 'post', status: 'draft', authorId: 7));
        $repository->save(new Content('Published Post', type: 'post', body: 'Hello world', status: 'published', authorId: 7, tenantType: 'store', tenantId: '101'));
        $repository->save(new Content('Published Page', type: 'page', status: 'published'));

        $this->assertCount(2, $repository->findAll(['type' => 'post']));
        $this->assertCount(1, $repository->findAll(['type' => 'post', 'status' => 'published']));
        $this->assertCount(1, $repository->findAll(['search' => 'hello']));
        $this->assertCount(1, $repository->findByTenant('store', '101', 'post'));
        $this->assertCount(2, $repository->findByAuthorId(7));
        $this->assertSame(2, $repository->count(['status' => 'published']));
        $this->assertCount(1, $repository->findAll([], limit: 0));
    }

    public function test_content_repository_orders_and_rejects_invalid_order_column(): void
    {
        $repository = new InMemoryContentRepository;

        $repository->save(new Content('B Title', type: 'post', slug: 'b'));
        $repository->save(new Content('A Title', type: 'post', slug: 'a'));

        $titles = array_map(
            static fn (Content $content): string => $content->getTitle(),
            $repository->findAll(['type' => 'post'], orderBy: ['title' => 'ASC'])
        );

        $this->assertSame(['A Title', 'B Title'], $titles);

        $this->expectException(InvalidOrderByException::class);
        $repository->findAll(orderBy: ['title; DROP TABLE contents; --' => 'ASC']);
    }

    public function test_content_repository_soft_deletes_restores_and_increments_views(): void
    {
        $repository = new InMemoryContentRepository;
        $content = $repository->save(new Content('Post', type: 'post', slug: 'post'));
        $id = $content->getId();

        $this->assertTrue($repository->delete($id));
        $this->assertNull($repository->findById($id));
        $this->assertSame($content, $repository->findById($id, includeTrashed: true));
        $this->assertSame($content, $repository->findBySlug('post', includeTrashed: true));

        $this->assertTrue($repository->restore($id));
        $this->assertSame($content, $repository->findById($id));

        $this->assertTrue($repository->incrementViews($id));
        $this->assertSame(1, $content->getViewCount());
        $this->assertFalse($repository->incrementViews(999));

        $this->assertTrue($repository->delete($id, softDelete: false));
        $this->assertNull($repository->findById($id, includeTrashed: true));
    }

    public function test_content_repository_update_requires_existing_content(): void
    {
        $repository = new InMemoryContentRepository;

        $this->assertFalse($repository->update(new Content('Missing', id: 404)));

        $content = $repository->save(new Content('Original', type: 'post'));
        $content->setTitle('Updated');

        $this->assertTrue($repository->update($content));
        $this->assertSame('Updated', $repository->findById($content->getId())?->getTitle());
    }

    public function test_category_repository_saves_finds_and_filters_by_type(): void
    {
        $repository = new InMemoryCategoryRepository;

        $technology = $repository->save(new Category('Technology', 'technology'));
        $news = $repository->save(new Category('News', 'news'));
        $repository->save(new Category('Beta', 'beta', type: 'tag'));

        $this->assertSame(1, $technology->getId());
        $this->assertSame($technology, $repository->findById(1));
        $this->assertSame($news, $repository->findBySlug('news'));
        $this->assertNull($repository->findById(999));

        $this->assertSame(
            ['News', 'Technology'],
            array_map(static fn (Category $category): string => $category->getName(), $repository->findAll('category'))
        );
        $this->assertCount(1, $repository->findAll('tag'));
    }

    public function test_category_repository_attaches_and_detaches_categories_idempotently(): void
    {
        $repository = new InMemoryCategoryRepository;

        $technology = $repository->save(new Category('Technology', 'technology'));
        $news = $repository->save(new Category('News', 'news'));

        $this->assertTrue($repository->attachToContent(10, $technology->getId()));
        $this->assertTrue($repository->attachToContent(10, $technology->getId()));
        $this->assertTrue($repository->attachToContent(10, $news->getId()));

        $this->assertSame(
            ['News', 'Technology'],
            array_map(static fn (Category $category): string => $category->getName(), $repository->getCategoriesByContent(10))
        );

        $this->assertTrue($repository->detachFromContent(10, $technology->getId()));
        $this->assertCount(1, $repository->getCategoriesByContent(10));
        $this->assertSame([], $repository->getCategoriesByContent(999));

        $this->assertTrue($repository->delete($news->getId()));
        $this->assertSame([], $repository->getCategoriesByContent(10));
    }
}
