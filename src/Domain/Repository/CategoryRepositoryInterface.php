<?php

declare(strict_types=1);

namespace Ttpryg\ContentEngine\Domain\Repository;

use Ttpryg\ContentEngine\Domain\Entity\Category;

interface CategoryRepositoryInterface
{
    public function findById(int|string $id): ?Category;

    public function findBySlug(string $slug): ?Category;

    /** @return list<Category> */
    public function findAll(string $type = 'category'): array;

    public function save(Category $category): Category;

    public function delete(int|string $id): bool;

    public function attachToContent(int|string $contentId, int|string $categoryId): bool;

    public function detachFromContent(int|string $contentId, int|string $categoryId): bool;

    /** @return list<Category> */
    public function getCategoriesByContent(int|string $contentId): array;
}
