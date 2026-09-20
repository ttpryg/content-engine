<?php

namespace Ttpryg\ContentEngine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ttpryg\ContentEngine\Contracts\ContentRepositoryInterface;
use Ttpryg\ContentEngine\Contracts\EventDispatcherInterface;
use Ttpryg\ContentEngine\Contracts\SlugGeneratorInterface;
use Ttpryg\ContentEngine\Entities\Content;
use Ttpryg\ContentEngine\Events\ContentCreatedEvent;
use Ttpryg\ContentEngine\Events\ContentPublishedEvent;
use Ttpryg\ContentEngine\Exceptions\ContentNotFoundException;
use Ttpryg\ContentEngine\Exceptions\InvalidContentStatusException;
use Ttpryg\ContentEngine\Services\ContentService;

class ContentServiceTest extends TestCase
{
    // POSITIVE CASE: Create Content
    public function test_successful_content_creation(): void
    {
        $repo = $this->createMock(ContentRepositoryInterface::class);
        $slugGen = $this->createMock(SlugGeneratorInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $slugGen->method('generate')->with('My First Post')->willReturn('my-first-post');
        $repo->method('findBySlug')->willReturn(value: null);

        $repo->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (Content $content): \Ttpryg\ContentEngine\Entities\Content {
                $content->setId(1);

                return $content;
            });

        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ContentCreatedEvent::class));

        $contentService = new ContentService($repo, $slugGen, $dispatcher);
        $content = $contentService->createContent('My First Post', type: 'post', body: 'Hello world');

        $this->assertEquals(1, $content->getId());
        $this->assertEquals('my-first-post', $content->getSlug());
        $this->assertEquals('draft', $content->getStatus());
    }

    // NEGATIVE CASE: Invalid Status Throws Exception
    public function test_create_content_fails_on_invalid_status(): void
    {
        $repo = $this->createMock(ContentRepositoryInterface::class);

        $this->expectException(InvalidContentStatusException::class);

        $contentService = new ContentService($repo);
        $contentService->createContent('Invalid Post', status: 'non_existent_status');
    }

    // POSITIVE CASE: Publish Content
    public function test_publish_content(): void
    {
        $repo = $this->createMock(ContentRepositoryInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $content = new Content('Draft Post', status: 'draft', id: 5);
        $repo->method('findById')->with(5)->willReturn($content);
        $repo->method('update')->willReturn(value: true);

        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ContentPublishedEvent::class));

        $contentService = new ContentService($repo, eventDispatcher: $dispatcher);
        $result = $contentService->publish(5);

        $this->assertTrue($result);
        $this->assertEquals('published', $content->getStatus());
        $this->assertNotNull($content->getPublishedAt());
    }

    // NEGATIVE CASE: Publish Non-Existent Content
    public function test_publish_fails_on_non_existent_content(): void
    {
        $repo = $this->createMock(ContentRepositoryInterface::class);
        $repo->method('findById')->with(999)->willReturn(value: null);

        $this->expectException(ContentNotFoundException::class);

        $contentService = new ContentService($repo);
        $contentService->publish(999);
    }
}
