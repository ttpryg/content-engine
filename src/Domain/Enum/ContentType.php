<?php

declare(strict_types=1);

namespace Ttpryg\ContentEngine\Domain\Enum;

enum ContentType: string
{
    case PAGE = 'page';
    case POST = 'post';
    case TESTIMONIAL = 'testimonial';
    case FAQ = 'faq';
}
