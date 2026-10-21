<?php

declare(strict_types=1);

namespace Ttpryg\ContentEngine\Domain\ValueObject;

final class TestimonialMeta
{
    public function __construct(
        public readonly ?string $company = null,
        public readonly ?string $position = null,
        public readonly ?int $rating = null,
        public readonly ?string $avatarUrl = null
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            company: self::stringOrNull($data['company'] ?? null),
            position: self::stringOrNull($data['position'] ?? null),
            rating: self::intOrNull($data['rating'] ?? null),
            avatarUrl: self::stringOrNull($data['avatar_url'] ?? null)
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'company' => $this->company,
            'position' => $this->position,
            'rating' => $this->rating,
            'avatar_url' => $this->avatarUrl,
        ], fn (int|string|null $val): bool => $val !== null);
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private static function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
