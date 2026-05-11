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

final class LoginController
{
    #[Route('/api/auth/login', name: 'api_auth_login', methods: ['POST'])]
    #[OA\Post(path: '/api/auth/login', summary: 'Authenticate a user and issue JWT tokens')]
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
                'password' => [new Assert\NotBlank(), new Assert\Length(min: 1, max: 255)],
                'deviceName' => new Assert\Optional([new Assert\Type('string'), new Assert\Length(max: 120)]),
            ],
            allowExtraFields: false,
            allowMissingFields: false,
        ));
        $rateLimiter->consumeAuthLogin($this->buildLimiterKey($request, (string) $payload['email']));

        return new JsonResponse($identityManager->login(
            (string) $payload['email'],
            (string) $payload['password'],
            is_string($payload['deviceName'] ?? null) ? $payload['deviceName'] : null,
            $request->headers->get('User-Agent'),
            $request->getClientIp(),
        ));
    }

    private function buildLimiterKey(Request $request, string $email): string
    {
        return sprintf('%s:%s', $request->getClientIp() ?? 'unknown', strtolower(trim($email)));
    }
}
