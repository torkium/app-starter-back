<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Realtime;

use App\Shared\Application\Port\RealtimePublisherInterface;
use App\Shared\Application\Port\OutboxRecorderInterface;

final readonly class MercureRealtimePublisher implements RealtimePublisherInterface
{
    public function __construct(
        private OutboxRecorderInterface $outboxRecorder,
    ) {
    }

    public function publish(string $topic, array $payload, bool $private = false): void
    {
        $this->outboxRecorder->record('shared.realtime.update', 'realtime', [
            'topic' => $topic,
            'payload' => $payload,
            'private' => $private,
        ]);
    }
}
