<?php

declare(strict_types=1);

namespace Ttpryg\ContentEngine\Contracts;

interface SlugGeneratorInterface
{
    public function generate(string $title): string;
}
