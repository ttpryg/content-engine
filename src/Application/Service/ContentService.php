<?php

declare(strict_types=1);

namespace Ttpryg\ContentEngine\Application\Service;

use DateTimeImmutable;
use Ttpryg\ContentEngine\Contracts\EventDispatcherInterface;
use Ttpryg\ContentEngine\Contracts\SlugGeneratorInterface;
use Ttpryg\ContentEngine\Domain\Entity\Content;
use Ttpryg\ContentEngine\Domain\Enum\ContentStatus;
use Ttpryg\ContentEngine\Domain\Event\ContentArchivedEvent;
use Ttpryg\ContentEngine\Domain\Event\ContentCreatedEvent;
use Ttpryg\ContentEngine\Domain\Event\ContentDeletedEvent;
use Ttpryg\ContentEngine\Domain\Event\ContentPublishedEvent;
use Ttpryg\ContentEngine\Domain\Event\ContentViewedEvent;
use Ttpryg\ContentEngine\Domain\Exception\ContentNotFoundException;
use Ttpryg\ContentEngine\Domain\Exception\InvalidContentStatusException;
use Ttpryg\ContentEngine\Domain\Repository\ContentRepositoryInterface;
use Ttpryg\ContentEngine\Utilities\NativeSlugGenerator;

class ContentService
{
    public function __construct(
        private readonly ContentRepositoryInterface $contentRepository,
        private ?SlugGeneratorInterface $slugGenerator = null,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        /**
         * Jika true, pembacaan konten TIDAK melakukan UPDATE view_count langsung
         * ke database (menghindari row-lock contention pada traffic tinggi).
         * Hitungan view dialirkan lewat ContentViewedEvent agar bisa di-buffer
         * / di-flush asinkron (mis. ke Redis atau _modules/queue).
         */
        private readonly bool $deferViewCounts = false
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
        if (! $content instanceof \Ttpryg\ContentEngine\Domain\Entity\Content) {
            throw ContentNotFoundException::byId($id);
        }

        $content->setStatus(ContentStatus::PUBLISHED->value);
        if (! $content->getPublishedAt() instanceof \DateTimeInterface) {
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
        if (! $content instanceof \Ttpryg\ContentEngine\Domain\Entity\Content) {
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
        $content = $this->contentRepository->findById($id, includeTrashed: true);
        if (! $content instanceof \Ttpryg\ContentEngine\Domain\Entity\Content) {
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
        $content = $this->contentRepository->findBySlug($slug, $type, includeTrashed: false, tenantType: $tenantType, tenantId: $tenantId);
        if (! $content instanceof \Ttpryg\ContentEngine\Domain\Entity\Content) {
            throw ContentNotFoundException::bySlug($slug, $type);
        }

        $this->eventDispatcher?->dispatch(new ContentViewedEvent($content));

        // Mode deferViewCounts: lewati UPDATE view_count per bacaan untuk menghindari
        // row-lock contention. Konsumen event (Redis / _modules/queue) yang menanggung
        // buffering & flush asinkron.
        if (! $this->deferViewCounts) {
            $this->contentRepository->incrementViews($content->getId());
        }

        return $content;
    }

    private function generateUniqueSlug(string $title, string $type, ?string $tenantType = null, int|string|null $tenantId = null): string
    {
        $baseSlug = $this->slugGenerator->generate($title);
        $slug = $baseSlug;
        $counter = 1;

        // Sengaja includeTrashed: true agar slug milik item soft-deleted tidak dipakai ulang,
        // mencegah tabrakan unique key saat item tersebut di-restore().
        while ($this->contentRepository->findBySlug($slug, $type, includeTrashed: true, tenantType: $tenantType, tenantId: $tenantId) instanceof \Ttpryg\ContentEngine\Domain\Entity\Content) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
