<?php

declare(strict_types=1);

namespace Ttpryg\ContentEngine\Domain\ValueObject;

final class SeoMeta
{
    /** @param array<mixed>|null $keywords */
    public function __construct(
        public readonly ?string $metaTitle = null,
        public readonly ?string $metaDescription = null,
        public readonly ?string $ogImage = null,
        public readonly ?array $keywords = null
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $keywords = $data['keywords'] ?? null;

        return new self(
            metaTitle: self::stringOrNull($data['meta_title'] ?? null),
            metaDescription: self::stringOrNull($data['meta_description'] ?? null),
            ogImage: self::stringOrNull($data['og_image'] ?? null),
            keywords: is_array($keywords) ? $keywords : null
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'meta_title' => $this->metaTitle,
            'meta_description' => $this->metaDescription,
            'og_image' => $this->ogImage,
            'keywords' => $this->keywords,
        ], fn (string|array|null $val): bool => $val !== null);
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
