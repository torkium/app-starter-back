<?php

declare(strict_types=1);

namespace App\Billing\Domain\Entity;

use App\Identity\Domain\Entity\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'user_subscriptions')]
#[ORM\UniqueConstraint(name: 'uniq_subscription_user', columns: ['user_id'])]
#[ORM\UniqueConstraint(name: 'uniq_subscription_stripe_subscription', columns: ['stripe_subscription_id'])]
class UserSubscription
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: BillingPlan::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private BillingPlan $plan;

    #[ORM\Column(length: 40)]
    private string $status;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $stripeCustomerId = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $stripeSubscriptionId = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $currentPeriodStart = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $currentPeriodEnd = null;

    #[ORM\Column]
    private bool $cancelAtPeriodEnd = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $id, User $user, BillingPlan $plan, string $status, \DateTimeImmutable $createdAt)
    {
        $this->id = $id;
        $this->user = $user;
        $this->plan = $plan;
        $this->status = $status;
        $this->createdAt = $createdAt;
        $this->updatedAt = $createdAt;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getPlan(): BillingPlan
    {
        return $this->plan;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getStripeCustomerId(): ?string
    {
        return $this->stripeCustomerId;
    }

    public function getStripeSubscriptionId(): ?string
    {
        return $this->stripeSubscriptionId;
    }

    public function getCurrentPeriodStart(): ?\DateTimeImmutable
    {
        return $this->currentPeriodStart;
    }

    public function getCurrentPeriodEnd(): ?\DateTimeImmutable
    {
        return $this->currentPeriodEnd;
    }

    public function isCancelAtPeriodEnd(): bool
    {
        return $this->cancelAtPeriodEnd;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function sync(
        BillingPlan $plan,
        string $status,
        ?string $stripeCustomerId,
        ?string $stripeSubscriptionId,
        ?\DateTimeImmutable $currentPeriodStart,
        ?\DateTimeImmutable $currentPeriodEnd,
        bool $cancelAtPeriodEnd,
        \DateTimeImmutable $updatedAt,
    ): void {
        $this->plan = $plan;
        $this->status = $status;
        $this->stripeCustomerId = $stripeCustomerId;
        $this->stripeSubscriptionId = $stripeSubscriptionId;
        $this->currentPeriodStart = $currentPeriodStart;
        $this->currentPeriodEnd = $currentPeriodEnd;
        $this->cancelAtPeriodEnd = $cancelAtPeriodEnd;
        $this->updatedAt = $updatedAt;
    }

    public function toView(): array
    {
        return [
            'plan' => [
                'code' => $this->plan->getCode(),
                'name' => $this->plan->getName(),
                'interval' => $this->plan->getIntervalUnit(),
            ],
            'status' => $this->status,
            'stripeCustomerId' => $this->stripeCustomerId,
            'stripeSubscriptionId' => $this->stripeSubscriptionId,
            'currentPeriodStart' => $this->currentPeriodStart?->format(\DATE_ATOM),
            'currentPeriodEnd' => $this->currentPeriodEnd?->format(\DATE_ATOM),
            'cancelAtPeriodEnd' => $this->cancelAtPeriodEnd,
            'updatedAt' => $this->updatedAt->format(\DATE_ATOM),
        ];
    }

    public function toFrontView(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'planCode' => $this->plan->getCode(),
            'planName' => $this->plan->getName(),
            'renewsAt' => $this->currentPeriodEnd?->format(\DATE_ATOM),
            'cancelAtPeriodEnd' => $this->cancelAtPeriodEnd,
            'amount' => $this->plan->getAmountMinor(),
            'currency' => strtoupper($this->plan->getCurrency()),
        ];
    }
}
