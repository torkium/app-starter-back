<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Mailer;

use App\Shared\Application\Message\SendTransactionalEmailMessage;
use App\Shared\Application\Port\MailerPortInterface;
use App\Shared\Infrastructure\Outbox\OutboxRecorder;

final readonly class SymfonyMailerPort implements MailerPortInterface
{
    public function __construct(
        private OutboxRecorder $outboxRecorder,
        private string $fromAddress,
    ) {
    }

    public function queue(string $to, string $subject, string $template, array $context = []): void
    {
        $payload = ['from' => $this->fromAddress] + $context;
        $this->outboxRecorder->record('shared.mail.transactional', 'mail', [
            'to' => $to,
            'subject' => $subject,
            'template' => $template,
            'context' => $payload,
        ]);
    }
}
