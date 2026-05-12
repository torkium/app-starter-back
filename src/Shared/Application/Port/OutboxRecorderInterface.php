<?php

declare(strict_types=1);

namespace App\Shared\Application\Port;

interface OutboxRecorderInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function record(string $topic, string $channel, array $payload): string;
}
