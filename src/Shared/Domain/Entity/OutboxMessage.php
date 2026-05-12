<?php

declare(strict_types=1);

namespace App\Shared\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'outbox_messages')]
#[ORM\Index(name: 'idx_outbox_claim_token', columns: ['claim_token'])]
#[ORM\Index(name: 'idx_outbox_pending_claim', columns: ['published_at', 'failed_at', 'delivery_started_at', 'claim_token', 'next_attempt_at', 'created_at'])]
#[ORM\Index(name: 'idx_outbox_delivery_started', columns: ['delivery_started_at'])]
#[ORM\Index(name: 'idx_outbox_failed_at', columns: ['failed_at'])]
#[ORM\Index(name: 'idx_outbox_next_attempt', columns: ['next_attempt_at'])]
class OutboxMessage
{
    public const MAX_DELIVERY_ATTEMPTS = 5;
    private const SENSITIVE_PAYLOAD_KEYS = [
        'authorization',
        'cookie',
        'jwt',
        'password',
        'passwordhash',
        'token',
        'refreshtoken',
        'secret',
        'signature',
        'apikey',
        'code',
    ];

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

    #[ORM\Column(options: ['default' => 0])]
    private int $attemptCount = 0;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $nextAttemptAt = null;

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

    public function getAttemptCount(): int
    {
        return $this->attemptCount;
    }

    public function getNextAttemptAt(): ?\DateTimeImmutable
    {
        return $this->nextAttemptAt;
    }

    public function startDelivery(\DateTimeImmutable $startedAt): bool
    {
        if (null !== $this->deliveryStartedAt || $this->isPublished() || $this->isFailed()) {
            return false;
        }

        $this->deliveryStartedAt = $startedAt;
        ++$this->attemptCount;

        return true;
    }

    public function markFailed(\DateTimeImmutable $failedAt, string $reason): void
    {
        $this->failedAt = $failedAt;
        $this->failureReason = mb_substr($reason, 0, 255);
        $this->deliveryStartedAt = null;
        $this->nextAttemptAt = null;
        $this->releaseClaim();
    }

    public function scheduleRetry(\DateTimeImmutable $failedAt, string $reason): bool
    {
        if ($this->attemptCount >= self::MAX_DELIVERY_ATTEMPTS) {
            $this->markFailed($failedAt, $reason);

            return false;
        }

        $this->deliveryStartedAt = null;
        $this->failedAt = null;
        $this->failureReason = mb_substr($reason, 0, 255);
        $delaySeconds = min(3600, 60 * (2 ** max(0, $this->attemptCount - 1)));
        $this->nextAttemptAt = $failedAt->modify(sprintf('+%d seconds', $delaySeconds));
        $this->releaseClaim();

        return true;
    }

    public function scrubSensitivePayload(): void
    {
        $this->payload = $this->scrubValue($this->payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function replacePayload(array $payload): void
    {
        $this->payload = $payload;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function scrubValue(array $payload): array
    {
        foreach ($payload as $key => $value) {
            $normalizedKey = strtolower(str_replace(['-', '_'], '', (string) $key));
            foreach (self::SENSITIVE_PAYLOAD_KEYS as $sensitiveKey) {
                if (str_contains($normalizedKey, $sensitiveKey)) {
                    $payload[$key] = '***';
                    continue 2;
                }
            }

            if (is_array($value)) {
                $payload[$key] = $this->scrubValue($value);
                continue;
            }

            if (is_string($value)) {
                $payload[$key] = preg_replace('/([?&][^=&#]*(?:token|code|secret|signature|credential)[^=&#]*=)[^&#]+/i', '$1***', $value) ?? $value;
            }
        }

        return $payload;
    }

    public function markPublished(\DateTimeImmutable $publishedAt): void
    {
        $this->publishedAt = $publishedAt;
        $this->deliveryStartedAt = null;
        $this->failedAt = null;
        $this->failureReason = null;
        $this->nextAttemptAt = null;
        $this->releaseClaim();
    }
}
