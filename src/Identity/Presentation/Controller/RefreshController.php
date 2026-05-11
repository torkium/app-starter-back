<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Controller;

use App\Identity\Application\Service\IdentityManager;
use App\Shared\Application\Http\JsonRequestDecoder;
use App\Shared\Application\Http\RequestPayloadValidator;
use App\Shared\Application\Http\RequestRateLimiter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;

final class RefreshController
{
    #[Route('/api/auth/refresh', name: 'api_auth_refresh', methods: ['POST'])]
    public function __invoke(
        Request $request,
        IdentityManager $identityManager,
        JsonRequestDecoder $decoder,
        RequestPayloadValidator $validator,
        RequestRateLimiter $rateLimiter,
    ): JsonResponse
    {
        $payload = $decoder->decode($request);
        $validator->validate($payload, new Assert\Collection(
            fields: [
                'refreshToken' => [new Assert\NotBlank(), new Assert\Length(min: 32, max: 255)],
            ],
            allowExtraFields: false,
            allowMissingFields: false,
        ));
        $rateLimiter->consumeSensitiveAuthAction($this->buildLimiterKey($request, (string) $payload['refreshToken']));

        return new JsonResponse($identityManager->refresh(
            (string) $payload['refreshToken'],
            $request->headers->get('User-Agent'),
            $request->getClientIp(),
        ));
    }

    private function buildLimiterKey(Request $request, string $refreshToken): string
    {
        return sprintf('%s:%s', $request->getClientIp() ?? 'unknown', substr(hash('sha256', $refreshToken), 0, 20));
    }
}
