<?php

declare(strict_types=1);

namespace App\Admin\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[UniqueEntity(fields: ['email'], message: 'This admin email is already used.')]
#[ORM\Entity]
#[ORM\Table(name: 'admin_users')]
#[ORM\UniqueConstraint(name: 'uniq_admin_user_email', columns: ['email'])]
class AdminUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(length: 255)]
    private string $passwordHash;

    #[ORM\Column(length: 180)]
    private string $displayName;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $roles = ['ROLE_ADMIN'];

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    /**
     * @param list<string> $roles
     */
    public function __construct(
        string $id,
        string $email,
        string $passwordHash,
        string $displayName,
        array $roles,
        \DateTimeImmutable $createdAt,
        bool $active = true,
    ) {
        $this->id = $id;
        $this->email = strtolower(trim($email));
        $this->passwordHash = $passwordHash;
        $this->displayName = trim($displayName);
        $this->roles = $this->normalizeRoles($roles);
        $this->active = $active;
        $this->createdAt = $createdAt;
        $this->updatedAt = $createdAt;
    }

    public function __toString(): string
    {
        return sprintf('%s <%s>', $this->displayName, $this->email);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = strtolower(trim($email));
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->passwordHash;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(string $passwordHash): void
    {
        $this->passwordHash = $passwordHash;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): void
    {
        $this->displayName = trim($displayName);
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): void
    {
        $this->roles = $this->normalizeRoles($roles);
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function markLoggedIn(\DateTimeImmutable $at): void
    {
        $this->lastLoginAt = $at;
        $this->updatedAt = $at;
    }

    public function eraseCredentials(): void
    {
    }

    /**
     * @param list<string> $roles
     * @return list<string>
     */
    private function normalizeRoles(array $roles): array
    {
        $normalized = array_values(array_unique(array_filter(array_map(
            static fn (mixed $role): string => strtoupper(trim((string) $role)),
            $roles,
        ))));

        if (!in_array('ROLE_ADMIN', $normalized, true) && !in_array('ROLE_SUPER_ADMIN', $normalized, true)) {
            $normalized[] = 'ROLE_ADMIN';
        }

        return $normalized;
    }
}
