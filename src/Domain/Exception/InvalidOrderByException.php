<?php

declare(strict_types=1);

namespace Ttpryg\ContentEngine\Domain\Exception;

class InvalidOrderByException extends ContentEngineException
{
    /**
     * @param  array<int, string>  $allowedColumns
     */
    public static function column(string $column, array $allowedColumns): self
    {
        $allowed = implode(', ', $allowedColumns);

        return new self("Order by column '{$column}' is not allowed. Allowed columns: {$allowed}.");
    }
}
