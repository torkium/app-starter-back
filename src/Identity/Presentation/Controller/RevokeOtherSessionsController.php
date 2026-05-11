<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Controller;

use App\Identity\Application\Service\IdentityManager;
use App\Identity\Domain\Entity\User;
use App\Shared\Application\Http\ApiProblemException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class RevokeOtherSessionsController
{
    #[Route('/api/account/sessions/others', name: 'api_account_sessions_revoke_others', methods: ['DELETE'])]
    public function __invoke(
        Request $request,
        Security $security,
        IdentityManager $identityManager,
        JWTTokenManagerInterface $jwtManager,
    ): JsonResponse {
        $user = $security->getUser();
        if (!$user instanceof User) {
            throw ApiProblemException::unauthorized();
        }

        $authorization = $request->headers->get('Authorization');
        if (!is_string($authorization) || !str_starts_with($authorization, 'Bearer ')) {
            throw ApiProblemException::unauthorized();
        }

        try {
            $payload = $jwtManager->parse(substr($authorization, 7));
        } catch (\Throwable) {
            throw ApiProblemException::unauthorized();
        }

        $currentSessionId = is_array($payload) && is_string($payload['sid'] ?? null) ? $payload['sid'] : null;
        if (null === $currentSessionId) {
            throw ApiProblemException::unprocessable('La session courante est introuvable.');
        }

        $identityManager->revokeOtherSessions($user, $currentSessionId);

        return new JsonResponse(['status' => 'others_revoked']);
    }
}
