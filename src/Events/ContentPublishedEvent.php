<?php

declare(strict_types=1);

namespace Ttpryg\ContentEngine\Events;

use Ttpryg\ContentEngine\Entities\Content;

class ContentPublishedEvent
{
    public function __construct(
        public readonly Content $content
    ) {}
}
