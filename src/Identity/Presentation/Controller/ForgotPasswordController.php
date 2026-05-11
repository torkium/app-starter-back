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

final class ForgotPasswordController
{
    #[Route('/api/auth/forgot-password', name: 'api_auth_forgot_password', methods: ['POST'])]
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
                'email' => [new Assert\NotBlank(), new Assert\Email(), new Assert\Length(max: 180)],
            ],
            allowExtraFields: false,
            allowMissingFields: false,
        ));
        $rateLimiter->consumeForgotPassword($this->buildLimiterKey($request, (string) $payload['email']));
        $identityManager->forgotPassword((string) $payload['email']);

        return new JsonResponse(['status' => 'accepted']);
    }

    private function buildLimiterKey(Request $request, string $email): string
    {
        return sprintf('%s:%s', $request->getClientIp() ?? 'unknown', strtolower(trim($email)));
    }
}
