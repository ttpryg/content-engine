<?php

declare(strict_types=1);

namespace Ttpryg\ContentEngine\Domain\Event;

use Ttpryg\ContentEngine\Domain\Entity\Content;

class ContentArchivedEvent
{
    public function __construct(
        public readonly Content $content
    ) {}
}
