<?php

declare(strict_types=1);

namespace Ttpryg\ContentEngine\Tests\Unit\Domain\Event;

use PHPUnit\Framework\TestCase;
use Ttpryg\ContentEngine\Domain\Entity\Content;
use Ttpryg\ContentEngine\Domain\Event\ContentArchivedEvent;
use Ttpryg\ContentEngine\Domain\Event\ContentCreatedEvent;
use Ttpryg\ContentEngine\Domain\Event\ContentDeletedEvent;
use Ttpryg\ContentEngine\Domain\Event\ContentPublishedEvent;
use Ttpryg\ContentEngine\Domain\Event\ContentViewedEvent;

class ContentEventsTest extends TestCase
{
    public function test_content_events_expose_the_content(): void
    {
        $content = new Content('Event Post');

        $this->assertSame($content, (new ContentCreatedEvent($content))->content);
        $this->assertSame($content, (new ContentPublishedEvent($content))->content);
        $this->assertSame($content, (new ContentArchivedEvent($content))->content);
        $this->assertSame($content, (new ContentViewedEvent($content))->content);
    }

    public function test_deleted_event_exposes_id_and_delete_mode(): void
    {
        $event = new ContentDeletedEvent(contentId: 42, softDeleted: true);

        $this->assertSame(42, $event->contentId);
        $this->assertTrue($event->softDeleted);
    }
}
