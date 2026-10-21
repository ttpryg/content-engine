<?php

declare(strict_types=1);

namespace Ttpryg\ContentEngine\Domain\Exception;

class CategorySlugAlreadyExistsException extends ContentEngineException
{
    public static function bySlug(string $slug): self
    {
        return new self("Category slug '{$slug}' already exists.");
    }
}
