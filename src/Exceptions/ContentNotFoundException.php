<?php

namespace Ttpryg\ContentEngine\Exceptions;

class ContentNotFoundException extends ContentEngineException
{
    public static function byId(int|string $id): self
    {
        return new self("Content with ID '{$id}' was not found.");
    }

    public static function bySlug(string $slug, string $type): self
    {
        return new self("Content of type '{$type}' with slug '{$slug}' was not found.");
    }
}
