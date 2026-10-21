<?php

declare(strict_types=1);

namespace Ttpryg\ContentEngine\Tests\Unit\Domain\Enum;

use PHPUnit\Framework\TestCase;
use Ttpryg\ContentEngine\Domain\Enum\ContentType;

class ContentTypeTest extends TestCase
{
    public function test_values_match_persisted_types(): void
    {
        $this->assertSame('page', ContentType::PAGE->value);
        $this->assertSame('post', ContentType::POST->value);
        $this->assertSame('testimonial', ContentType::TESTIMONIAL->value);
        $this->assertSame('faq', ContentType::FAQ->value);
    }

    public function test_can_be_resolved_from_persisted_value(): void
    {
        $this->assertSame(ContentType::POST, ContentType::from('post'));
    }
}
