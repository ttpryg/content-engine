<?php

declare(strict_types=1);

namespace Ttpryg\ContentEngine\Application\Service;

use Psr\EventDispatcher\EventDispatcherInterface;
use Ttpryg\ContentEngine\Application\DTO\CreateContentRequest;
use Ttpryg\ContentEngine\Application\UseCase\ArchiveContentUseCase;
use Ttpryg\ContentEngine\Application\UseCase\CreateContentUseCase;
use Ttpryg\ContentEngine\Application\UseCase\DeleteContentUseCase;
use Ttpryg\ContentEngine\Application\UseCase\PublishContentUseCase;
use Ttpryg\ContentEngine\Application\UseCase\ViewContentUseCase;
use Ttpryg\ContentEngine\Contracts\SlugGeneratorInterface;
use Ttpryg\ContentEngine\Domain\Entity\Content;
use Ttpryg\ContentEngine\Domain\Enum\ContentStatus;
use Ttpryg\ContentEngine\Domain\Repository\ContentRepositoryInterface;

class ContentService
{
    private readonly CreateContentUseCase $createContentUseCase;

    private readonly PublishContentUseCase $publishContentUseCase;

    private readonly ArchiveContentUseCase $archiveContentUseCase;

    private readonly DeleteContentUseCase $deleteContentUseCase;

    private readonly ViewContentUseCase $viewContentUseCase;

    public function __construct(
        ContentRepositoryInterface $contentRepository,
        SlugGeneratorInterface $slugGenerator,
        ?EventDispatcherInterface $eventDispatcher = null,
        bool $deferViewCounts = false
    ) {
        $this->createContentUseCase = new CreateContentUseCase($contentRepository, $slugGenerator, $eventDispatcher);
        $this->publishContentUseCase = new PublishContentUseCase($contentRepository, $eventDispatcher);
        $this->archiveContentUseCase = new ArchiveContentUseCase($contentRepository, $eventDispatcher);
        $this->deleteContentUseCase = new DeleteContentUseCase($contentRepository, $eventDispatcher);
        $this->viewContentUseCase = new ViewContentUseCase($contentRepository, $eventDispatcher, $deferViewCounts);
    }

    public function createContent(CreateContentRequest $request): Content
    {
        return $this->createContentUseCase->execute($request);
    }

    public function publish(int|string $id): bool
    {
        return $this->publishContentUseCase->execute($id);
    }

    public function archive(int|string $id): bool
    {
        return $this->archiveContentUseCase->execute($id);
    }

    public function delete(int|string $id, bool $softDelete = true): bool
    {
        return $this->deleteContentUseCase->execute($id, $softDelete);
    }

    public function getContentBySlug(
        string $slug,
        string $type = 'post',
        ?string $tenantType = null,
        int|string|null $tenantId = null,
        ContentStatus|string|null $status = ContentStatus::PUBLISHED
    ): Content {
        return $this->viewContentUseCase->execute($slug, $type, $tenantType, $tenantId, $status);
    }
}
