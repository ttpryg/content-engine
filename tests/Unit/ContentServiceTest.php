<?php

namespace Ttpryg\ContentEngine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Ttpryg\ContentEngine\Application\DTO\CreateContentRequest;
use Ttpryg\ContentEngine\Application\Service\ContentService;
use Ttpryg\ContentEngine\Contracts\SlugGeneratorInterface;
use Ttpryg\ContentEngine\Domain\Entity\Content;
use Ttpryg\ContentEngine\Domain\Event\ContentCreatedEvent;
use Ttpryg\ContentEngine\Domain\Event\ContentPublishedEvent;
use Ttpryg\ContentEngine\Domain\Event\ContentViewedEvent;
use Ttpryg\ContentEngine\Domain\Exception\ContentNotFoundException;
use Ttpryg\ContentEngine\Domain\Exception\InvalidContentStatusException;
use Ttpryg\ContentEngine\Domain\Repository\ContentRepositoryInterface;
use Ttpryg\ContentEngine\Utilities\NativeSlugGenerator;

class ContentServiceTest extends TestCase
{
    // POSITIVE CASE: Create Content
    public function test_successful_content_creation(): void
    {
        $repo = $this->createMock(ContentRepositoryInterface::class);
        $slugGen = $this->createMock(SlugGeneratorInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $slugGen->method('generate')->with('My First Post')->willReturn('my-first-post');
        $repo->method('findBySlug')->willReturn(null);

        $repo->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (Content $content): \Ttpryg\ContentEngine\Domain\Entity\Content {
                $content->setId(1);

                return $content;
            });

        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ContentCreatedEvent::class));

        $contentService = new ContentService($repo, $slugGen, $dispatcher);
        $content = $contentService->createContent(new CreateContentRequest(
            title: 'My First Post',
            type: 'post',
            body: 'Hello world'
        ));

        $this->assertEquals(1, $content->getId());
        $this->assertEquals('my-first-post', $content->getSlug());
        $this->assertEquals('draft', $content->getStatus());
    }

    // NEGATIVE CASE: Invalid Status Throws Exception
    public function test_create_content_fails_on_invalid_status(): void
    {
        $repo = $this->createMock(ContentRepositoryInterface::class);

        $this->expectException(InvalidContentStatusException::class);

        $contentService = new ContentService($repo, new NativeSlugGenerator);
        $contentService->createContent(new CreateContentRequest(
            title: 'Invalid Post',
            status: 'non_existent_status'
        ));
    }

    // POSITIVE CASE: Publish Content
    public function test_publish_content(): void
    {
        $repo = $this->createMock(ContentRepositoryInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $content = new Content('Draft Post', status: 'draft', id: 5);
        $repo->method('findById')->with(5)->willReturn($content);
        $repo->method('update')->willReturn(true);

        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ContentPublishedEvent::class));

        $contentService = new ContentService($repo, new NativeSlugGenerator, eventDispatcher: $dispatcher);
        $result = $contentService->publish(5);

        $this->assertTrue($result);
        $this->assertEquals('published', $content->getStatus());
        $this->assertNotNull($content->getPublishedAt());
    }

    // NEGATIVE CASE: Publish Non-Existent Content
    public function test_publish_fails_on_non_existent_content(): void
    {
        $repo = $this->createMock(ContentRepositoryInterface::class);
        $repo->method('findById')->with(999)->willReturn(null);

        $this->expectException(ContentNotFoundException::class);

        $contentService = new ContentService($repo, new NativeSlugGenerator);
        $contentService->publish(999);
    }

    // INTEGRITY CASE: Slug generation mempertimbangkan item soft-deleted agar aman saat restore()
    public function test_slug_generation_avoids_trashed_slug_collision(): void
    {
        $repo = $this->createMock(ContentRepositoryInterface::class);
        $slugGen = $this->createMock(SlugGeneratorInterface::class);

        $slugGen->method('generate')->with('My First Post')->willReturn('my-first-post');

        $repo->expects($this->exactly(2))
            ->method('findBySlug')
            ->willReturnCallback(function (string $slug, string $type = 'post', bool $includeTrashed = false, ?string $tenantType = null, int|string|null $tenantId = null): ?Content {
                // Slug unik harus mengecek termasuk item trashed (includeTrashed = true).
                $this->assertTrue($includeTrashed, 'findBySlug must include trashed items when generating a unique slug.');

                return $slug === 'my-first-post'
                    ? new Content('My First Post', type: $type, slug: $slug, deletedAt: new \DateTimeImmutable)
                    : null;
            });

        $repo->method('save')->willReturnCallback(function (Content $content): Content {
            $content->setId(2);

            return $content;
        });

        $contentService = new ContentService($repo, $slugGen);
        $content = $contentService->createContent(new CreateContentRequest(
            title: 'My First Post',
            type: 'post'
        ));

        $this->assertEquals('my-first-post-1', $content->getSlug());
    }

    // SCALABILITY CASE: Mode default — pembacaan menaikkan view_count dan memicu ContentViewedEvent
    public function test_get_content_by_slug_increments_views_and_dispatches_viewed_event(): void
    {
        $repo = $this->createMock(ContentRepositoryInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $content = new Content('Viewed Post', type: 'post', slug: 'viewed-post', id: 10);

        $repo->method('findBySlug')->willReturn($content);
        $repo->expects($this->once())
            ->method('incrementViews')
            ->with(10)
            ->willReturn(true);

        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ContentViewedEvent::class));

        $contentService = new ContentService($repo, new NativeSlugGenerator, eventDispatcher: $dispatcher);
        $result = $contentService->getContentBySlug('viewed-post');

        $this->assertEquals('Viewed Post', $result->getTitle());
    }

    // SCALABILITY CASE: deferViewCounts=true — lewati UPDATE view_count (hindari row-lock contention)
    public function test_get_content_by_slug_with_defer_view_counts_skips_database_increment(): void
    {
        $repo = $this->createMock(ContentRepositoryInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $content = new Content('Viewed Post', type: 'post', slug: 'viewed-post', id: 10);

        $repo->method('findBySlug')->willReturn($content);
        // Inti test: TIDAK ada UPDATE view_count ke database pada mode defer.
        $repo->expects($this->never())->method('incrementViews');

        // Event tetap dikirim agar konsumen async (Redis / _modules/queue) bisa buffering.
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ContentViewedEvent::class));

        $contentService = new ContentService($repo, new NativeSlugGenerator, eventDispatcher: $dispatcher, deferViewCounts: true);
        $result = $contentService->getContentBySlug('viewed-post');

        $this->assertEquals(10, $result->getId());
    }

    // NEGATIVE CASE: getContentBySlug pada konten tidak ada → ContentNotFoundException, tanpa increment
    public function test_get_content_by_slug_fails_on_missing_content(): void
    {
        $repo = $this->createMock(ContentRepositoryInterface::class);
        $repo->method('findBySlug')->willReturn(null);
        $repo->expects($this->never())->method('incrementViews');

        $this->expectException(ContentNotFoundException::class);

        $contentService = new ContentService($repo, new NativeSlugGenerator);
        $contentService->getContentBySlug('missing-post');
    }
}
