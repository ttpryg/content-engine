<?php

declare(strict_types=1);

namespace Ttpryg\ContentEngine\Infrastructure\Persistence\InMemory;

use DateTimeImmutable;
use DateTimeInterface;
use Ttpryg\ContentEngine\Domain\Entity\Content;
use Ttpryg\ContentEngine\Domain\Exception\InvalidOrderByException;
use Ttpryg\ContentEngine\Domain\Repository\ContentRepositoryInterface;

class InMemoryContentRepository implements ContentRepositoryInterface
{
    /**
     * @var array<int|string, Content>
     */
    private array $contents = [];

    private int $autoIncrement = 1;

    /**
     * @var array<int, string>
     */
    private const ALLOWED_ORDER_BY_COLUMNS = ['id', 'title', 'created_at', 'published_at', 'sort_order', 'view_count'];

    private const MAX_LIMIT = 100;

    public function findById(int|string $id, bool $includeTrashed = false): ?Content
    {
        $content = $this->contents[$id] ?? null;

        if (! $content instanceof Content) {
            return null;
        }

        if (! $includeTrashed && $content->getDeletedAt() instanceof DateTimeInterface) {
            return null;
        }

        return $content;
    }

    public function findBySlug(string $slug, string $type = 'post', bool $includeTrashed = false, ?string $tenantType = null, int|string|null $tenantId = null): ?Content
    {
        foreach ($this->contents as $content) {
            if ($content->getSlug() !== $slug || $content->getType() !== $type) {
                continue;
            }

            if (! $includeTrashed && $content->getDeletedAt() instanceof DateTimeInterface) {
                continue;
            }

            if ($tenantType !== null && $content->getTenantType() !== $tenantType) {
                continue;
            }

            if ($tenantId !== null && (string) $content->getTenantId() !== (string) $tenantId) {
                continue;
            }

            return $content;
        }

        return null;
    }

    /** @return list<Content> */
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

    /** @return list<Content> */
    public function findByAuthorId(int|string $authorId, ?string $type = null, int $limit = 20, int $offset = 0): array
    {
        $criteria = ['author_id' => $authorId];

        if ($type !== null) {
            $criteria['type'] = $type;
        }

        return $this->findAll($criteria, $limit, $offset);
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @param  array<string, 'ASC'|'DESC'>  $orderBy
     * @return list<Content>
     */
    public function findAll(array $criteria = [], int $limit = 20, int $offset = 0, array $orderBy = ['created_at' => 'DESC']): array
    {
        $limit = min(max(1, $limit), self::MAX_LIMIT);
        $offset = max(0, $offset);

        $results = array_values(array_filter(
            $this->contents,
            fn (Content $content): bool => $this->matchesCriteria($content, $criteria)
        ));

        $results = $this->sort($results, $orderBy);

        return array_slice($results, $offset, $limit);
    }

    /** @param array<string, mixed> $criteria */
    public function count(array $criteria = []): int
    {
        unset($criteria['search']);

        return count(array_filter(
            $this->contents,
            fn (Content $content): bool => $this->matchesCriteria($content, $criteria)
        ));
    }

    public function save(Content $content): Content
    {
        $id = $content->getId();

        if ($id === null) {
            $id = $this->autoIncrement;
            $content->setId($id);
        }

        if (is_numeric($id) && (int) $id >= $this->autoIncrement) {
            $this->autoIncrement = (int) $id + 1;
        }

        $this->contents[$id] = $content;

        return $content;
    }

    public function update(Content $content): bool
    {
        $id = $content->getId();

        if ($id === null || ! array_key_exists($id, $this->contents)) {
            return false;
        }

        $this->contents[$id] = $content;

        return true;
    }

    public function delete(int|string $id, bool $softDelete = true): bool
    {
        $content = $this->contents[$id] ?? null;

        if (! $content instanceof Content) {
            return false;
        }

        if ($softDelete) {
            $content->setDeletedAt(new DateTimeImmutable);

            return true;
        }

        unset($this->contents[$id]);

        return true;
    }

    public function restore(int|string $id): bool
    {
        $content = $this->contents[$id] ?? null;

        if (! $content instanceof Content) {
            return false;
        }

        $content->setDeletedAt(null);

        return true;
    }

    public function incrementViews(int|string $id): bool
    {
        $content = $this->contents[$id] ?? null;

        if (! $content instanceof Content) {
            return false;
        }

        $content->setViewCount($content->getViewCount() + 1);

        return true;
    }

    /**
     * @param  array<string, mixed>  $criteria
     */
    private function matchesCriteria(Content $content, array $criteria): bool
    {
        if ($content->getDeletedAt() instanceof DateTimeInterface) {
            return false;
        }

        if (isset($criteria['type']) && $content->getType() !== $criteria['type']) {
            return false;
        }

        if (isset($criteria['status']) && $content->getStatus() !== $criteria['status']) {
            return false;
        }

        if (array_key_exists('tenant_type', $criteria)) {
            if ($criteria['tenant_type'] === null) {
                if ($content->getTenantType() !== null) {
                    return false;
                }
            } elseif ($content->getTenantType() !== $criteria['tenant_type']) {
                return false;
            }
        }

        if (array_key_exists('tenant_id', $criteria)) {
            if ($criteria['tenant_id'] === null) {
                if ($content->getTenantId() !== null) {
                    return false;
                }
            } elseif ((string) $content->getTenantId() !== (string) $criteria['tenant_id']) {
                return false;
            }
        }

        if (isset($criteria['author_id']) && (string) $content->getAuthorId() !== (string) $criteria['author_id']) {
            return false;
        }

        return ! isset($criteria['search']) || $this->matchesSearch($content, (string) $criteria['search']);
    }

    private function matchesSearch(Content $content, string $search): bool
    {
        if (mb_stripos($content->getTitle(), $search) !== false) {
            return true;
        }

        return mb_stripos((string) $content->getBody(), $search) !== false;
    }

    /**
     * @param  array<int, Content>  $contents
     * @param  array<string, string>  $orderBy
     * @return array<int, Content>
     */
    private function sort(array $contents, array $orderBy): array
    {
        if ($orderBy === []) {
            $orderBy = ['created_at' => 'DESC'];
        }

        foreach ($orderBy as $field => $direction) {
            if (! in_array($field, self::ALLOWED_ORDER_BY_COLUMNS, true)) {
                throw InvalidOrderByException::column((string) $field, self::ALLOWED_ORDER_BY_COLUMNS);
            }
        }

        usort($contents, function (Content $a, Content $b) use ($orderBy): int {
            foreach ($orderBy as $field => $direction) {
                $result = $this->compareValues($this->valueFor($a, (string) $field), $this->valueFor($b, (string) $field));

                if ($result !== 0) {
                    return strtoupper($direction) === 'ASC' ? $result : -$result;
                }
            }

            return 0;
        });

        return $contents;
    }

    private function valueFor(Content $content, string $field): int|string|null
    {
        return match ($field) {
            'id' => is_numeric($content->getId()) ? (int) $content->getId() : (string) $content->getId(),
            'title' => $content->getTitle(),
            'created_at' => $content->getCreatedAt()?->getTimestamp(),
            'published_at' => $content->getPublishedAt()?->getTimestamp(),
            'sort_order' => $content->getSortOrder(),
            'view_count' => $content->getViewCount(),
            default => null,
        };
    }

    private function compareValues(int|string|null $a, int|string|null $b): int
    {
        if ($a === null && $b === null) {
            return 0;
        }

        if ($a === null) {
            return -1;
        }

        if ($b === null) {
            return 1;
        }

        if (is_int($a) && is_int($b)) {
            return $a <=> $b;
        }

        return (string) $a <=> (string) $b;
    }
}
