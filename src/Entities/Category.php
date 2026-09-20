<?php

namespace Ttpryg\ContentEngine\Entities;

use DateTimeImmutable;
use DateTimeInterface;

class Category
{
    private int|string|null $id;

    private string $name;

    private string $slug;

    private string $type;

    private ?DateTimeInterface $createdAt;

    public function __construct(
        string $name,
        string $slug,
        string $type = 'category',
        int|string|null $id = null,
        ?DateTimeInterface $createdAt = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->slug = $slug;
        $this->type = $type;
        $this->createdAt = $createdAt ?? new DateTimeImmutable;
    }

    public function getId(): int|string|null
    {
        return $this->id;
    }

    public function setId(int|string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type,
            'created_at' => $this->createdAt?->format(DateTimeInterface::ATOM),
        ];
    }
}
