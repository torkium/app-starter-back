<?php

declare(strict_types=1);

namespace App\Shared\Application\Http;

use Symfony\Component\HttpFoundation\JsonResponse;

final class JsonErrorResponder
{
    /**
     * @param array<string, mixed> $extra
     */
    public static function create(string $title, int $status, string $detail, ?string $requestId = null, array $extra = [], array $headers = []): JsonResponse
    {
        $payload = [
            'title' => 'Request error',
            'status' => $status,
            'detail' => $detail,
        ];

        $payload['title'] = $title;

        if (null !== $requestId && '' !== $requestId) {
            $payload['requestId'] = $requestId;
        }

        foreach ($extra as $key => $value) {
            $payload[$key] = $value;
        }

        return new JsonResponse($payload, $status, array_merge(['Content-Type' => 'application/problem+json'], $headers));
    }

    public static function badRequest(string $detail, int $status = 400, ?string $requestId = null): JsonResponse
    {
        return self::create('Bad Request', $status, $detail, $requestId);
    }

    public static function fromApiProblem(ApiProblemException $exception, ?string $requestId = null): JsonResponse
    {
        return self::create(
            $exception->getTitle(),
            $exception->getStatusCode(),
            $exception->getMessage(),
            $requestId,
            $exception->getExtra(),
            $exception->getHeaders(),
        );
    }
}
