<?php

declare(strict_types=1);

namespace Ttpryg\ContentEngine\Application\UseCase;

use Psr\EventDispatcher\EventDispatcherInterface;
use Ttpryg\ContentEngine\Domain\Entity\Content;
use Ttpryg\ContentEngine\Domain\Event\ContentViewedEvent;
use Ttpryg\ContentEngine\Domain\Exception\ContentNotFoundException;
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

    public function execute(string $slug, string $type = 'post', ?string $tenantType = null, int|string|null $tenantId = null): Content
    {
        $content = $this->contentRepository->findBySlug($slug, $type, includeTrashed: false, tenantType: $tenantType, tenantId: $tenantId);
        if (! $content instanceof Content) {
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
}
