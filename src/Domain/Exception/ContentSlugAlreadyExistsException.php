<?php

declare(strict_types=1);

namespace Ttpryg\ContentEngine\Domain\Exception;

class ContentSlugAlreadyExistsException extends ContentEngineException
{
    public static function bySlug(string $slug): self
    {
        return new self("Content slug '{$slug}' already exists for this type and tenant scope.");
    }
}
