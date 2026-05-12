<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Outbox;

use App\Shared\Application\Port\ClockInterface;
use App\Shared\Application\Port\OutboxRecorderInterface;
use App\Shared\Infrastructure\Http\SensitiveValueSanitizer;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Uid\Uuid;

final readonly class OutboxRecorder implements OutboxRecorderInterface
{
    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
        private OutboxPayloadProtector $payloadProtector,
        private SensitiveValueSanitizer $sensitiveValueSanitizer,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function record(string $topic, string $channel, array $payload): string
    {
        $id = Uuid::v7()->toRfc4122();
        if ('mail' === $channel) {
            $payload = $this->payloadProtector->protect($payload);
        } else {
            $payload = $this->sensitiveValueSanitizer->sanitizeArray($payload);
        }

        $this->connection->insert('outbox_messages', [
            'id' => $id,
            'topic' => $topic,
            'channel' => $channel,
            'payload' => $payload,
            'created_at' => $this->clock->now(),
            'attempt_count' => 0,
            'next_attempt_at' => null,
        ], [
            'payload' => Types::JSON,
            'created_at' => Types::DATETIME_IMMUTABLE,
        ]);

        return $id;
    }
}
