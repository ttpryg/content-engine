<?php

declare(strict_types=1);

namespace Ttpryg\ContentEngine\Application\UseCase;

use Psr\EventDispatcher\EventDispatcherInterface;
use Ttpryg\ContentEngine\Domain\Entity\Content;
use Ttpryg\ContentEngine\Domain\Event\ContentDeletedEvent;
use Ttpryg\ContentEngine\Domain\Exception\ContentNotFoundException;
use Ttpryg\ContentEngine\Domain\Repository\ContentRepositoryInterface;

final class DeleteContentUseCase
{
    public function __construct(
        private readonly ContentRepositoryInterface $contentRepository,
        private readonly ?EventDispatcherInterface $eventDispatcher = null
    ) {}

    public function execute(int|string $id, bool $softDelete = true): bool
    {
        $content = $this->contentRepository->findById($id, includeTrashed: true);
        if (! $content instanceof Content) {
            throw ContentNotFoundException::byId($id);
        }

        $result = $this->contentRepository->delete($id, $softDelete);

        if ($result) {
            $this->eventDispatcher?->dispatch(new ContentDeletedEvent($id, $softDelete));
        }

        return $result;
    }
}
