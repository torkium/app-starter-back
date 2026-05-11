<?php

declare(strict_types=1);

namespace App\Shared\Application\Message;

final readonly class SendTransactionalEmailMessage
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public string $to,
        public string $subject,
        public string $template,
        public array $context = [],
    ) {
    }
}
