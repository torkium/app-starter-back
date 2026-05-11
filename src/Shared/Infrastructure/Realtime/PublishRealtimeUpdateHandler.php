<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Realtime;

use App\Shared\Application\Message\PublishRealtimeUpdateMessage;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PublishRealtimeUpdateHandler
{
    public function __construct(
        private HubInterface $hub,
    ) {
    }

    public function __invoke(PublishRealtimeUpdateMessage $message): void
    {
        $this->hub->publish(new Update(
            $message->topic,
            json_encode($message->payload, JSON_THROW_ON_ERROR),
            private: $message->private,
        ));
    }
}
