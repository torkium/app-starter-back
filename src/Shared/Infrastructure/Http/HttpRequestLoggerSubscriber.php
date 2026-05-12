<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class HttpRequestLoggerSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LoggerInterface $httpLogger,
        private SensitiveValueSanitizer $sanitizer,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onRequest',
            KernelEvents::RESPONSE => 'onResponse',
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $this->httpLogger->info('HTTP request started', $this->buildRequestContext($request));
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $startedAt = (float) $request->attributes->get('_request_started_at', microtime(true));

        $this->httpLogger->info('HTTP request completed', [
            'request_id' => $request->attributes->get(RequestIdSubscriber::ATTRIBUTE),
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
            'status_code' => $event->getResponse()->getStatusCode(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRequestContext(Request $request): array
    {
        $content = [];
        $raw = trim($request->getContent());

        if ('' !== $raw && strlen($raw) <= 8192 && str_contains((string) $request->headers->get('Content-Type', ''), 'application/json')) {
            try {
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $content = $this->sanitizer->sanitizeArray($decoded);
                }
            } catch (\JsonException) {
                $content = ['_malformed_json' => true];
            }
        } elseif (strlen($raw) > 8192) {
            $content = ['_omitted' => 'body_too_large'];
        }

        return [
            'request_id' => $request->attributes->get(RequestIdSubscriber::ATTRIBUTE),
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
            'query' => $this->sanitizer->sanitizeArray($request->query->all()),
            'body' => $content,
            'ip' => $this->maskIp($request->getClientIp()),
        ];
    }

    private function maskIp(?string $ip): ?string
    {
        if (null === $ip) {
            return null;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return preg_replace('/\.\d+$/', '.0', $ip);
        }

        return preg_replace('/:[0-9a-f]{1,4}$/i', ':0000', $ip);
    }
}
