<?php

declare(strict_types=1);

namespace App\Media\Presentation\Controller;

use App\Media\Application\Service\MediaManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class UploadBinaryController
{
    #[Route('/api/media/uploads/{assetId}/binary', name: 'api_media_upload_binary', methods: ['PUT'])]
    public function __invoke(string $assetId, Request $request, MediaManager $mediaManager): JsonResponse
    {
        $stream = $request->getContent(true);

        $mediaManager->ingestBinary(
            $assetId,
            (string) $request->headers->get('X-Upload-Token', ''),
            $stream,
            $request->headers->get('Content-Type'),
        );

        return new JsonResponse(['status' => 'uploaded']);
    }
}
