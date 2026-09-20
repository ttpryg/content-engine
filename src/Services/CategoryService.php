<?php

namespace Ttpryg\ContentEngine\Services;

use Ttpryg\ContentEngine\Contracts\CategoryRepositoryInterface;
use Ttpryg\ContentEngine\Contracts\SlugGeneratorInterface;
use Ttpryg\ContentEngine\Entities\Category;
use Ttpryg\ContentEngine\Exceptions\CategoryNotFoundException;
use Ttpryg\ContentEngine\Utilities\NativeSlugGenerator;

class CategoryService
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository,
        private ?SlugGeneratorInterface $slugGenerator = null
    ) {
        $this->slugGenerator = $slugGenerator ?? new NativeSlugGenerator;
    }

    public function createCategory(string $name, string $type = 'category', ?string $slug = null): Category
    {
        $generatedSlug = $slug ?: $this->slugGenerator->generate($name);

        $category = new Category(
            name: $name,
            slug: $generatedSlug,
            type: $type
        );

        return $this->categoryRepository->save($category);
    }

    public function attachCategoryToContent(int|string $contentId, int|string $categoryId): bool
    {
        $category = $this->categoryRepository->findById($categoryId);
        if (! $category instanceof \Ttpryg\ContentEngine\Entities\Category) {
            throw CategoryNotFoundException::byId($categoryId);
        }

        return $this->categoryRepository->attachToContent($contentId, $categoryId);
    }

    public function detachCategoryFromContent(int|string $contentId, int|string $categoryId): bool
    {
        return $this->categoryRepository->detachFromContent($contentId, $categoryId);
    }
}
