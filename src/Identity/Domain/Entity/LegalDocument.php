<?php

declare(strict_types=1);

namespace App\Identity\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[UniqueEntity(fields: ['code', 'version', 'locale'], message: 'This legal document version already exists for the locale.')]
#[ORM\Entity]
#[ORM\Table(name: 'legal_documents')]
#[ORM\UniqueConstraint(name: 'uniq_legal_document_code_version_locale', columns: ['code', 'version', 'locale'])]
class LegalDocument
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(length: 80)]
    private string $code;

    #[ORM\Column(length: 32)]
    private string $version;

    #[ORM\Column(length: 10)]
    private string $locale;

    #[ORM\Column(length: 180)]
    private string $title;

    #[ORM\Column(type: 'text')]
    private string $content;

    #[ORM\Column]
    private bool $active;

    #[ORM\Column]
    private \DateTimeImmutable $publishedAt;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id,
        string $code,
        string $version,
        string $locale,
        string $title,
        string $content,
        bool $active,
        \DateTimeImmutable $publishedAt,
        \DateTimeImmutable $createdAt,
    ) {
        $this->id = $id;
        $this->code = $code;
        $this->version = $version;
        $this->locale = strtolower($locale);
        $this->title = $title;
        $this->content = $content;
        $this->active = $active;
        $this->publishedAt = $publishedAt;
        $this->createdAt = $createdAt;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function __toString(): string
    {
        return sprintf('%s %s (%s)', $this->code, $this->version, $this->locale);
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getPublishedAt(): \DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCode(string $code): void
    {
        $this->code = trim($code);
    }

    public function setVersion(string $version): void
    {
        $this->version = trim($version);
    }

    public function setLocale(string $locale): void
    {
        $this->locale = strtolower(trim($locale));
    }

    public function setTitle(string $title): void
    {
        $this->title = trim($title);
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function setPublishedAt(\DateTimeImmutable $publishedAt): void
    {
        $this->publishedAt = $publishedAt;
    }
}
