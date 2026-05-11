<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Monolog\LogRecord;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class RequestIdProcessor
{
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null !== $request) {
            return $record->with(extra: $record->extra + [
                'request_id' => $request->attributes->get(RequestIdSubscriber::ATTRIBUTE),
            ]);
        }

        return $record;
    }
}
