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
        private OutboxPayloadProtector $payloadProtector,
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

        $payload = [];
        $mailPayloadWasProtected = true;

        try {
            $payload = 'mail' === $outbox->getChannel()
                ? $this->payloadProtector->reveal($outbox->getPayload())
                : $outbox->getPayload();
            $mailPayloadWasProtected = 'mail' !== $outbox->getChannel() || $this->payloadProtector->isProtected($outbox->getPayload());

            match ($outbox->getChannel()) {
                'mail' => $this->dispatchMail($outbox->getId(), $payload),
                'realtime' => $this->dispatchRealtime($payload, $outbox->getId()),
                default => null,
            };

            if ('mail' === $outbox->getChannel()) {
                $outbox->replacePayload($this->redactedMailPayload());
            }
            $outbox->markPublished($this->clock->now());
        } catch (\InvalidArgumentException $throwable) {
            if ('mail' === $outbox->getChannel()) {
                $outbox->replacePayload($this->redactedMailPayload());
            }
            $outbox->markFailed($this->clock->now(), $throwable->getMessage());
            $this->entityManager->flush();

            return;
        } catch (\Throwable $throwable) {
            $shouldRetry = $outbox->scheduleRetry($this->clock->now(), $throwable->getMessage());
            if ('mail' === $outbox->getChannel()) {
                if (!$shouldRetry) {
                    $outbox->replacePayload($this->redactedMailPayload());
                } elseif (!$mailPayloadWasProtected) {
                    $outbox->replacePayload($this->payloadProtector->protect($payload));
                }
            }
            $this->entityManager->flush();

            if ($shouldRetry) {
                throw $throwable;
            }

            return;
        }

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
            throw new \InvalidArgumentException('Outbox mail payload must contain non-empty to, subject and template fields.');
        }

        $email = (new TemplatedEmail())
            ->to($to)
            ->subject($subject)
            ->htmlTemplate('emails/generic.html.twig')
            ->textTemplate('emails/generic.txt.twig')
            ->context([
                'headline' => $subject,
                'body' => $template,
                'data' => $context,
            ]);

        if (isset($context['from']) && is_string($context['from'])) {
            $email->from($context['from']);
        }

        $email->getHeaders()->addTextHeader('X-My-App-Outbox-Id', $messageId);

        $this->mailer->send($email);
    }

    /**
     * @return array<string, mixed>
     */
    private function redactedMailPayload(): array
    {
        return [
            'redacted' => true,
            'reason' => 'mail_payload_protected',
        ];
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
            throw new \InvalidArgumentException('Outbox realtime payload must contain a non-empty topic field.');
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
