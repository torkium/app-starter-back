<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Controller;

use App\Identity\Domain\Entity\User;
use App\Shared\Application\Http\ApiProblemException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Routing\Attribute\Route;

final class AuthorizeRealtimeSubscriptionsController
{
    #[Route('/api/account/realtime/authorize', name: 'api_account_realtime_authorize', methods: ['POST'])]
    public function __invoke(Request $request, Security $security, Authorization $authorization): JsonResponse
    {
        $user = $security->getUser();
        if (!$user instanceof User) {
            throw ApiProblemException::unauthorized();
        }

        $authorization->setCookie($request, subscribe: ['/users/'.$user->getId()]);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
