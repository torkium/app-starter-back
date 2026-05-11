<?php

declare(strict_types=1);

namespace App\Shared\Application\Port;

interface MailerPortInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function queue(string $to, string $subject, string $template, array $context = []): void;
}
