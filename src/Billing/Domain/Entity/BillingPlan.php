<?php

declare(strict_types=1);

namespace App\Billing\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'billing_plans')]
#[ORM\UniqueConstraint(name: 'uniq_billing_plan_code', columns: ['code'])]
class BillingPlan
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(length: 80)]
    private string $code;

    #[ORM\Column(length: 180)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description;

    #[ORM\Column]
    private int $amountMinor;

    #[ORM\Column(length: 3)]
    private string $currency;

    #[ORM\Column(length: 20)]
    private string $intervalUnit;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $stripePriceId;

    #[ORM\Column]
    private bool $active;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id,
        string $code,
        string $name,
        ?string $description,
        int $amountMinor,
        string $currency,
        string $intervalUnit,
        bool $active,
        \DateTimeImmutable $createdAt,
        ?string $stripePriceId = null,
    ) {
        $this->id = $id;
        $this->code = $code;
        $this->name = $name;
        $this->description = $description;
        $this->amountMinor = $amountMinor;
        $this->currency = strtolower($currency);
        $this->intervalUnit = $intervalUnit;
        $this->active = $active;
        $this->createdAt = $createdAt;
        $this->stripePriceId = $stripePriceId;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getAmountMinor(): int
    {
        return $this->amountMinor;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getIntervalUnit(): string
    {
        return $this->intervalUnit;
    }

    public function getStripePriceId(): ?string
    {
        return $this->stripePriceId;
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}
