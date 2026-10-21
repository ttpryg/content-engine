<?php

declare(strict_types=1);

namespace Ttpryg\ContentEngine\Application\Service;

use Ttpryg\ContentEngine\Contracts\SlugGeneratorInterface;
use Ttpryg\ContentEngine\Domain\Entity\Category;
use Ttpryg\ContentEngine\Domain\Exception\CategoryNotFoundException;
use Ttpryg\ContentEngine\Domain\Exception\CategorySlugAlreadyExistsException;
use Ttpryg\ContentEngine\Domain\Repository\CategoryRepositoryInterface;

class CategoryService
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly SlugGeneratorInterface $slugGenerator
    ) {}

    public function createCategory(string $name, string $type = 'category', ?string $slug = null): Category
    {
        $generatedSlug = $slug !== null && $slug !== ''
            ? $this->resolveRequestedSlug($slug)
            : $this->generateUniqueSlug($name);

        $category = new Category(
            name: $name,
            slug: $generatedSlug,
            type: $type
        );

        return $this->categoryRepository->save($category);
    }

    private function resolveRequestedSlug(string $slug): string
    {
        if ($this->categoryRepository->findBySlug($slug) instanceof Category) {
            throw CategorySlugAlreadyExistsException::bySlug($slug);
        }

        return $slug;
    }

    private function generateUniqueSlug(string $name): string
    {
        $baseSlug = $this->slugGenerator->generate($name);
        $slug = $baseSlug;
        $counter = 1;

        while ($this->categoryRepository->findBySlug($slug) instanceof Category) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    public function attachCategoryToContent(int|string $contentId, int|string $categoryId): bool
    {
        $category = $this->categoryRepository->findById($categoryId);
        if (! $category instanceof Category) {
            throw CategoryNotFoundException::byId($categoryId);
        }

        return $this->categoryRepository->attachToContent($contentId, $categoryId);
    }

    public function detachCategoryFromContent(int|string $contentId, int|string $categoryId): bool
    {
        return $this->categoryRepository->detachFromContent($contentId, $categoryId);
    }
}
