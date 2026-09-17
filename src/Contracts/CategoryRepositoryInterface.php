<?php

namespace Ttpryg\ContentEngine\Contracts;

use Ttpryg\ContentEngine\Entities\Category;

interface CategoryRepositoryInterface
{
    public function findById(int|string $id): ?Category;
    public function findBySlug(string $slug): ?Category;
    public function findAll(string $type = 'category'): array;
    public function save(Category $category): Category;
    public function delete(int|string $id): bool;
    public function attachToContent(int|string $contentId, int|string $categoryId): bool;
    public function detachFromContent(int|string $contentId, int|string $categoryId): bool;
    public function getCategoriesByContent(int|string $contentId): array;
}
