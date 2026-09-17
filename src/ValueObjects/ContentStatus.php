<?php

namespace Ttpryg\ContentEngine\ValueObjects;

enum ContentStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';

    public static function isValid(string $status): bool
    {
        return self::tryFrom($status) !== null;
    }
}
