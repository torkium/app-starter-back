<?php

declare(strict_types=1);

namespace App\Media\Presentation\Controller;

use App\Identity\Domain\Entity\User;
use App\Media\Application\Service\MediaManager;
use App\Shared\Application\Http\ApiProblemException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ListMediaAssetsController
{
    #[Route('/api/media/assets', name: 'api_media_assets', methods: ['GET'])]
    public function __invoke(Request $request, Security $security, MediaManager $mediaManager): JsonResponse
    {
        $user = $security->getUser();
        if (!$user instanceof User) {
            throw ApiProblemException::unauthorized();
        }

        return new JsonResponse($mediaManager->listAssets(
            $user,
            $request->query->getInt('limit', 50),
            $request->query->getInt('offset', 0),
        ));
    }
}
