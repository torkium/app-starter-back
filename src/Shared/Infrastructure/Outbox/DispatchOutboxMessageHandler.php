<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Outbox;

use App\Shared\Application\Message\DispatchOutboxMessage;
use App\Shared\Application\Port\ClockInterface;
use App\Shared\Domain\Entity\OutboxMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

#[AsMessageHandler]
final readonly class DispatchOutboxMessageHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
        private MailerInterface $mailer,
        private HubInterface $hub,
    ) {
    }

    public function __invoke(DispatchOutboxMessage $message): void
    {
        $outbox = $this->entityManager->find(OutboxMessage::class, $message->messageId);
        if (
            !$outbox instanceof OutboxMessage
            || $outbox->isPublished()
            || !$outbox->isClaimedBy($message->claimToken)
        ) {
            return;
        }

        if (!$outbox->startDelivery($this->clock->now())) {
            return;
        }

        $this->entityManager->flush();

        match ($outbox->getChannel()) {
            'mail' => $this->dispatchMail($outbox->getId(), $outbox->getPayload()),
            'realtime' => $this->dispatchRealtime($outbox->getId(), $outbox->getPayload()),
            default => $this->dispatchRealtime([
                'topic' => sprintf('/events/%s', $outbox->getTopic()),
                'payload' => $outbox->getPayload(),
            ], $outbox->getId()),
        };

        $outbox->markPublished($this->clock->now());
        $this->entityManager->flush();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function dispatchMail(string $messageId, array $payload): void
    {
        $to = isset($payload['to']) && is_string($payload['to']) ? $payload['to'] : '';
        $subject = isset($payload['subject']) && is_string($payload['subject']) ? $payload['subject'] : '';
        $template = isset($payload['template']) && is_string($payload['template']) ? $payload['template'] : '';
        $context = is_array($payload['context'] ?? null) ? $payload['context'] : [];

        if ('' === $to || '' === $subject || '' === $template) {
            return;
        }

        $email = (new TemplatedEmail())
            ->to($to)
            ->subject($subject)
            ->htmlTemplate('emails/generic.html.twig')
            ->context([
                'headline' => $subject,
                'body' => $template,
                'data' => $context,
            ])
            ->messageId(sprintf('<%s@starter.local>', $messageId));

        if (isset($context['from']) && is_string($context['from'])) {
            $email->from($context['from']);
        }

        $email->getHeaders()->addTextHeader('X-Starter-Outbox-Id', $messageId);

        $this->mailer->send($email);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function dispatchRealtime(array $payload, string $messageId): void
    {
        $topic = isset($payload['topic']) && is_string($payload['topic']) ? $payload['topic'] : '';
        $data = is_array($payload['payload'] ?? null) ? $payload['payload'] : [];
        $private = (bool) ($payload['private'] ?? false);

        if ('' === $topic) {
            return;
        }

        if (!isset($data['eventId']) || !is_string($data['eventId']) || '' === trim($data['eventId'])) {
            $data['eventId'] = $messageId;
        }

        $this->hub->publish(new Update(
            $topic,
            json_encode($data, JSON_THROW_ON_ERROR),
            $private,
            $messageId,
        ));
    }
}
