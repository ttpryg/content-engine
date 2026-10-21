<?php

declare(strict_types=1);

namespace Ttpryg\ContentEngine\Tests\Unit\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use Ttpryg\ContentEngine\Domain\ValueObject\SeoMeta;

class SeoMetaTest extends TestCase
{
    public function test_from_array_maps_snake_case_keys(): void
    {
        $meta = SeoMeta::fromArray([
            'meta_title' => 'Title',
            'meta_description' => 'Description',
            'og_image' => 'https://example.com/image.png',
            'keywords' => ['php', 'cms'],
        ]);

        $this->assertSame('Title', $meta->metaTitle);
        $this->assertSame('Description', $meta->metaDescription);
        $this->assertSame('https://example.com/image.png', $meta->ogImage);
        $this->assertSame(['php', 'cms'], $meta->keywords);
    }

    public function test_to_array_omits_null_values(): void
    {
        $meta = new SeoMeta(metaTitle: 'Title');

        $this->assertSame(['meta_title' => 'Title'], $meta->toArray());
    }

    public function test_from_array_ignores_invalid_value_types(): void
    {
        $meta = SeoMeta::fromArray([
            'meta_title' => 123,
            'keywords' => 'not-an-array',
        ]);

        $this->assertNull($meta->metaTitle);
        $this->assertNull($meta->keywords);
        $this->assertSame([], $meta->toArray());
    }
}
