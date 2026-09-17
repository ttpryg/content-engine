<?php

namespace Ttpryg\ContentEngine\ValueObjects;

class TestimonialMeta
{
    public function __construct(
        public readonly ?string $company = null,
        public readonly ?string $position = null,
        public readonly ?int $rating = null,
        public readonly ?string $avatarUrl = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            company: $data['company'] ?? null,
            position: $data['position'] ?? null,
            rating: isset($data['rating']) ? (int) $data['rating'] : null,
            avatarUrl: $data['avatar_url'] ?? null
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'company' => $this->company,
            'position' => $this->position,
            'rating' => $this->rating,
            'avatar_url' => $this->avatarUrl,
        ], fn ($val) => $val !== null);
    }
}
