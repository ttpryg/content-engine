<?php

namespace Ttpryg\ContentEngine\Services;

use DateTimeImmutable;
use Ttpryg\ContentEngine\Contracts\ContentRepositoryInterface;
use Ttpryg\ContentEngine\Contracts\EventDispatcherInterface;
use Ttpryg\ContentEngine\Contracts\SlugGeneratorInterface;
use Ttpryg\ContentEngine\Entities\Content;
use Ttpryg\ContentEngine\Events\ContentArchivedEvent;
use Ttpryg\ContentEngine\Events\ContentCreatedEvent;
use Ttpryg\ContentEngine\Events\ContentDeletedEvent;
use Ttpryg\ContentEngine\Events\ContentPublishedEvent;
use Ttpryg\ContentEngine\Exceptions\ContentNotFoundException;
use Ttpryg\ContentEngine\Exceptions\InvalidContentStatusException;
use Ttpryg\ContentEngine\Utilities\NativeSlugGenerator;
use Ttpryg\ContentEngine\ValueObjects\ContentStatus;

class ContentService
{
    public function __construct(
        private ContentRepositoryInterface $contentRepository,
        private ?SlugGeneratorInterface $slugGenerator = null,
        private ?EventDispatcherInterface $eventDispatcher = null
    ) {
        $this->slugGenerator = $slugGenerator ?? new NativeSlugGenerator;
    }

    public function createContent(
        string $title,
        string $type = 'post',
        ?string $slug = null,
        ?string $summary = null,
        ?string $body = null,
        array $meta = [],
        string $status = 'draft',
        int $sortOrder = 0,
        int|string|null $authorId = null,
        ?string $tenantType = null,
        int|string|null $tenantId = null
    ): Content {
        if (! ContentStatus::isValid($status)) {
            throw new InvalidContentStatusException($status);
        }

        $generatedSlug = $slug ?: $this->generateUniqueSlug($title, $type, $tenantType, $tenantId);

        $publishedAt = ($status === ContentStatus::PUBLISHED->value) ? new DateTimeImmutable : null;

        $content = new Content(
            title: $title,
            type: $type,
            slug: $generatedSlug,
            summary: $summary,
            body: $body,
            meta: $meta,
            status: $status,
            sortOrder: $sortOrder,
            authorId: $authorId,
            tenantType: $tenantType,
            tenantId: $tenantId,
            publishedAt: $publishedAt
        );

        $savedContent = $this->contentRepository->save($content);

        $this->eventDispatcher?->dispatch(new ContentCreatedEvent($savedContent));

        if ($status === ContentStatus::PUBLISHED->value) {
            $this->eventDispatcher?->dispatch(new ContentPublishedEvent($savedContent));
        }

        return $savedContent;
    }

    public function publish(int|string $id): bool
    {
        $content = $this->contentRepository->findById($id);
        if (! $content) {
            throw ContentNotFoundException::byId($id);
        }

        $content->setStatus(ContentStatus::PUBLISHED->value);
        if ($content->getPublishedAt() === null) {
            $content->setPublishedAt(new DateTimeImmutable);
        }

        $result = $this->contentRepository->update($content);
        if ($result) {
            $this->eventDispatcher?->dispatch(new ContentPublishedEvent($content));
        }

        return $result;
    }

    public function archive(int|string $id): bool
    {
        $content = $this->contentRepository->findById($id);
        if (! $content) {
            throw ContentNotFoundException::byId($id);
        }

        $content->setStatus(ContentStatus::ARCHIVED->value);
        $result = $this->contentRepository->update($content);

        if ($result) {
            $this->eventDispatcher?->dispatch(new ContentArchivedEvent($content));
        }

        return $result;
    }

    public function delete(int|string $id, bool $softDelete = true): bool
    {
        $content = $this->contentRepository->findById($id, true);
        if (! $content) {
            throw ContentNotFoundException::byId($id);
        }

        $result = $this->contentRepository->delete($id, $softDelete);

        if ($result) {
            $this->eventDispatcher?->dispatch(new ContentDeletedEvent($id, $softDelete));
        }

        return $result;
    }

    public function getContentBySlug(string $slug, string $type = 'post', ?string $tenantType = null, int|string|null $tenantId = null): Content
    {
        $content = $this->contentRepository->findBySlug($slug, $type, false, $tenantType, $tenantId);
        if (! $content) {
            throw ContentNotFoundException::bySlug($slug, $type);
        }

        $this->contentRepository->incrementViews($content->getId());

        return $content;
    }

    private function generateUniqueSlug(string $title, string $type, ?string $tenantType = null, int|string|null $tenantId = null): string
    {
        $baseSlug = $this->slugGenerator->generate($title);
        $slug = $baseSlug;
        $counter = 1;

        while ($this->contentRepository->findBySlug($slug, $type, false, $tenantType, $tenantId) !== null) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
