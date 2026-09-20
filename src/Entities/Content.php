<?php

namespace Ttpryg\ContentEngine\Entities;

use DateTimeImmutable;
use DateTimeInterface;
use Ttpryg\ContentEngine\Contracts\ContentInterface;
use Ttpryg\ContentEngine\ValueObjects\ContentStatus;

class Content implements ContentInterface
{
    private int|string|null $id;

    private string $type;

    private string $title;

    private ?string $slug;

    private ?string $summary;

    private ?string $body;

    private array $meta;

    private string $status;

    private int $sortOrder;

    private int $viewCount;

    private int|string|null $authorId;

    private ?DateTimeInterface $publishedAt;

    private ?DateTimeInterface $createdAt;

    private ?DateTimeInterface $updatedAt;

    private ?DateTimeInterface $deletedAt;

    public function __construct(
        string $title,
        string $type = 'post',
        ?string $slug = null,
        ?string $summary = null,
        ?string $body = null,
        array $meta = [],
        string $status = 'draft',
        int $sortOrder = 0,
        int $viewCount = 0,
        int|string|null $authorId = null,
        ?DateTimeInterface $publishedAt = null,
        int|string|null $id = null,
        ?DateTimeInterface $createdAt = null,
        ?DateTimeInterface $updatedAt = null,
        ?DateTimeInterface $deletedAt = null
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->type = $type;
        $this->slug = $slug;
        $this->summary = $summary;
        $this->body = $body;
        $this->meta = $meta;
        $this->status = ContentStatus::isValid($status) ? $status : ContentStatus::DRAFT->value;
        $this->sortOrder = $sortOrder;
        $this->viewCount = $viewCount;
        $this->authorId = $authorId;
        $this->publishedAt = $publishedAt;
        $this->createdAt = $createdAt ?? new DateTimeImmutable;
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable;
        $this->deletedAt = $deletedAt;
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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(?string $summary): self
    {
        $this->summary = $summary;

        return $this;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setBody(?string $body): self
    {
        $this->body = $body;

        return $this;
    }

    public function getMeta(): array
    {
        return $this->meta;
    }

    public function setMeta(array $meta): self
    {
        $this->meta = $meta;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        if (ContentStatus::isValid($status)) {
            $this->status = $status;
        }

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function getViewCount(): int
    {
        return $this->viewCount;
    }

    public function setViewCount(int $viewCount): self
    {
        $this->viewCount = $viewCount;

        return $this;
    }

    public function getAuthorId(): int|string|null
    {
        return $this->authorId;
    }

    public function setAuthorId(int|string|null $authorId): self
    {
        $this->authorId = $authorId;

        return $this;
    }

    public function getPublishedAt(): ?DateTimeInterface
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?DateTimeInterface $publishedAt): self
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getDeletedAt(): ?DateTimeInterface
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?DateTimeInterface $deletedAt): self
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'slug' => $this->slug,
            'summary' => $this->summary,
            'body' => $this->body,
            'meta' => $this->meta,
            'status' => $this->status,
            'sort_order' => $this->sortOrder,
            'view_count' => $this->viewCount,
            'author_id' => $this->authorId,
            'published_at' => $this->publishedAt?->format(DateTimeInterface::ATOM),
            'created_at' => $this->createdAt?->format(DateTimeInterface::ATOM),
            'updated_at' => $this->updatedAt?->format(DateTimeInterface::ATOM),
            'deleted_at' => $this->deletedAt?->format(DateTimeInterface::ATOM),
        ];
    }
}
