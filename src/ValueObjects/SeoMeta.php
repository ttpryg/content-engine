<?php

namespace Ttpryg\ContentEngine\ValueObjects;

class SeoMeta
{
    public function __construct(
        public readonly ?string $metaTitle = null,
        public readonly ?string $metaDescription = null,
        public readonly ?string $ogImage = null,
        public readonly ?array $keywords = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            metaTitle: $data['meta_title'] ?? null,
            metaDescription: $data['meta_description'] ?? null,
            ogImage: $data['og_image'] ?? null,
            keywords: $data['keywords'] ?? null
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'meta_title' => $this->metaTitle,
            'meta_description' => $this->metaDescription,
            'og_image' => $this->ogImage,
            'keywords' => $this->keywords,
        ], fn ($val) => $val !== null);
    }
}
