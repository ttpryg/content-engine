<?php

declare(strict_types=1);

namespace Ttpryg\ContentEngine\Application\UseCase;

use DateTimeImmutable;
use DateTimeInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Ttpryg\ContentEngine\Domain\Entity\Content;
use Ttpryg\ContentEngine\Domain\Event\ContentPublishedEvent;
use Ttpryg\ContentEngine\Domain\Exception\ContentNotFoundException;
use Ttpryg\ContentEngine\Domain\Repository\ContentRepositoryInterface;

final class PublishContentUseCase
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

        $content->publish();
        if (! $content->getPublishedAt() instanceof DateTimeInterface) {
            $content->setPublishedAt(new DateTimeImmutable);
        }

        $result = $this->contentRepository->update($content);
        if ($result) {
            $this->eventDispatcher?->dispatch(new ContentPublishedEvent($content));
        }

        return $result;
    }
}
