<?php

namespace Ttpryg\ContentEngine\Exceptions;

class InvalidContentStatusException extends ContentEngineException
{
    public function __construct(string $status)
    {
        parent::__construct("Invalid content status '{$status}'. Allowed values are 'draft', 'published', 'archived'.");
    }
}
