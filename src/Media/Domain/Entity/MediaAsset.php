<?php

declare(strict_types=1);

namespace App\Media\Domain\Entity;

use App\Identity\Domain\Entity\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'media_assets')]
#[ORM\Index(name: 'idx_media_asset_user_created', columns: ['user_id', 'created_at'])]
class MediaAsset
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 180)]
    private string $filename;

    #[ORM\Column(length: 120)]
    private string $mimeType;

    #[ORM\Column]
    private int $size;

    #[ORM\Column(length: 255, unique: true)]
    private string $objectKey;

    #[ORM\Column(length: 40)]
    private string $status;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $purpose;

    #[ORM\Column(name: 'upload_token_hash', length: 64)]
    private string $uploadTokenHash;

    #[ORM\Column]
    private \DateTimeImmutable $uploadTokenExpiresAt;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $previewUrl = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $uploadedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $checksumSha256 = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id,
        User $user,
        string $filename,
        string $mimeType,
        int $size,
        string $objectKey,
        string $status,
        ?string $purpose,
        string $uploadTokenHash,
        \DateTimeImmutable $uploadTokenExpiresAt,
        \DateTimeImmutable $createdAt,
    ) {
        $this->id = $id;
        $this->user = $user;
        $this->filename = mb_substr(trim($filename), 0, 180);
        $this->mimeType = mb_substr(trim($mimeType), 0, 120);
        $this->size = $size;
        $this->objectKey = $objectKey;
        $this->status = $status;
        $this->purpose = null !== $purpose ? mb_substr(trim($purpose), 0, 80) : null;
        $this->uploadTokenHash = $uploadTokenHash;
        $this->uploadTokenExpiresAt = $uploadTokenExpiresAt;
        $this->createdAt = $createdAt;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function __toString(): string
    {
        return $this->filename;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getObjectKey(): string
    {
        return $this->objectKey;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getPurpose(): ?string
    {
        return $this->purpose;
    }

    public function getUploadTokenHash(): string
    {
        return $this->uploadTokenHash;
    }

    public function getUploadTokenExpiresAt(): \DateTimeImmutable
    {
        return $this->uploadTokenExpiresAt;
    }

    public function getPreviewUrl(): ?string
    {
        return $this->previewUrl;
    }

    public function getUploadedAt(): ?\DateTimeImmutable
    {
        return $this->uploadedAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getChecksumSha256(): ?string
    {
        return $this->checksumSha256;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function matchesMimeType(string $mimeType): bool
    {
        return strtolower(trim($this->mimeType)) === strtolower(trim($mimeType));
    }

    public function matchesUploadToken(string $token, \DateTimeImmutable $now): bool
    {
        return 'pending' === $this->status
            && hash_equals($this->uploadTokenHash, hash('sha256', $token))
            && $this->uploadTokenExpiresAt >= $now;
    }

    public function markUploaded(\DateTimeImmutable $uploadedAt, string $checksumSha256): void
    {
        $this->status = 'uploaded';
        $this->uploadedAt = $uploadedAt;
        $this->checksumSha256 = strtolower($checksumSha256);
    }

    public function markReady(string $previewUrl, \DateTimeImmutable $completedAt): void
    {
        $this->status = 'ready';
        $this->previewUrl = $previewUrl;
        $this->completedAt = $completedAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function toView(): array
    {
        return [
            'id' => $this->id,
            'filename' => $this->filename,
            'mimeType' => $this->mimeType,
            'size' => $this->size,
            'status' => $this->status,
            'previewUrl' => $this->previewUrl,
            'createdAt' => $this->createdAt->format(\DATE_ATOM),
        ];
    }

    public function isUploaded(): bool
    {
        return 'uploaded' === $this->status || 'ready' === $this->status;
    }

    public function matchesChecksum(?string $checksumSha256): bool
    {
        if (null === $checksumSha256 || '' === trim($checksumSha256)) {
            return false;
        }

        return null !== $this->checksumSha256 && hash_equals($this->checksumSha256, strtolower(trim($checksumSha256)));
    }

    public function isReady(): bool
    {
        return 'ready' === $this->status;
    }
}
