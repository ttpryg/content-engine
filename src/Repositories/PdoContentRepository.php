<?php

namespace Ttpryg\ContentEngine\Repositories;

use DateTimeImmutable;
use PDO;
use Ttpryg\ContentEngine\Contracts\ContentRepositoryInterface;
use Ttpryg\ContentEngine\Entities\Content;

class PdoContentRepository implements ContentRepositoryInterface
{
    public function __construct(private readonly PDO $pdo, private readonly string $table = 'contents') {}

    public function findById(int|string $id, bool $includeTrashed = false): ?Content
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id";
        if (! $includeTrashed) {
            $sql .= ' AND deleted_at IS NULL';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? $this->mapToEntity($data) : null;
    }

    public function findBySlug(string $slug, string $type = 'post', bool $includeTrashed = false, ?string $tenantType = null, int|string|null $tenantId = null): ?Content
    {
        $sql = "SELECT * FROM {$this->table} WHERE slug = :slug AND type = :type";
        $params = ['slug' => $slug, 'type' => $type];

        if (! $includeTrashed) {
            $sql .= ' AND deleted_at IS NULL';
        }

        if ($tenantType !== null) {
            $sql .= ' AND tenant_type = :tenant_type';
            $params['tenant_type'] = $tenantType;
        }

        if ($tenantId !== null) {
            $sql .= ' AND tenant_id = :tenant_id';
            $params['tenant_id'] = $tenantId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? $this->mapToEntity($data) : null;
    }

    public function findByTenant(?string $tenantType, int|string|null $tenantId, ?string $type = null, int $limit = 20, int $offset = 0): array
    {
        $criteria = [
            'tenant_type' => $tenantType,
            'tenant_id' => $tenantId,
        ];
        if ($type !== null) {
            $criteria['type'] = $type;
        }

        return $this->findAll($criteria, $limit, $offset);
    }

    public function findByAuthorId(int|string $authorId, ?string $type = null, int $limit = 20, int $offset = 0): array
    {
        $criteria = ['author_id' => $authorId];
        if ($type !== null) {
            $criteria['type'] = $type;
        }

        return $this->findAll($criteria, $limit, $offset);
    }

    public function findAll(array $criteria = [], int $limit = 20, int $offset = 0, array $orderBy = ['created_at' => 'DESC']): array
    {
        $where = ['deleted_at IS NULL'];
        $params = [];

        if (isset($criteria['type'])) {
            $where[] = 'type = :type';
            $params['type'] = $criteria['type'];
        }

        if (isset($criteria['status'])) {
            $where[] = 'status = :status';
            $params['status'] = $criteria['status'];
        }

        if (array_key_exists('tenant_type', $criteria)) {
            if ($criteria['tenant_type'] === null) {
                $where[] = 'tenant_type IS NULL';
            } else {
                $where[] = 'tenant_type = :tenant_type';
                $params['tenant_type'] = $criteria['tenant_type'];
            }
        }

        if (array_key_exists('tenant_id', $criteria)) {
            if ($criteria['tenant_id'] === null) {
                $where[] = 'tenant_id IS NULL';
            } else {
                $where[] = 'tenant_id = :tenant_id';
                $params['tenant_id'] = $criteria['tenant_id'];
            }
        }

        if (isset($criteria['author_id'])) {
            $where[] = 'author_id = :author_id';
            $params['author_id'] = $criteria['author_id'];
        }

        if (isset($criteria['search'])) {
            $where[] = '(title LIKE :search OR body LIKE :search)';
            $params['search'] = '%'.$criteria['search'].'%';
        }

        $whereSql = implode(' AND ', $where);

        $orderSqls = [];
        foreach ($orderBy as $field => $dir) {
            $direction = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';
            $orderSqls[] = "{$field} {$direction}";
        }
        $orderSql = implode(', ', $orderSqls);

        $sql = "SELECT * FROM {$this->table} WHERE {$whereSql} ORDER BY {$orderSql} LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = $this->mapToEntity($row);
        }

        return $results;
    }

    public function count(array $criteria = []): int
    {
        $where = ['deleted_at IS NULL'];
        $params = [];

        if (isset($criteria['type'])) {
            $where[] = 'type = :type';
            $params['type'] = $criteria['type'];
        }

        if (isset($criteria['status'])) {
            $where[] = 'status = :status';
            $params['status'] = $criteria['status'];
        }

        if (array_key_exists('tenant_type', $criteria)) {
            if ($criteria['tenant_type'] === null) {
                $where[] = 'tenant_type IS NULL';
            } else {
                $where[] = 'tenant_type = :tenant_type';
                $params['tenant_type'] = $criteria['tenant_type'];
            }
        }

        if (array_key_exists('tenant_id', $criteria)) {
            if ($criteria['tenant_id'] === null) {
                $where[] = 'tenant_id IS NULL';
            } else {
                $where[] = 'tenant_id = :tenant_id';
                $params['tenant_id'] = $criteria['tenant_id'];
            }
        }

        if (isset($criteria['author_id'])) {
            $where[] = 'author_id = :author_id';
            $params['author_id'] = $criteria['author_id'];
        }

        $whereSql = implode(' AND ', $where);
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE {$whereSql}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function save(Content $content): Content
    {
        $sql = "INSERT INTO {$this->table} 
                (tenant_type, tenant_id, author_id, type, title, slug, summary, body, meta, status, sort_order, view_count, published_at, created_at, updated_at) 
                VALUES (:tenant_type, :tenant_id, :author_id, :type, :title, :slug, :summary, :body, :meta, :status, :sort_order, :view_count, :published_at, :created_at, :updated_at)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'tenant_type' => $content->getTenantType(),
            'tenant_id' => $content->getTenantId(),
            'author_id' => $content->getAuthorId(),
            'type' => $content->getType(),
            'title' => $content->getTitle(),
            'slug' => $content->getSlug(),
            'summary' => $content->getSummary(),
            'body' => $content->getBody(),
            'meta' => json_encode($content->getMeta()),
            'status' => $content->getStatus(),
            'sort_order' => $content->getSortOrder(),
            'view_count' => $content->getViewCount(),
            'published_at' => $content->getPublishedAt()?->format('Y-m-d H:i:s'),
            'created_at' => $content->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updated_at' => $content->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ]);

        $id = $this->pdo->lastInsertId();
        $content->setId($id);

        return $content;
    }

    public function update(Content $content): bool
    {
        $sql = "UPDATE {$this->table} 
                SET tenant_type = :tenant_type,
                    tenant_id = :tenant_id,
                    author_id = :author_id,
                    type = :type, 
                    title = :title, 
                    slug = :slug, 
                    summary = :summary, 
                    body = :body, 
                    meta = :meta, 
                    status = :status, 
                    sort_order = :sort_order, 
                    view_count = :view_count, 
                    published_at = :published_at, 
                    updated_at = :updated_at 
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            'id' => $content->getId(),
            'tenant_type' => $content->getTenantType(),
            'tenant_id' => $content->getTenantId(),
            'author_id' => $content->getAuthorId(),
            'type' => $content->getType(),
            'title' => $content->getTitle(),
            'slug' => $content->getSlug(),
            'summary' => $content->getSummary(),
            'body' => $content->getBody(),
            'meta' => json_encode($content->getMeta()),
            'status' => $content->getStatus(),
            'sort_order' => $content->getSortOrder(),
            'view_count' => $content->getViewCount(),
            'published_at' => $content->getPublishedAt()?->format('Y-m-d H:i:s'),
            'updated_at' => (new DateTimeImmutable)->format('Y-m-d H:i:s'),
        ]);
    }

    public function delete(int|string $id, bool $softDelete = true): bool
    {
        if ($softDelete) {
            $sql = "UPDATE {$this->table} SET deleted_at = :deleted_at WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);

            return $stmt->execute([
                'id' => $id,
                'deleted_at' => (new DateTimeImmutable)->format('Y-m-d H:i:s'),
            ]);
        }

        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute(['id' => $id]);
    }

    public function restore(int|string $id): bool
    {
        $sql = "UPDATE {$this->table} SET deleted_at = NULL WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute(['id' => $id]);
    }

    public function incrementViews(int|string $id): bool
    {
        $sql = "UPDATE {$this->table} SET view_count = view_count + 1 WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute(['id' => $id]);
    }

    private function mapToEntity(array $data): Content
    {
        $meta = [];
        if (! empty($data['meta'])) {
            $decoded = json_decode($data['meta'], associative: true);
            if (is_array($decoded)) {
                $meta = $decoded;
            }
        }

        return new Content(
            title: $data['title'],
            type: $data['type'],
            slug: $data['slug'] ?? null,
            summary: $data['summary'] ?? null,
            body: $data['body'] ?? null,
            meta: $meta,
            status: $data['status'] ?? 'draft',
            sortOrder: (int) ($data['sort_order'] ?? 0),
            viewCount: (int) ($data['view_count'] ?? 0),
            authorId: $data['author_id'] ?? null,
            tenantType: $data['tenant_type'] ?? null,
            tenantId: $data['tenant_id'] ?? null,
            publishedAt: ! empty($data['published_at']) ? new DateTimeImmutable($data['published_at']) : null,
            id: $data['id'],
            createdAt: ! empty($data['created_at']) ? new DateTimeImmutable($data['created_at']) : null,
            updatedAt: ! empty($data['updated_at']) ? new DateTimeImmutable($data['updated_at']) : null,
            deletedAt: ! empty($data['deleted_at']) ? new DateTimeImmutable($data['deleted_at']) : null
        );
    }
}
