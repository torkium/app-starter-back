<?php

declare(strict_types=1);

namespace App\Billing\Domain\Entity;

use App\Identity\Domain\Entity\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'billing_events')]
#[ORM\UniqueConstraint(name: 'uniq_billing_event_external_id', columns: ['external_id'])]
class BillingEvent
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user;

    #[ORM\Column(length: 180)]
    private string $externalId;

    #[ORM\Column(length: 120)]
    private string $type;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $payload;

    #[ORM\Column]
    private \DateTimeImmutable $occurredAt;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        string $id,
        ?User $user,
        string $externalId,
        string $type,
        array $payload,
        \DateTimeImmutable $occurredAt,
        \DateTimeImmutable $createdAt,
    ) {
        $this->id = $id;
        $this->user = $user;
        $this->externalId = $externalId;
        $this->type = $type;
        $this->payload = $payload;
        $this->occurredAt = $occurredAt;
        $this->createdAt = $createdAt;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function toView(): array
    {
        return [
            'externalId' => $this->externalId,
            'type' => $this->type,
            'occurredAt' => $this->occurredAt->format(\DATE_ATOM),
            'payload' => $this->payload,
        ];
    }
}
