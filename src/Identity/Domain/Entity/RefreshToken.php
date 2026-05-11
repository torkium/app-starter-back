<?php

declare(strict_types=1);

namespace App\Identity\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'refresh_tokens')]
class RefreshToken
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $deviceName = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $lastUsedUserAgent = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $lastUsedIp = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $revokedReason = null;

    public function __construct(
        string $id,
        User $user,
        string $tokenHash,
        \DateTimeImmutable $expiresAt,
        \DateTimeImmutable $createdAt,
        ?string $deviceName = null,
        ?string $lastUsedUserAgent = null,
        ?string $lastUsedIp = null,
    )
    {
        $this->id = $id;
        $this->user = $user;
        $this->tokenHash = $tokenHash;
        $this->expiresAt = $expiresAt;
        $this->createdAt = $createdAt;
        $this->deviceName = null !== $deviceName ? trim($deviceName) : null;
        $this->lastUsedUserAgent = null !== $lastUsedUserAgent ? mb_substr($lastUsedUserAgent, 0, 500) : null;
        $this->lastUsedIp = $lastUsedIp;
        $this->lastUsedAt = $createdAt;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function matchesTokenValue(string $tokenValue): bool
    {
        return hash_equals($this->tokenHash, hash('sha256', $tokenValue));
    }

    public function isActive(\DateTimeImmutable $now): bool
    {
        return null === $this->revokedAt && $this->expiresAt >= $now;
    }

    public function revoke(\DateTimeImmutable $at, ?string $reason = null): void
    {
        $this->revokedAt = $at;
        $this->revokedReason = null !== $reason ? mb_substr($reason, 0, 120) : null;
    }

    public function touch(\DateTimeImmutable $at, ?string $userAgent = null, ?string $ipAddress = null): void
    {
        $this->lastUsedAt = $at;
        if (null !== $userAgent && '' !== trim($userAgent)) {
            $this->lastUsedUserAgent = mb_substr($userAgent, 0, 500);
        }

        if (null !== $ipAddress && '' !== trim($ipAddress)) {
            $this->lastUsedIp = $ipAddress;
        }
    }

    public function getDeviceName(): ?string
    {
        return $this->deviceName;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function getLastUsedUserAgent(): ?string
    {
        return $this->lastUsedUserAgent;
    }

    public function getLastUsedIp(): ?string
    {
        return $this->lastUsedIp;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function getRevokedReason(): ?string
    {
        return $this->revokedReason;
    }
}
