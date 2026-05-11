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

final class CreateUploadIntentController
{
    #[Route('/api/media/uploads', name: 'api_media_upload_prepare', methods: ['POST'])]
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
                'filename' => [new Assert\NotBlank(), new Assert\Length(max: 180)],
                'mimeType' => [new Assert\NotBlank(), new Assert\Length(max: 120)],
                'size' => [new Assert\NotBlank(), new Assert\Positive()],
                'purpose' => new Assert\Optional([new Assert\Type('string'), new Assert\Length(max: 80)]),
            ],
            allowExtraFields: false,
            allowMissingFields: false,
        ));

        return new JsonResponse($mediaManager->createUploadIntent(
            $user,
            (string) $payload['filename'],
            (string) $payload['mimeType'],
            (int) $payload['size'],
            is_string($payload['purpose'] ?? null) ? $payload['purpose'] : null,
        ), 201);
    }
}
