<?php

declare(strict_types=1);

namespace Ttpryg\ContentEngine\Tests\Unit\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use Ttpryg\ContentEngine\Domain\ValueObject\TestimonialMeta;

class TestimonialMetaTest extends TestCase
{
    public function test_from_array_maps_fields_and_casts_rating(): void
    {
        $meta = TestimonialMeta::fromArray([
            'company' => 'Acme Corp',
            'position' => 'CEO',
            'rating' => '5',
            'avatar_url' => 'https://example.com/avatar.png',
        ]);

        $this->assertSame('Acme Corp', $meta->company);
        $this->assertSame('CEO', $meta->position);
        $this->assertSame(5, $meta->rating);
        $this->assertSame('https://example.com/avatar.png', $meta->avatarUrl);
    }

    public function test_to_array_omits_null_values(): void
    {
        $meta = new TestimonialMeta(company: 'Acme Corp');

        $this->assertSame(['company' => 'Acme Corp'], $meta->toArray());
    }

    public function test_from_array_ignores_invalid_rating(): void
    {
        $meta = TestimonialMeta::fromArray(['rating' => 'not-a-number']);

        $this->assertNull($meta->rating);
    }
}
