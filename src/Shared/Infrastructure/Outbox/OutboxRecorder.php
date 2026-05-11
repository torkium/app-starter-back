<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Outbox;

use App\Shared\Application\Port\ClockInterface;
use App\Shared\Domain\Entity\OutboxMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class OutboxRecorder
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function record(string $topic, string $channel, array $payload): string
    {
        $message = new OutboxMessage(Uuid::v7()->toRfc4122(), $topic, $channel, $payload, $this->clock->now());
        $this->entityManager->persist($message);

        return $message->getId();
    }
}
