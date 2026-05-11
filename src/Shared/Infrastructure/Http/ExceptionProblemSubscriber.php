<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Application\Http\ApiProblemException;
use App\Shared\Application\Http\JsonErrorResponder;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class ExceptionProblemSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => 'onException'];
    }

    public function onException(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();
        $request = $event->getRequest();
        $requestId = $request->attributes->get(RequestIdSubscriber::ATTRIBUTE);

        if ($throwable instanceof ApiProblemException) {
            $event->setResponse(JsonErrorResponder::fromApiProblem($throwable, (string) $requestId));

            return;
        }

        if ($throwable instanceof UnauthorizedHttpException) {
            $event->setResponse(JsonErrorResponder::create('Unauthorized', 401, $throwable->getMessage(), (string) $requestId));

            return;
        }

        if ($throwable instanceof AccessDeniedHttpException) {
            $event->setResponse(JsonErrorResponder::create('Forbidden', 403, $throwable->getMessage(), (string) $requestId));

            return;
        }

        if ($throwable instanceof HttpExceptionInterface) {
            $event->setResponse(JsonErrorResponder::create(
                $this->titleFromStatusCode($throwable->getStatusCode()),
                $throwable->getStatusCode(),
                $throwable->getMessage() ?: 'HTTP error.',
                (string) $requestId,
            ));

            return;
        }

        $this->logger->error('Unhandled application exception', [
            'exception' => $throwable,
            'request_id' => $requestId,
        ]);

        $event->setResponse(JsonErrorResponder::create(
            'Internal Server Error',
            500,
            'An unexpected error occurred.',
            (string) $requestId,
        ));
    }

    private function titleFromStatusCode(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            409 => 'Conflict',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            default => 'HTTP Error',
        };
    }
}
