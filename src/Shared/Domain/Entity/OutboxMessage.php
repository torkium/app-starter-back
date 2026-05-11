<?php

declare(strict_types=1);

namespace App\Shared\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'outbox_messages')]
class OutboxMessage
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(length: 180)]
    private string $topic;

    #[ORM\Column(length: 32)]
    private string $channel;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $payload;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(length: 36, nullable: true)]
    private ?string $claimToken = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $claimedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deliveryStartedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $failedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $failureReason = null;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(string $id, string $topic, string $channel, array $payload, \DateTimeImmutable $createdAt)
    {
        $this->id = $id;
        $this->topic = $topic;
        $this->channel = $channel;
        $this->payload = $payload;
        $this->createdAt = $createdAt;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTopic(): string
    {
        return $this->topic;
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isPublished(): bool
    {
        return null !== $this->publishedAt;
    }

    public function isFailed(): bool
    {
        return null !== $this->failedAt;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function claim(string $claimToken, \DateTimeImmutable $claimedAt): void
    {
        $this->claimToken = $claimToken;
        $this->claimedAt = $claimedAt;
    }

    public function releaseClaim(): void
    {
        $this->claimToken = null;
        $this->claimedAt = null;
    }

    public function isClaimedBy(string $claimToken): bool
    {
        return null !== $this->claimToken && hash_equals($this->claimToken, $claimToken);
    }

    public function getClaimToken(): ?string
    {
        return $this->claimToken;
    }

    public function getClaimedAt(): ?\DateTimeImmutable
    {
        return $this->claimedAt;
    }

    public function getDeliveryStartedAt(): ?\DateTimeImmutable
    {
        return $this->deliveryStartedAt;
    }

    public function getFailedAt(): ?\DateTimeImmutable
    {
        return $this->failedAt;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function startDelivery(\DateTimeImmutable $startedAt): bool
    {
        if (null !== $this->deliveryStartedAt || $this->isPublished() || $this->isFailed()) {
            return false;
        }

        $this->deliveryStartedAt = $startedAt;

        return true;
    }

    public function markFailed(\DateTimeImmutable $failedAt, string $reason): void
    {
        $this->failedAt = $failedAt;
        $this->failureReason = mb_substr($reason, 0, 255);
        $this->releaseClaim();
    }

    public function resetForRetry(): void
    {
        $this->deliveryStartedAt = null;
        $this->failedAt = null;
        $this->failureReason = null;
        $this->releaseClaim();
    }

    public function markPublished(\DateTimeImmutable $publishedAt): void
    {
        $this->publishedAt = $publishedAt;
        $this->deliveryStartedAt = null;
        $this->failedAt = null;
        $this->failureReason = null;
        $this->releaseClaim();
    }
}
