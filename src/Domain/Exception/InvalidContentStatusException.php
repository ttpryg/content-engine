<?php

declare(strict_types=1);

namespace Ttpryg\ContentEngine\Domain\Exception;

class InvalidContentStatusException extends ContentEngineException
{
    public function __construct(string $status)
    {
        parent::__construct("Invalid content status '{$status}'. Allowed values are 'draft', 'published', 'archived'.");
    }
}
