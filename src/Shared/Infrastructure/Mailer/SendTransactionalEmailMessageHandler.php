<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Mailer;

use App\Shared\Application\Message\SendTransactionalEmailMessage;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendTransactionalEmailMessageHandler
{
    public function __construct(
        private MailerInterface $mailer,
    ) {
    }

    public function __invoke(SendTransactionalEmailMessage $message): void
    {
        $email = (new TemplatedEmail())
            ->to($message->to)
            ->subject($message->subject)
            ->htmlTemplate('emails/generic.html.twig')
            ->textTemplate('emails/generic.txt.twig')
            ->context([
                'headline' => $message->subject,
                'body' => $message->template,
                'data' => $message->context,
            ]);

        if (isset($message->context['from']) && is_string($message->context['from'])) {
            $email->from($message->context['from']);
        }

        $this->mailer->send($email);
    }
}
