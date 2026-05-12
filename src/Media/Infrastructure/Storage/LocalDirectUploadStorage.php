<?php

declare(strict_types=1);

namespace App\Media\Infrastructure\Storage;

use App\Media\Application\Port\DirectUploadStorageInterface;

final readonly class LocalDirectUploadStorage implements DirectUploadStorageInterface
{
    public function __construct(
        private string $uploadDirectory,
    ) {
    }

    public function buildUploadTarget(string $assetId, array $headers, \DateTimeImmutable $expiresAt, int $maxSizeBytes): array
    {
        return [
            'uploadUrl' => sprintf('/api/media/uploads/%s/binary', $assetId),
            'method' => 'PUT',
            'headers' => $headers,
            'expiresAt' => $expiresAt->format(\DATE_ATOM),
            'maxSizeBytes' => $maxSizeBytes,
        ];
    }

    public function storeStream(string $objectKey, mixed $stream, int $expectedSize): array
    {
        if (!is_resource($stream)) {
            throw new \InvalidArgumentException('Le flux d’upload fourni est invalide.');
        }

        $path = $this->buildPath($objectKey);
        $directory = \dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Impossible de créer le répertoire de stockage "%s".', $directory));
        }

        $temporaryPath = sprintf('%s.part', $path);
        $target = fopen($temporaryPath, 'wb');
        if (false === $target) {
            throw new \RuntimeException(sprintf('Impossible d’ouvrir le fichier temporaire "%s".', $temporaryPath));
        }

        $hash = hash_init('sha256');
        $written = 0;

        try {
            while (!feof($stream)) {
                $chunk = fread($stream, 1_048_576);
                if (false === $chunk) {
                    throw new \RuntimeException('Impossible de lire le flux d’upload.');
                }

                if ('' === $chunk) {
                    continue;
                }

                $chunkLength = strlen($chunk);
                $written += $chunkLength;
                if ($written > $expectedSize) {
                    throw new \RuntimeException('Le contenu uploadé dépasse la taille déclarée.');
                }

                hash_update($hash, $chunk);

                if (false === fwrite($target, $chunk)) {
                    throw new \RuntimeException(sprintf('Impossible d’écrire le fichier "%s".', $temporaryPath));
                }
            }
        } catch (\Throwable $exception) {
            fclose($target);
            @unlink($temporaryPath);

            throw $exception;
        }

        fclose($target);

        if ($written !== $expectedSize) {
            @unlink($temporaryPath);

            throw new \RuntimeException('La taille du contenu reçu ne correspond pas à la taille déclarée.');
        }

        $detectedMimeType = $this->detectMimeType($temporaryPath);

        if (!rename($temporaryPath, $path)) {
            @unlink($temporaryPath);

            throw new \RuntimeException(sprintf('Impossible de finaliser le fichier "%s".', $path));
        }

        return [
            'checksum' => hash_final($hash),
            'detectedMimeType' => $detectedMimeType,
        ];
    }

    public function openReadStream(string $objectKey): mixed
    {
        $path = $this->buildPath($objectKey);
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException(sprintf('Le fichier media "%s" est introuvable.', $objectKey));
        }

        $stream = fopen($path, 'rb');
        if (false === $stream) {
            throw new \RuntimeException(sprintf('Impossible d’ouvrir le fichier media "%s".', $objectKey));
        }

        return $stream;
    }

    public function delete(string $objectKey): void
    {
        $path = $this->buildPath($objectKey);
        if (is_file($path)) {
            @unlink($path);
        }
        if (is_file($path.'.part')) {
            @unlink($path.'.part');
        }
    }

    private function buildPath(string $objectKey): string
    {
        return rtrim($this->uploadDirectory, '/').'/'.ltrim($objectKey, '/');
    }

    private function detectMimeType(string $path): ?string
    {
        $header = file_get_contents($path, false, null, 0, 32);
        if (false !== $header) {
            if (str_starts_with($header, "\x89PNG\x0D\x0A\x1A\x0A")) {
                return 'image/png';
            }

            if (str_starts_with($header, 'GIF87a') || str_starts_with($header, 'GIF89a')) {
                return 'image/gif';
            }

            if (str_starts_with($header, "\xFF\xD8\xFF")) {
                return 'image/jpeg';
            }

            if (strlen($header) >= 12 && 'RIFF' === substr($header, 0, 4) && 'WEBP' === substr($header, 8, 4)) {
                return 'image/webp';
            }

            if (strlen($header) >= 12 && 'ftyp' === substr($header, 4, 4)) {
                $brand = substr($header, 8, 4);

                if ('qt  ' === $brand) {
                    return 'video/quicktime';
                }

                return 'video/mp4';
            }

            if (str_starts_with($header, "\x1A\x45\xDF\xA3")) {
                return 'video/webm';
            }
        }

        $finfo = new \finfo(\FILEINFO_MIME_TYPE);
        $detected = $finfo->file($path);

        return false !== $detected ? strtolower(trim($detected)) : null;
    }
}
