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

final class ListSessionsController
{
    #[Route('/api/account/sessions', name: 'api_account_sessions_list', methods: ['GET'])]
    public function __invoke(
        Request $request,
        Security $security,
        IdentityManager $identityManager,
        JWTTokenManagerInterface $jwtManager,
    ): JsonResponse
    {
        $user = $security->getUser();
        if (!$user instanceof User) {
            throw ApiProblemException::unauthorized();
        }

        $currentSessionId = null;
        $authorization = $request->headers->get('Authorization');
        if (is_string($authorization) && str_starts_with($authorization, 'Bearer ')) {
            try {
                $payload = $jwtManager->parse(substr($authorization, 7));
                $currentSessionId = is_array($payload) && is_string($payload['sid'] ?? null) ? $payload['sid'] : null;
            } catch (\Throwable) {
                throw ApiProblemException::unauthorized();
            }
        }

        return new JsonResponse($identityManager->listSessions($user, $currentSessionId));
    }
}
