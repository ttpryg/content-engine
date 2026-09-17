<?php

namespace Ttpryg\ContentEngine\Repositories;

use DateTimeImmutable;
use PDO;
use Ttpryg\ContentEngine\Contracts\CategoryRepositoryInterface;
use Ttpryg\ContentEngine\Entities\Category;

class PdoCategoryRepository implements CategoryRepositoryInterface
{
    private PDO $pdo;
    private string $table;
    private string $pivotTable;

    public function __construct(PDO $pdo, string $table = 'categories', string $pivotTable = 'content_category')
    {
        $this->pdo = $pdo;
        $this->table = $table;
        $this->pivotTable = $pivotTable;
    }

    public function findById(int|string $id): ?Category
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ? $this->mapToEntity($data) : null;
    }

    public function findBySlug(string $slug): ?Category
    {
        $sql = "SELECT * FROM {$this->table} WHERE slug = :slug";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['slug' => $slug]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ? $this->mapToEntity($data) : null;
    }

    public function findAll(string $type = 'category'): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE type = :type ORDER BY name ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['type' => $type]);

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = $this->mapToEntity($row);
        }

        return $results;
    }

    public function save(Category $category): Category
    {
        $sql = "INSERT INTO {$this->table} (name, slug, type, created_at) VALUES (:name, :slug, :type, :created_at)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'name' => $category->getName(),
            'slug' => $category->getSlug(),
            'type' => $category->getType(),
            'created_at' => $category->getCreatedAt()?->format('Y-m-d H:i:s'),
        ]);

        $id = $this->pdo->lastInsertId();
        $category->setId($id);

        return $category;
    }

    public function delete(int|string $id): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    public function attachToContent(int|string $contentId, int|string $categoryId): bool
    {
        $sql = "INSERT IGNORE INTO {$this->pivotTable} (content_id, category_id) VALUES (:content_id, :category_id)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'content_id' => $contentId,
            'category_id' => $categoryId,
        ]);
    }

    public function detachFromContent(int|string $contentId, int|string $categoryId): bool
    {
        $sql = "DELETE FROM {$this->pivotTable} WHERE content_id = :content_id AND category_id = :category_id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'content_id' => $contentId,
            'category_id' => $categoryId,
        ]);
    }

    public function getCategoriesByContent(int|string $contentId): array
    {
        $sql = "SELECT c.* FROM {$this->table} c
                INNER JOIN {$this->pivotTable} cc ON c.id = cc.category_id
                WHERE cc.content_id = :content_id
                ORDER BY c.name ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['content_id' => $contentId]);

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = $this->mapToEntity($row);
        }

        return $results;
    }

    private function mapToEntity(array $data): Category
    {
        return new Category(
            name: $data['name'],
            slug: $data['slug'],
            type: $data['type'] ?? 'category',
            id: $data['id'],
            createdAt: !empty($data['created_at']) ? new DateTimeImmutable($data['created_at']) : null
        );
    }
}
