<?php

declare(strict_types=1);

namespace UI\UiPermissions\Dto;

class FileMountDto extends AbstractDto
{
    protected string $description;
    protected string $identifier = '';
    protected bool $readOnly;
    protected string $title;

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getReadOnlyProcessedForDatabase(): int
    {
        return (int)$this->readOnly;
    }

    public function isReadOnly(): bool
    {
        return $this->readOnly;
    }

    public function setReadOnly(bool|int|string $readOnly): void
    {
        $this->readOnly = (bool)$readOnly;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }
}
