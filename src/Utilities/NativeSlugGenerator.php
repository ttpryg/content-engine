<?php

declare(strict_types=1);

namespace Ttpryg\ContentEngine\Utilities;

use Ttpryg\ContentEngine\Contracts\SlugGeneratorInterface;

final class NativeSlugGenerator implements SlugGeneratorInterface
{
    public function generate(string $title): string
    {
        // Lowercase
        $slug = mb_strtolower($title, 'UTF-8');
        // Replace non letter or digits by -
        $slug = preg_replace('~[^\pL\d]+~u', '-', $slug) ?? $slug;
        // Transliterate
        $slug = iconv('utf-8', 'us-ascii//TRANSLIT', $slug) ?: $slug;
        // Remove unwanted characters
        $slug = preg_replace('~[^\-\w]+~', '', $slug) ?? $slug;
        // Trim hyphens
        $slug = trim($slug, '-');
        // Remove duplicate hyphens
        $slug = preg_replace('~-+~', '-', $slug) ?? $slug;

        return empty($slug) ? 'n-a' : $slug;
    }
}
