<?php

declare(strict_types=1);

namespace Ttpryg\ContentEngine\Domain\Event;

use Ttpryg\ContentEngine\Domain\Entity\Content;

final class ContentCreatedEvent
{
    public function __construct(
        public readonly Content $content
    ) {}
}
