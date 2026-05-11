<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Controller;

use App\Identity\Application\Service\IdentityManager;
use App\Identity\Domain\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class MeController
{
    #[Route('/api/account/me', name: 'api_account_me', methods: ['GET'])]
    public function __invoke(Security $security, IdentityManager $identityManager): JsonResponse
    {
        $user = $security->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['title' => 'Unauthorized', 'status' => 401], 401);
        }

        return new JsonResponse($identityManager->viewUser($user));
    }
}
