<?php

declare(strict_types=1);

namespace App\Notification\Domain\Entity;

use App\Identity\Domain\Entity\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'push_subscriptions')]
#[ORM\UniqueConstraint(name: 'uniq_push_subscription_endpoint', columns: ['endpoint'])]
class PushSubscription
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 500)]
    private string $endpoint;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $p256dh;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $auth;

    #[ORM\Column(nullable: true)]
    private ?int $expirationTime;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        string $id,
        User $user,
        string $endpoint,
        ?string $p256dh,
        ?string $auth,
        ?int $expirationTime,
        \DateTimeImmutable $createdAt,
    ) {
        $this->id = $id;
        $this->user = $user;
        $this->endpoint = mb_substr($endpoint, 0, 500);
        $this->p256dh = null !== $p256dh ? mb_substr($p256dh, 0, 255) : null;
        $this->auth = null !== $auth ? mb_substr($auth, 0, 255) : null;
        $this->expirationTime = $expirationTime;
        $this->createdAt = $createdAt;
        $this->updatedAt = $createdAt;
    }

    public function belongsTo(User $user): bool
    {
        return $this->user->getId() === $user->getId();
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function touch(?string $p256dh, ?string $auth, ?int $expirationTime, \DateTimeImmutable $updatedAt): void
    {
        $this->p256dh = null !== $p256dh ? mb_substr($p256dh, 0, 255) : null;
        $this->auth = null !== $auth ? mb_substr($auth, 0, 255) : null;
        $this->expirationTime = $expirationTime;
        $this->updatedAt = $updatedAt;
    }

    public function reassignTo(User $user, ?string $p256dh, ?string $auth, ?int $expirationTime, \DateTimeImmutable $updatedAt): void
    {
        $this->user = $user;
        $this->touch($p256dh, $auth, $expirationTime, $updatedAt);
    }

    /**
     * @return array<string, mixed>
     */
    public function toView(): array
    {
        return [
            'subscribed' => true,
            'endpoint' => $this->endpoint,
            'updatedAt' => $this->updatedAt->format(\DATE_ATOM),
        ];
    }
}
