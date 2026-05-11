<?php

declare(strict_types=1);

namespace App\Identity\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[UniqueEntity(fields: ['email'], message: 'This user email is already used.')]
#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(length: 255)]
    private string $passwordHash;

    #[ORM\Column(length: 120)]
    private string $firstName;

    #[ORM\Column(length: 120)]
    private string $lastName;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $roles = ['ROLE_USER'];

    #[ORM\Column]
    private bool $emailVerified = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $id, string $email, string $passwordHash, string $firstName, string $lastName, \DateTimeImmutable $createdAt)
    {
        $this->id = $id;
        $this->email = strtolower(trim($email));
        $this->passwordHash = $passwordHash;
        $this->firstName = trim($firstName);
        $this->lastName = trim($lastName);
        $this->createdAt = $createdAt;
        $this->updatedAt = $createdAt;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function __toString(): string
    {
        return sprintf('%s %s <%s>', $this->firstName, $this->lastName, $this->email);
    }

    public function getEmail(): string
    {
        return $this->email;
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

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return array_values(array_unique($this->roles));
    }

    public function eraseCredentials(): void
    {
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerified;
    }

    public function markEmailVerified(): void
    {
        $this->emailVerified = true;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function rename(string $firstName, string $lastName): void
    {
        $this->firstName = trim($firstName);
        $this->lastName = trim($lastName);
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function changeEmail(string $email): void
    {
        $this->email = strtolower(trim($email));
        $this->emailVerified = false;
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * @param list<string> $roles
     */
    public function replaceRoles(array $roles): void
    {
        $normalized = array_values(array_unique(array_filter(array_map(
            static fn (mixed $role): string => strtoupper(trim((string) $role)),
            $roles,
        ))));

        $this->roles = [] !== $normalized ? $normalized : ['ROLE_USER'];
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function setEmailVerified(bool $emailVerified): void
    {
        $this->emailVerified = $emailVerified;
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
}
