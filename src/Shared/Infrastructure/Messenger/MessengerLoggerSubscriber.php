<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messenger;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Stamp\BusNameStamp;

final readonly class MessengerLoggerSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LoggerInterface $messengerLogger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageReceivedEvent::class => 'onReceived',
            WorkerMessageHandledEvent::class => 'onHandled',
            WorkerMessageFailedEvent::class => 'onFailed',
        ];
    }

    public function onReceived(WorkerMessageReceivedEvent $event): void
    {
        $this->messengerLogger->info('Messenger message received', $this->context($event->getEnvelope()->getMessage(), $event->getEnvelope()->last(BusNameStamp::class)?->getBusName()));
    }

    public function onHandled(WorkerMessageHandledEvent $event): void
    {
        $this->messengerLogger->info('Messenger message handled', $this->context($event->getEnvelope()->getMessage(), $event->getEnvelope()->last(BusNameStamp::class)?->getBusName()));
    }

    public function onFailed(WorkerMessageFailedEvent $event): void
    {
        $this->messengerLogger->error('Messenger message failed', $this->context(
            $event->getEnvelope()->getMessage(),
            $event->getEnvelope()->last(BusNameStamp::class)?->getBusName(),
        ) + [
            'exception' => $event->getThrowable(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function context(object $message, ?string $busName): array
    {
        return [
            'message_class' => $message::class,
            'bus' => $busName,
        ];
    }
}
