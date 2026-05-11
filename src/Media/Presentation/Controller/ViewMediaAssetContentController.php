<?php

declare(strict_types=1);

namespace App\Media\Presentation\Controller;

use App\Identity\Domain\Entity\User;
use App\Media\Application\Service\MediaManager;
use App\Shared\Application\Http\ApiProblemException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ViewMediaAssetContentController
{
    #[Route('/api/media/assets/{assetId}/content', name: 'api_media_asset_content', methods: ['GET'])]
    public function __invoke(string $assetId, Security $security, MediaManager $mediaManager): StreamedResponse
    {
        $user = $security->getUser();
        if (!$user instanceof User) {
            throw ApiProblemException::unauthorized();
        }

        $asset = $mediaManager->readContent($user, $assetId);

        return new StreamedResponse(static function () use ($asset): void {
            $stream = $asset['stream'];
            if (!is_resource($stream)) {
                throw new \RuntimeException('Flux média invalide.');
            }

            try {
                while (!feof($stream)) {
                    $chunk = fread($stream, 1_048_576);
                    if (false === $chunk) {
                        throw new \RuntimeException('Impossible de lire le flux média.');
                    }

                    if ('' === $chunk) {
                        continue;
                    }

                    echo $chunk;
                }
            } finally {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $asset['mimeType'],
            'Content-Length' => (string) $asset['size'],
            'Content-Disposition' => sprintf('inline; filename="%s"', addcslashes($asset['filename'], '"\\')),
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
