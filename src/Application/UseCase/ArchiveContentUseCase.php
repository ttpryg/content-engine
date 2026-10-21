<?php

declare(strict_types=1);

namespace Ttpryg\ContentEngine\Application\UseCase;

use Psr\EventDispatcher\EventDispatcherInterface;
use Ttpryg\ContentEngine\Domain\Entity\Content;
use Ttpryg\ContentEngine\Domain\Enum\ContentStatus;
use Ttpryg\ContentEngine\Domain\Event\ContentArchivedEvent;
use Ttpryg\ContentEngine\Domain\Exception\ContentNotFoundException;
use Ttpryg\ContentEngine\Domain\Repository\ContentRepositoryInterface;

final class ArchiveContentUseCase
{
    public function __construct(
        private readonly ContentRepositoryInterface $contentRepository,
        private readonly ?EventDispatcherInterface $eventDispatcher = null
    ) {}

    public function execute(int|string $id): bool
    {
        $content = $this->contentRepository->findById($id);
        if (! $content instanceof Content) {
            throw ContentNotFoundException::byId($id);
        }

        $content->setStatus(ContentStatus::ARCHIVED->value);
        $result = $this->contentRepository->update($content);

        if ($result) {
            $this->eventDispatcher?->dispatch(new ContentArchivedEvent($content));
        }

        return $result;
    }
}
