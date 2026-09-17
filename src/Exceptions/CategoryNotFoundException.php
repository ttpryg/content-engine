<?php

namespace Ttpryg\ContentEngine\Exceptions;

class CategoryNotFoundException extends ContentEngineException
{
    public static function byId(int|string $id): self
    {
        return new self("Category with ID '{$id}' was not found.");
    }

    public static function bySlug(string $slug): self
    {
        return new self("Category with slug '{$slug}' was not found.");
    }
}
