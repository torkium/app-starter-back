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

final class ResetPasswordController
{
    #[Route('/api/auth/reset-password', name: 'api_auth_reset_password', methods: ['POST'])]
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
                'token' => [new Assert\NotBlank(), new Assert\Length(min: 32, max: 128)],
                'password' => [new Assert\NotBlank(), new Assert\Length(min: 12, max: 255)],
            ],
            allowExtraFields: false,
            allowMissingFields: false,
        ));
        $rateLimiter->consumeSensitiveAuthAction($this->buildLimiterKey($request, (string) $payload['token']));
        $identityManager->resetPassword(
            (string) $payload['token'],
            (string) $payload['password'],
        );

        return new JsonResponse(['status' => 'password_updated']);
    }

    private function buildLimiterKey(Request $request, string $token): string
    {
        return sprintf('%s:%s', $request->getClientIp() ?? 'unknown', substr(hash('sha256', $token), 0, 20));
    }
}
