<?php

declare(strict_types=1);

namespace App\Media\Application\Service;

use App\Identity\Domain\Entity\User;
use App\Media\Application\Port\DirectUploadStorageInterface;
use App\Media\Domain\Entity\MediaAsset;
use App\Shared\Application\Http\ApiProblemException;
use App\Shared\Application\Port\ClockInterface;
use App\Shared\Application\Port\TransactionManagerInterface;
use App\Shared\Application\Port\OutboxRecorderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class MediaManager
{
    private const MAX_IMAGE_SIZE = 10_485_760;
    private const MAX_VIDEO_SIZE = 104_857_600;
    private const ALLOWED_IMAGE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private const ALLOWED_VIDEO_MIME_TYPES = ['video/mp4', 'video/webm', 'video/quicktime'];
    private const MIME_TYPE_ALIASES = [
        'image/jpg' => 'image/jpeg',
        'image/pjpeg' => 'image/jpeg',
        'video/x-msvideo' => 'video/avi',
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private DirectUploadStorageInterface $storage,
        private TransactionManagerInterface $transactionManager,
        private OutboxRecorderInterface $outboxRecorder,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAssets(User $user): array
    {
        $assets = $this->entityManager->getRepository(MediaAsset::class)->findBy(['user' => $user], ['createdAt' => 'DESC']);

        return array_map(
            static fn (MediaAsset $asset): array => $asset->toView(),
            array_values(array_filter($assets, static fn (mixed $asset): bool => $asset instanceof MediaAsset)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function createUploadIntent(User $user, string $filename, string $mimeType, int $size, ?string $purpose): array
    {
        if ($size <= 0) {
            throw ApiProblemException::unprocessable('La taille du fichier doit être supérieure à zéro.');
        }

        $normalizedMimeType = $this->normalizeMimeType($mimeType);
        if (!in_array($normalizedMimeType, [...self::ALLOWED_IMAGE_MIME_TYPES, ...self::ALLOWED_VIDEO_MIME_TYPES], true)) {
            throw ApiProblemException::unprocessable('Type de fichier non supporté.');
        }

        $maxSize = in_array($normalizedMimeType, self::ALLOWED_VIDEO_MIME_TYPES, true)
            ? self::MAX_VIDEO_SIZE
            : self::MAX_IMAGE_SIZE;

        if ($size > $maxSize) {
            throw ApiProblemException::unprocessable('La taille du fichier dépasse la limite autorisée.');
        }

        $assetId = Uuid::v7()->toRfc4122();
        $uploadToken = bin2hex(random_bytes(24));
        $uploadTokenHash = hash('sha256', $uploadToken);
        $createdAt = $this->clock->now();
        $expiresAt = $createdAt->modify('+30 minutes');
        $safeFilename = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $filename) ?: 'upload.bin';
        $objectKey = sprintf('%s/%s-%s', $user->getId(), $assetId, $safeFilename);

        $asset = new MediaAsset(
            $assetId,
            $user,
            $filename,
            $normalizedMimeType,
            $size,
            $objectKey,
            'pending',
            $purpose,
            $uploadTokenHash,
            $expiresAt,
            $createdAt,
        );

        $this->transactionManager->run(function () use ($asset): void {
            $this->entityManager->persist($asset);
            $this->outboxRecorder->record('media.upload_intent_created', 'default', [
                'assetId' => $asset->getId(),
                'userId' => $asset->getUser()->getId(),
                'objectKey' => $asset->getObjectKey(),
            ]);
        });

        return [
            'asset' => $asset->toView(),
            'upload' => $this->storage->buildUploadTarget(
                $asset->getId(),
                ['X-Upload-Token' => $uploadToken, 'Content-Type' => $normalizedMimeType],
                $expiresAt,
                $size,
            ),
        ];
    }

    /**
     * @param resource $stream
     */
    public function ingestBinary(string $assetId, string $uploadToken, mixed $stream, ?string $contentType = null): void
    {
        $asset = $this->entityManager->getRepository(MediaAsset::class)->find($assetId);
        if (!$asset instanceof MediaAsset) {
            throw ApiProblemException::notFound('Asset media introuvable.');
        }

        if (!$asset->matchesUploadToken($uploadToken, $this->clock->now())) {
            throw ApiProblemException::forbidden('Jeton d’upload invalide ou expiré.');
        }

        if (!is_resource($stream)) {
            throw ApiProblemException::unprocessable('Le flux d’upload est invalide.');
        }

        if (null !== $contentType && !$asset->matchesMimeType($this->normalizeMimeType($contentType))) {
            throw ApiProblemException::unprocessable('Le type MIME du contenu ne correspond pas à celui déclaré.');
        }

        $storedObjectKey = null;
        try {
            $this->transactionManager->run(function () use ($asset, $stream, &$storedObjectKey): void {
                $stored = $this->storage->storeStream($asset->getObjectKey(), $stream, $asset->getSize());
                $storedObjectKey = $asset->getObjectKey();
                if (!$this->matchesExpectedMimeType($asset->getMimeType(), $stored['detectedMimeType'])) {
                    throw ApiProblemException::unprocessable(sprintf(
                        'The uploaded file content does not match the declared MIME type. Expected "%s", detected "%s".',
                        $asset->getMimeType(),
                        $stored['detectedMimeType'] ?? 'unknown',
                    ));
                }

                $asset->markUploaded($this->clock->now(), $stored['checksum']);
                $this->outboxRecorder->record('media.binary_uploaded', 'default', [
                    'assetId' => $asset->getId(),
                    'userId' => $asset->getUser()->getId(),
                    'objectKey' => $asset->getObjectKey(),
                ]);
            });
        } catch (\Throwable $throwable) {
            if (null !== $storedObjectKey) {
                $this->storage->delete($storedObjectKey);
            }

            throw $throwable;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function complete(User $user, string $assetId, ?string $checksum = null): array
    {
        $asset = $this->entityManager->getRepository(MediaAsset::class)->findOneBy(['id' => $assetId, 'user' => $user]);
        if (!$asset instanceof MediaAsset) {
            throw ApiProblemException::notFound('Asset media introuvable.');
        }

        if (!$asset->isUploaded()) {
            throw ApiProblemException::unprocessable('Le binaire doit être uploadé avant finalisation.');
        }

        if (!$asset->matchesChecksum($checksum)) {
            throw ApiProblemException::unprocessable('Le checksum fourni ne correspond pas au contenu uploadé.');
        }

        $this->transactionManager->run(function () use ($asset): void {
            $asset->markReady($this->buildControlledPreviewUrl($asset->getId()), $this->clock->now());
            $this->outboxRecorder->record('media.asset_ready', 'default', [
                'assetId' => $asset->getId(),
                'userId' => $asset->getUser()->getId(),
                'objectKey' => $asset->getObjectKey(),
            ]);
        });

        return $asset->toView();
    }

    /**
     * @return array{stream:mixed,mimeType:string,filename:string,size:int}
     */
    public function readContent(User $user, string $assetId): array
    {
        $asset = $this->entityManager->getRepository(MediaAsset::class)->findOneBy(['id' => $assetId, 'user' => $user]);
        if (!$asset instanceof MediaAsset) {
            throw ApiProblemException::notFound('Asset media introuvable.');
        }

        if (!$asset->isReady()) {
            throw ApiProblemException::unprocessable('Le media n’est pas encore disponible.');
        }

        return [
            'stream' => $this->storage->openReadStream($asset->getObjectKey()),
            'mimeType' => $asset->getMimeType(),
            'filename' => $asset->getFilename(),
            'size' => $asset->getSize(),
        ];
    }

    private function buildControlledPreviewUrl(string $assetId): string
    {
        return sprintf('/api/media/assets/%s/content', $assetId);
    }

    private function normalizeMimeType(string $mimeType): string
    {
        $normalized = strtolower(trim(explode(';', $mimeType, 2)[0]));

        return self::MIME_TYPE_ALIASES[$normalized] ?? $normalized;
    }

    private function matchesExpectedMimeType(string $expectedMimeType, ?string $detectedMimeType): bool
    {
        if (null === $detectedMimeType || '' === trim($detectedMimeType)) {
            return false;
        }

        return $this->normalizeMimeType($expectedMimeType) === $this->normalizeMimeType($detectedMimeType);
    }
}
