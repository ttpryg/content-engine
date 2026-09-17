<?php

namespace Ttpryg\ContentEngine\Events;

use Ttpryg\ContentEngine\Entities\Content;

class ContentCreatedEvent
{
    public function __construct(
        public readonly Content $content
    ) {}
}
