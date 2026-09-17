<?php

namespace Ttpryg\ContentEngine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ttpryg\ContentEngine\Entities\Content;
use Ttpryg\ContentEngine\ValueObjects\SeoMeta;
use Ttpryg\ContentEngine\ValueObjects\TestimonialMeta;

class ContentEntityTest extends TestCase
{
    // POSITIVE CASE: Entity Getters and Meta Object Wrapping
    public function testContentCreationAndMetaWrapping(): void
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
            status: 'published',
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

    // NEGATIVE CASE: Invalid Status Fallbacks to Draft
    public function testInvalidStatusDefaultsToDraft(): void
    {
        $content = new Content(
            title: 'Some Page',
            status: 'invalid_status_name'
        );

        $this->assertEquals('draft', $content->getStatus());
    }
}
