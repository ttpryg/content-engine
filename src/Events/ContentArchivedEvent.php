<?php

namespace Ttpryg\ContentEngine\Events;

use Ttpryg\ContentEngine\Entities\Content;

class ContentArchivedEvent
{
    public function __construct(
        public readonly Content $content
    ) {}
}
