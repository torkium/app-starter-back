<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Uid\Uuid;

final class RequestIdSubscriber implements EventSubscriberInterface
{
    public const ATTRIBUTE = '_request_id';
    public const HEADER = 'X-Request-Id';

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onRequest',
            KernelEvents::RESPONSE => 'onResponse',
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $requestId = trim((string) $request->headers->get(self::HEADER, ''));

        if ('' === $requestId) {
            $requestId = Uuid::v7()->toRfc4122();
        }

        $request->attributes->set(self::ATTRIBUTE, $requestId);
        $request->attributes->set('_request_started_at', microtime(true));
    }

    public function onResponse(ResponseEvent $event): void
    {
        $requestId = $event->getRequest()->attributes->getString(self::ATTRIBUTE, '');
        if ('' !== $requestId) {
            $event->getResponse()->headers->set(self::HEADER, $requestId);
        }
    }
}
