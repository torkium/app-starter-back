<?php

declare(strict_types=1);

namespace App\Identity\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'user_action_tokens')]
class UserActionToken
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash;

    #[ORM\Column(length: 32)]
    private string $type;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $payload;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $usedAt = null;

    public function __construct(string $id, User $user, string $tokenHash, string $type, \DateTimeImmutable $expiresAt, ?array $payload = null)
    {
        $this->id = $id;
        $this->user = $user;
        $this->tokenHash = $tokenHash;
        $this->type = $type;
        $this->expiresAt = $expiresAt;
        $this->payload = $payload;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPayload(): ?array
    {
        return $this->payload;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getUsedAt(): ?\DateTimeImmutable
    {
        return $this->usedAt;
    }

    public function isConsumable(\DateTimeImmutable $now): bool
    {
        return null === $this->usedAt && $this->expiresAt >= $now;
    }

    public function markUsed(\DateTimeImmutable $usedAt): void
    {
        $this->usedAt = $usedAt;
    }

    public function matchesTokenValue(string $tokenValue): bool
    {
        return hash_equals($this->tokenHash, hash('sha256', $tokenValue));
    }
}
