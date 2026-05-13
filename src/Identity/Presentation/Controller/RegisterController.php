<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Controller;

use App\Identity\Application\Service\IdentityManager;
use App\Shared\Application\Http\JsonRequestDecoder;
use App\Shared\Application\Http\RequestPayloadValidator;
use App\Shared\Application\Http\RequestRateLimiter;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;

final class RegisterController
{
    #[Route('/api/auth/register', name: 'api_auth_register', methods: ['POST'])]
    #[OA\Post(path: '/api/auth/register', summary: 'Register a user')]
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
                'password' => [new Assert\NotBlank(), new Assert\Length(min: 12, max: 255)],
                'firstName' => new Assert\Optional([new Assert\NotBlank(), new Assert\Length(max: 120)]),
                'lastName' => new Assert\Optional([new Assert\NotBlank(), new Assert\Length(max: 120)]),
            ],
            allowExtraFields: false,
            allowMissingFields: false,
        ));
        $rateLimiter->consumeAuthRegister($this->buildLimiterKey($request, (string) $payload['email']));

        $identityManager->register(
            (string) $payload['email'],
            (string) $payload['password'],
            (string) ($payload['firstName'] ?? ''),
            (string) ($payload['lastName'] ?? ''),
        );

        return new JsonResponse(['status' => 'registered'], 201);
    }

    private function buildLimiterKey(Request $request, string $email): string
    {
        return sprintf('%s:%s', $request->getClientIp() ?? 'unknown', strtolower(trim($email)));
    }
}
