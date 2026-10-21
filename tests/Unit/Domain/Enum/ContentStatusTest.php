<?php

declare(strict_types=1);

namespace Ttpryg\ContentEngine\Tests\Unit\Domain\Enum;

use PHPUnit\Framework\TestCase;
use Ttpryg\ContentEngine\Domain\Enum\ContentStatus;

class ContentStatusTest extends TestCase
{
    public function test_values_match_persisted_statuses(): void
    {
        $this->assertSame('draft', ContentStatus::DRAFT->value);
        $this->assertSame('published', ContentStatus::PUBLISHED->value);
        $this->assertSame('archived', ContentStatus::ARCHIVED->value);
    }

    public function test_is_valid_only_accepts_known_statuses(): void
    {
        $this->assertTrue(ContentStatus::isValid('draft'));
        $this->assertTrue(ContentStatus::isValid('published'));
        $this->assertTrue(ContentStatus::isValid('archived'));
        $this->assertFalse(ContentStatus::isValid('deleted'));
    }
}
