<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Controller;

use App\Identity\Application\Service\IdentityManager;
use App\Identity\Domain\Entity\User;
use App\Shared\Application\Http\ApiProblemException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class RevokeSessionController
{
    #[Route('/api/account/sessions/{sessionId}', name: 'api_account_sessions_revoke', methods: ['DELETE'])]
    public function __invoke(string $sessionId, Security $security, IdentityManager $identityManager): JsonResponse
    {
        $user = $security->getUser();
        if (!$user instanceof User) {
            throw ApiProblemException::unauthorized();
        }

        $identityManager->revokeSession($user, $sessionId);

        return new JsonResponse(['status' => 'revoked']);
    }
}
