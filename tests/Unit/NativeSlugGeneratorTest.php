<?php

namespace Ttpryg\ContentEngine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ttpryg\ContentEngine\Utilities\NativeSlugGenerator;

class NativeSlugGeneratorTest extends TestCase
{
    private NativeSlugGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new NativeSlugGenerator();
    }

    // POSITIVE CASE
    public function testGenerateSlugFromTitle(): void
    {
        $title = "Hello World! This is a CMS Engine.";
        $slug = $this->generator->generate($title);

        $this->assertEquals("hello-world-this-is-a-cms-engine", $slug);
    }

    // POSITIVE CASE with special characters & accents
    public function testGenerateSlugWithSpecialChars(): void
    {
        $title = "  Judul Artikel: 100% Bagus & Mantap!!  ";
        $slug = $this->generator->generate($title);

        $this->assertEquals("judul-artikel-100-bagus-mantap", $slug);
    }

    // NEGATIVE/FALLBACK CASE: Empty String Title
    public function testGenerateSlugFallbackOnEmptyTitle(): void
    {
        $slug = $this->generator->generate("!!! ");
        $this->assertEquals("n-a", $slug);
    }
}
