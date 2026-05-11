<?php

declare(strict_types=1);

namespace App\Identity\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'user_consents')]
#[ORM\UniqueConstraint(name: 'uniq_user_consent_document', columns: ['user_id', 'document_id'])]
class UserConsent
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: LegalDocument::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private LegalDocument $document;

    #[ORM\Column(nullable: true, length: 45)]
    private ?string $ipAddress;

    #[ORM\Column(nullable: true, length: 500)]
    private ?string $userAgent;

    #[ORM\Column]
    private \DateTimeImmutable $acceptedAt;

    public function __construct(
        string $id,
        User $user,
        LegalDocument $document,
        \DateTimeImmutable $acceptedAt,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ) {
        $this->id = $id;
        $this->user = $user;
        $this->document = $document;
        $this->acceptedAt = $acceptedAt;
        $this->ipAddress = $ipAddress;
        $this->userAgent = null !== $userAgent ? mb_substr($userAgent, 0, 500) : null;
    }

    public function getDocument(): LegalDocument
    {
        return $this->document;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getAcceptedAt(): \DateTimeImmutable
    {
        return $this->acceptedAt;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }
}
