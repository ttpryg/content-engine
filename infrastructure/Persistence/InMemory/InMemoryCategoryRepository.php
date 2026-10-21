<?php

declare(strict_types=1);

namespace Ttpryg\ContentEngine\Infrastructure\Persistence\InMemory;

use Ttpryg\ContentEngine\Domain\Entity\Category;
use Ttpryg\ContentEngine\Domain\Repository\CategoryRepositoryInterface;

class InMemoryCategoryRepository implements CategoryRepositoryInterface
{
    /**
     * @var array<int|string, Category>
     */
    private array $categories = [];

    /**
     * @var array<string, array{content_id: int|string, category_id: int|string}>
     */
    private array $contentCategories = [];

    private int $autoIncrement = 1;

    public function findById(int|string $id): ?Category
    {
        return $this->categories[$id] ?? null;
    }

    public function findBySlug(string $slug): ?Category
    {
        foreach ($this->categories as $category) {
            if ($category->getSlug() === $slug) {
                return $category;
            }
        }

        return null;
    }

    public function findAll(string $type = 'category'): array
    {
        $categories = array_values(array_filter(
            $this->categories,
            fn (Category $category): bool => $category->getType() === $type
        ));

        usort(
            $categories,
            static fn (Category $a, Category $b): int => strcmp($a->getName(), $b->getName())
        );

        return $categories;
    }

    public function save(Category $category): Category
    {
        $id = $category->getId();

        if ($id === null) {
            $id = $this->autoIncrement;
            $category->setId($id);
        }

        if (is_numeric($id) && (int) $id >= $this->autoIncrement) {
            $this->autoIncrement = (int) $id + 1;
        }

        $this->categories[$id] = $category;

        return $category;
    }

    public function delete(int|string $id): bool
    {
        if (! array_key_exists($id, $this->categories)) {
            return false;
        }

        unset($this->categories[$id]);

        foreach ($this->contentCategories as $key => $pivot) {
            if ((string) $pivot['category_id'] === (string) $id) {
                unset($this->contentCategories[$key]);
            }
        }

        return true;
    }

    public function attachToContent(int|string $contentId, int|string $categoryId): bool
    {
        $this->contentCategories[$this->pivotKey($contentId, $categoryId)] = [
            'content_id' => $contentId,
            'category_id' => $categoryId,
        ];

        return true;
    }

    public function detachFromContent(int|string $contentId, int|string $categoryId): bool
    {
        unset($this->contentCategories[$this->pivotKey($contentId, $categoryId)]);

        return true;
    }

    public function getCategoriesByContent(int|string $contentId): array
    {
        $categories = [];

        foreach ($this->contentCategories as $pivot) {
            if ((string) $pivot['content_id'] !== (string) $contentId) {
                continue;
            }

            $category = $this->categories[$pivot['category_id']] ?? null;

            if ($category instanceof Category) {
                $categories[] = $category;
            }
        }

        usort(
            $categories,
            static fn (Category $a, Category $b): int => strcmp($a->getName(), $b->getName())
        );

        return $categories;
    }

    private function pivotKey(int|string $contentId, int|string $categoryId): string
    {
        return $contentId.':'.$categoryId;
    }
}
