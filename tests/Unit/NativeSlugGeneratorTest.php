<?php

namespace Ttpryg\ContentEngine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ttpryg\ContentEngine\Utilities\NativeSlugGenerator;

class NativeSlugGeneratorTest extends TestCase
{
    private NativeSlugGenerator $nativeSlugGenerator;

    protected function setUp(): void
    {
        $this->nativeSlugGenerator = new NativeSlugGenerator;
    }

    // POSITIVE CASE
    public function test_generate_slug_from_title(): void
    {
        $title = 'Hello World! This is a CMS Engine.';
        $slug = $this->nativeSlugGenerator->generate($title);

        $this->assertEquals('hello-world-this-is-a-cms-engine', $slug);
    }

    // POSITIVE CASE with special characters & accents
    public function test_generate_slug_with_special_chars(): void
    {
        $title = '  Judul Artikel: 100% Bagus & Mantap!!  ';
        $slug = $this->nativeSlugGenerator->generate($title);

        $this->assertEquals('judul-artikel-100-bagus-mantap', $slug);
    }

    // NEGATIVE/FALLBACK CASE: Empty String Title
    public function test_generate_slug_fallback_on_empty_title(): void
    {
        $slug = $this->nativeSlugGenerator->generate('!!! ');
        $this->assertEquals('n-a', $slug);
    }
}
