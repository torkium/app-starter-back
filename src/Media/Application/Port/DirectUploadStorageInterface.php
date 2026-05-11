<?php

declare(strict_types=1);

namespace App\Media\Application\Port;

interface DirectUploadStorageInterface
{
    /**
     * @param array<string, string> $headers
     * @return array{uploadUrl:string,method:string,headers:array<string, string>,expiresAt:string,maxSizeBytes:int}
     */
    public function buildUploadTarget(string $assetId, array $headers, \DateTimeImmutable $expiresAt, int $maxSizeBytes): array;

    /**
     * @param resource $stream
     * @return array{checksum:string,detectedMimeType:string|null}
     */
    public function storeStream(string $objectKey, mixed $stream, int $expectedSize): array;

    /**
     * @return resource
     */
    public function openReadStream(string $objectKey): mixed;
}
