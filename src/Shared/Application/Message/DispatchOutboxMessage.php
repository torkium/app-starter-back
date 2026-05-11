<?php

declare(strict_types=1);

namespace App\Shared\Application\Message;

final readonly class DispatchOutboxMessage
{
    public function __construct(
        public string $messageId,
        public string $claimToken,
    ) {
    }
}
