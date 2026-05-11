<?php

declare(strict_types=1);

namespace App\Shared\Application\Port;

interface RealtimePublisherInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function publish(string $topic, array $payload, bool $private = false): void;
}
