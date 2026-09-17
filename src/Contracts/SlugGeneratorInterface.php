<?php

namespace Ttpryg\ContentEngine\Contracts;

interface SlugGeneratorInterface
{
    public function generate(string $title): string;
}
