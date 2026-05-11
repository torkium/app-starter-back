<?php

declare(strict_types=1);

namespace App\Shared\Application\Message;

final readonly class PublishRealtimeUpdateMessage
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $topic,
        public array $payload,
        public bool $private = false,
    ) {
    }
}
