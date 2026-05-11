<?php

declare(strict_types=1);

namespace App\Media\Presentation\Controller;

use App\Identity\Domain\Entity\User;
use App\Media\Application\Service\MediaManager;
use App\Shared\Application\Http\ApiProblemException;
use App\Shared\Application\Http\JsonRequestDecoder;
use App\Shared\Application\Http\RequestPayloadValidator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;

final class CompleteUploadController
{
    #[Route('/api/media/uploads/complete', name: 'api_media_upload_complete', methods: ['POST'])]
    public function __invoke(
        Request $request,
        Security $security,
        MediaManager $mediaManager,
        JsonRequestDecoder $decoder,
        RequestPayloadValidator $validator,
    ): JsonResponse {
        $user = $security->getUser();
        if (!$user instanceof User) {
            throw ApiProblemException::unauthorized();
        }

        $payload = $decoder->decode($request);
        $validator->validate($payload, new Assert\Collection(
            fields: [
                'assetId' => [new Assert\NotBlank(), new Assert\Uuid()],
                'checksum' => [new Assert\NotBlank(), new Assert\Regex('/^[a-f0-9]{64}$/')],
                'metadata' => new Assert\Optional([new Assert\Type('array')]),
            ],
            allowExtraFields: true,
            allowMissingFields: false,
        ));

        return new JsonResponse($mediaManager->complete(
            $user,
            (string) $payload['assetId'],
            is_string($payload['checksum'] ?? null) ? $payload['checksum'] : null,
        ));
    }
}
