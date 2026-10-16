<?php

namespace Ttpryg\ContentEngine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ttpryg\ContentEngine\Contracts\CategoryRepositoryInterface;
use Ttpryg\ContentEngine\Contracts\SlugGeneratorInterface;
use Ttpryg\ContentEngine\Entities\Category;
use Ttpryg\ContentEngine\Exceptions\CategoryNotFoundException;
use Ttpryg\ContentEngine\Services\CategoryService;

class CategoryServiceTest extends TestCase
{
    // POSITIVE CASE: Create Category dengan slug auto-generate dari nama
    public function test_create_category_generates_slug_from_name(): void
    {
        $repo = $this->createMock(CategoryRepositoryInterface::class);
        $slugGen = $this->createMock(SlugGeneratorInterface::class);

        $slugGen->method('generate')->with('My Category')->willReturn('my-category');

        $repo->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (Category $category): Category {
                $category->setId(1);

                return $category;
            });

        $categoryService = new CategoryService($repo, $slugGen);
        $category = $categoryService->createCategory('My Category');

        $this->assertEquals(1, $category->getId());
        $this->assertEquals('My Category', $category->getName());
        $this->assertEquals('my-category', $category->getSlug());
        $this->assertEquals('category', $category->getType());
    }

    // POSITIVE CASE: Create Category dengan slug & type eksplisit (slug generator tidak dipanggil)
    public function test_create_category_uses_explicit_slug_and_type(): void
    {
        $repo = $this->createMock(CategoryRepositoryInterface::class);
        $slugGen = $this->createMock(SlugGeneratorInterface::class);

        $slugGen->expects($this->never())->method('generate');

        $repo->method('save')->willReturnCallback(function (Category $category): Category {
            $category->setId(7);

            return $category;
        });

        $categoryService = new CategoryService($repo, $slugGen);
        $category = $categoryService->createCategory('News Tag', type: 'tag', slug: 'custom-news');

        $this->assertEquals(7, $category->getId());
        $this->assertEquals('custom-news', $category->getSlug());
        $this->assertEquals('tag', $category->getType());
    }

    // POSITIVE CASE: Attach kategori yang ada ke konten
    public function test_attach_category_to_content(): void
    {
        $repo = $this->createMock(CategoryRepositoryInterface::class);

        $repo->method('findById')->with(5)->willReturn(new Category(name: 'Tech', slug: 'tech'));
        $repo->expects($this->once())
            ->method('attachToContent')
            ->with(10, 5)
            ->willReturn(true);

        $categoryService = new CategoryService($repo);

        $this->assertTrue($categoryService->attachCategoryToContent(10, 5));
    }

    // NEGATIVE CASE: Attach kategori tidak ada melempar CategoryNotFoundException
    public function test_attach_category_fails_when_category_not_found(): void
    {
        $repo = $this->createMock(CategoryRepositoryInterface::class);

        $repo->method('findById')->with(999)->willReturn(null);
        $repo->expects($this->never())->method('attachToContent');

        $this->expectException(CategoryNotFoundException::class);

        $categoryService = new CategoryService($repo);
        $categoryService->attachCategoryToContent(10, 999);
    }

    // POSITIVE CASE: Detach kategori dari konten didelegasikan ke repository
    public function test_detach_category_from_content(): void
    {
        $repo = $this->createMock(CategoryRepositoryInterface::class);

        $repo->expects($this->once())
            ->method('detachFromContent')
            ->with(10, 5)
            ->willReturn(true);

        $categoryService = new CategoryService($repo);

        $this->assertTrue($categoryService->detachCategoryFromContent(10, 5));
    }
}
