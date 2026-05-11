<?php

declare(strict_types=1);

namespace App\Billing\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[UniqueEntity(fields: ['code'], message: 'This billing plan code is already used.')]
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

    public function __toString(): string
    {
        return sprintf('%s (%s)', $this->name, $this->code);
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCode(string $code): void
    {
        $this->code = trim($code);
    }

    public function setName(string $name): void
    {
        $this->name = trim($name);
    }

    public function setDescription(?string $description): void
    {
        $this->description = null !== $description ? trim($description) : null;
    }

    public function setAmountMinor(int $amountMinor): void
    {
        $this->amountMinor = $amountMinor;
    }

    public function setCurrency(string $currency): void
    {
        $this->currency = strtolower(trim($currency));
    }

    public function setIntervalUnit(string $intervalUnit): void
    {
        $this->intervalUnit = trim($intervalUnit);
    }

    public function setStripePriceId(?string $stripePriceId): void
    {
        $this->stripePriceId = null !== $stripePriceId ? trim($stripePriceId) : null;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }
}
