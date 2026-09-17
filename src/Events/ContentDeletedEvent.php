<?php

namespace Ttpryg\ContentEngine\Events;

class ContentDeletedEvent
{
    public function __construct(
        public readonly int|string $contentId,
        public readonly bool $softDeleted
    ) {}
}
