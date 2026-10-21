<?php

declare(strict_types=1);

namespace Ttpryg\ContentEngine\Application\UseCase;

use Psr\EventDispatcher\EventDispatcherInterface;
use Ttpryg\ContentEngine\Domain\Entity\Content;
use Ttpryg\ContentEngine\Domain\Enum\ContentStatus;
use Ttpryg\ContentEngine\Domain\Event\ContentViewedEvent;
use Ttpryg\ContentEngine\Domain\Exception\ContentNotFoundException;
use Ttpryg\ContentEngine\Domain\Exception\InvalidContentStatusException;
use Ttpryg\ContentEngine\Domain\Repository\ContentRepositoryInterface;

final class ViewContentUseCase
{
    public function __construct(
        private readonly ContentRepositoryInterface $contentRepository,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        /**
         * Jika true, pembacaan konten TIDAK melakukan UPDATE view_count langsung
         * ke database (menghindari row-lock contention pada traffic tinggi).
         * Hitungan view dialirkan lewat ContentViewedEvent agar bisa di-buffer
         * / di-flush asinkron (mis. ke Redis atau _modules/queue).
         */
        private readonly bool $deferViewCounts = false
    ) {}

    public function execute(
        string $slug,
        string $type = 'post',
        ?string $tenantType = null,
        int|string|null $tenantId = null,
        ContentStatus|string|null $status = ContentStatus::PUBLISHED
    ): Content {
        $statusValue = $this->normalizeStatus($status);

        $content = $this->contentRepository->findBySlug($slug, $type, includeTrashed: false, tenantType: $tenantType, tenantId: $tenantId);
        if (! $content instanceof Content || ($statusValue !== null && $content->getStatus() !== $statusValue)) {
            throw ContentNotFoundException::bySlug($slug, $type);
        }

        $this->eventDispatcher?->dispatch(new ContentViewedEvent($content));

        // Mode deferViewCounts: lewati UPDATE view_count per bacaan untuk menghindari
        // row-lock contention. Konsumen event (Redis / _modules/queue) yang menanggung
        // buffering & flush asinkron.
        if (! $this->deferViewCounts) {
            $id = $content->getId();
            if ($id !== null) {
                $this->contentRepository->incrementViews($id);
            }
        }

        return $content;
    }

    private function normalizeStatus(ContentStatus|string|null $status): ?string
    {
        if ($status === null) {
            return null;
        }

        if ($status instanceof ContentStatus) {
            return $status->value;
        }

        $resolved = ContentStatus::tryFrom($status);
        if ($resolved === null) {
            throw new InvalidContentStatusException($status);
        }

        return $resolved->value;
    }
}
