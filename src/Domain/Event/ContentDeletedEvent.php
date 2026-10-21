<?php

declare(strict_types=1);

namespace Ttpryg\ContentEngine\Domain\Event;

class ContentDeletedEvent
{
    public function __construct(
        public readonly int|string $contentId,
        public readonly bool $softDeleted
    ) {}
}
