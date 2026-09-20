<?php

namespace Ttpryg\ContentEngine\Contracts;

use Ttpryg\ContentEngine\Entities\Content;

interface ContentRepositoryInterface
{
    public function findById(int|string $id, bool $includeTrashed = false): ?Content;

    public function findBySlug(string $slug, string $type = 'post', bool $includeTrashed = false): ?Content;

    public function findAll(array $criteria = [], int $limit = 20, int $offset = 0, array $orderBy = ['created_at' => 'DESC']): array;

    public function count(array $criteria = []): int;

    public function save(Content $content): Content;

    public function update(Content $content): bool;

    public function delete(int|string $id, bool $softDelete = true): bool;

    public function restore(int|string $id): bool;

    public function incrementViews(int|string $id): bool;
}
