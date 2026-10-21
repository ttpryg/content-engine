<?php

declare(strict_types=1);

namespace Ttpryg\ContentEngine\Application\UseCase;

use DateTimeImmutable;
use Psr\EventDispatcher\EventDispatcherInterface;
use Ttpryg\ContentEngine\Application\DTO\CreateContentRequest;
use Ttpryg\ContentEngine\Contracts\SlugGeneratorInterface;
use Ttpryg\ContentEngine\Domain\Entity\Content;
use Ttpryg\ContentEngine\Domain\Enum\ContentStatus;
use Ttpryg\ContentEngine\Domain\Event\ContentCreatedEvent;
use Ttpryg\ContentEngine\Domain\Event\ContentPublishedEvent;
use Ttpryg\ContentEngine\Domain\Exception\InvalidContentStatusException;
use Ttpryg\ContentEngine\Domain\Repository\ContentRepositoryInterface;

final class CreateContentUseCase
{
    public function __construct(
        private readonly ContentRepositoryInterface $contentRepository,
        private readonly SlugGeneratorInterface $slugGenerator,
        private readonly ?EventDispatcherInterface $eventDispatcher = null
    ) {}

    public function execute(CreateContentRequest $request): Content
    {
        $status = ContentStatus::tryFrom($request->status) ?? throw new InvalidContentStatusException($request->status);

        $generatedSlug = $request->slug ?: $this->generateUniqueSlug(
            $request->title,
            $request->type,
            $request->tenantType,
            $request->tenantId
        );

        $publishedAt = $status === ContentStatus::PUBLISHED ? new DateTimeImmutable : null;

        $content = new Content(
            title: $request->title,
            type: $request->type,
            slug: $generatedSlug,
            summary: $request->summary,
            body: $request->body,
            meta: $request->meta,
            status: $status,
            sortOrder: $request->sortOrder,
            authorId: $request->authorId,
            tenantType: $request->tenantType,
            tenantId: $request->tenantId,
            publishedAt: $publishedAt
        );

        $savedContent = $this->contentRepository->save($content);

        $this->eventDispatcher?->dispatch(new ContentCreatedEvent($savedContent));

        if ($status === ContentStatus::PUBLISHED) {
            $this->eventDispatcher?->dispatch(new ContentPublishedEvent($savedContent));
        }

        return $savedContent;
    }

    private function generateUniqueSlug(string $title, string $type, ?string $tenantType = null, int|string|null $tenantId = null): string
    {
        $baseSlug = $this->slugGenerator->generate($title);
        $slug = $baseSlug;
        $counter = 1;

        // Sengaja includeTrashed: true agar slug milik item soft-deleted tidak dipakai ulang,
        // mencegah tabrakan unique key saat item tersebut di-restore().
        while ($this->contentRepository->findBySlug($slug, $type, includeTrashed: true, tenantType: $tenantType, tenantId: $tenantId) instanceof Content) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
