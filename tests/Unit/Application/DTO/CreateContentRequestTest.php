<?php

declare(strict_types=1);

namespace Ttpryg\ContentEngine\Tests\Unit\Application\DTO;

use PHPUnit\Framework\TestCase;
use Ttpryg\ContentEngine\Application\DTO\CreateContentRequest;

class CreateContentRequestTest extends TestCase
{
    public function test_defaults_are_safe(): void
    {
        $request = new CreateContentRequest(title: 'Hello');

        $this->assertSame('post', $request->type);
        $this->assertSame('draft', $request->status);
        $this->assertSame([], $request->meta);
        $this->assertSame(0, $request->sortOrder);
        $this->assertNull($request->tenantType);
        $this->assertNull($request->tenantId);
    }
}
