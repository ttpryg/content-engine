<?php

declare(strict_types=1);

namespace Ttpryg\ContentEngine\Application\DTO;

final class CreateContentRequest
{
    public function __construct(
        public readonly string $title,
        public readonly string $type = 'post',
        public readonly ?string $slug = null,
        public readonly ?string $summary = null,
        public readonly ?string $body = null,
        public readonly array $meta = [],
        public readonly string $status = 'draft',
        public readonly int $sortOrder = 0,
        public readonly int|string|null $authorId = null,
        public readonly ?string $tenantType = null,
        public readonly int|string|null $tenantId = null
    ) {}
}
