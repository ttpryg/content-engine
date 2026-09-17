<?php

namespace Ttpryg\ContentEngine\Contracts;

use DateTimeInterface;

interface ContentInterface
{
    public function getId(): int|string|null;
    public function getType(): string;
    public function getTitle(): string;
    public function getSlug(): ?string;
    public function getSummary(): ?string;
    public function getBody(): ?string;
    public function getMeta(): array;
    public function getStatus(): string;
    public function getSortOrder(): int;
    public function getViewCount(): int;
    public function getAuthorId(): int|string|null;
    public function getPublishedAt(): ?DateTimeInterface;
    public function getCreatedAt(): ?DateTimeInterface;
    public function getUpdatedAt(): ?DateTimeInterface;
    public function getDeletedAt(): ?DateTimeInterface;
}
