<?php

declare(strict_types=1);

namespace Ttpryg\ContentEngine\Infrastructure\Persistence\Pdo;

use DateTimeImmutable;
use PDO;
use PDOException;
use Ttpryg\ContentEngine\Domain\Entity\Category;
use Ttpryg\ContentEngine\Domain\Exception\CategorySlugAlreadyExistsException;
use Ttpryg\ContentEngine\Domain\Repository\CategoryRepositoryInterface;

class PdoCategoryRepository implements CategoryRepositoryInterface
{
    public function __construct(private readonly PDO $pdo, private readonly string $table = 'categories', private readonly string $pivotTable = 'content_category') {}

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

    /** @return list<Category> */
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

        try {
            $stmt->execute([
                'name' => $category->getName(),
                'slug' => $category->getSlug(),
                'type' => $category->getType(),
                'created_at' => $category->getCreatedAt()?->format('Y-m-d H:i:s'),
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000'
                && (stripos($e->getMessage(), 'unique') !== false || stripos($e->getMessage(), 'duplicate') !== false)) {
                throw CategorySlugAlreadyExistsException::bySlug($category->getSlug());
            }

            throw $e;
        }

        $id = $this->pdo->lastInsertId();
        if ($id === false) {
            throw new \RuntimeException('Failed to retrieve the inserted category ID.');
        }
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
        // Portabel antar driver (MySQL & SQLite): skip jika pasangan sudah ada.
        // Menggantikan INSERT IGNORE yang hanya didukung MySQL.
        $sql = "INSERT INTO {$this->pivotTable} (content_id, category_id)
                SELECT :new_content_id, :new_category_id
                WHERE NOT EXISTS (
                    SELECT 1 FROM {$this->pivotTable}
                    WHERE content_id = :existing_content_id AND category_id = :existing_category_id
                )";
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            'new_content_id' => $contentId,
            'new_category_id' => $categoryId,
            'existing_content_id' => $contentId,
            'existing_category_id' => $categoryId,
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

    /** @return list<Category> */
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

    /** @param array<string, mixed> $data */
    private function mapToEntity(array $data): Category
    {
        $createdAt = $data['created_at'] ?? null;

        return new Category(
            name: (string) $data['name'],
            slug: (string) $data['slug'],
            type: (string) ($data['type'] ?? 'category'),
            id: is_int($data['id'] ?? null) || is_string($data['id'] ?? null) ? $data['id'] : null,
            createdAt: is_string($createdAt) && $createdAt !== '' ? new DateTimeImmutable($createdAt) : null
        );
    }
}
