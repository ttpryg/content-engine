<?php

declare(strict_types=1);

namespace Ttpryg\ContentEngine\Tests\Unit\Domain\Entity;

use PHPUnit\Framework\TestCase;
use Ttpryg\ContentEngine\Domain\Entity\Content;
use Ttpryg\ContentEngine\Domain\Enum\ContentStatus;
use Ttpryg\ContentEngine\Domain\Exception\InvalidContentStatusException;
use Ttpryg\ContentEngine\Domain\ValueObject\TestimonialMeta;

class ContentEntityTest extends TestCase
{
    // POSITIVE CASE: Entity Getters and Meta Object Wrapping
    public function test_content_creation_and_meta_wrapping(): void
    {
        $testimonialMeta = new TestimonialMeta(
            company: 'Acme Corp',
            position: 'CEO',
            rating: 5
        );

        $content = new Content(
            title: 'John Doe Testimonial',
            type: 'testimonial',
            slug: 'john-doe-testimonial',
            body: 'Great service!',
            meta: $testimonialMeta->toArray(),
            status: ContentStatus::PUBLISHED,
            sortOrder: 1,
            id: 10
        );

        $this->assertEquals(10, $content->getId());
        $this->assertEquals('testimonial', $content->getType());
        $this->assertEquals('John Doe Testimonial', $content->getTitle());
        $this->assertEquals('published', $content->getStatus());

        $extractedMeta = TestimonialMeta::fromArray($content->getMeta());
        $this->assertEquals('Acme Corp', $extractedMeta->company);
        $this->assertEquals('CEO', $extractedMeta->position);
        $this->assertEquals(5, $extractedMeta->rating);
    }

    // NEGATIVE CASE: Invalid Status Throws Exception Instead of Silent Fallback
    public function test_invalid_status_throws_exception(): void
    {
        $this->expectException(InvalidContentStatusException::class);

        new Content(
            title: 'Some Page',
            status: 'invalid_status_name'
        );
    }

    // POSITIVE CASE: setStatus Accepts Backed Enum
    public function test_set_status_accepts_backed_enum(): void
    {
        $content = new Content(title: 'Some Page');

        $content->setStatus(ContentStatus::PUBLISHED);

        $this->assertSame('published', $content->getStatus());
    }

    // POSITIVE CASE: explicit lifecycle transitions
    public function test_publish_and_archive_transition_status(): void
    {
        $content = new Content(title: 'Some Page');

        $content->publish();
        $this->assertSame('published', $content->getStatus());

        $content->archive();
        $this->assertSame('archived', $content->getStatus());

        $content->publish();
        $this->assertSame('published', $content->getStatus());
    }

    // NEGATIVE CASE: setStatus Rejects Invalid String
    public function test_set_status_rejects_invalid_string(): void
    {
        $content = new Content(title: 'Some Page');

        $this->expectException(InvalidContentStatusException::class);

        $content->setStatus('invalid_status_name');
    }
}
