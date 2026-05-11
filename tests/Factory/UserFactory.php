<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Identity\Domain\Entity\User;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<User>
 */
final class UserFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return User::class;
    }

    protected function defaults(): array
    {
        $firstName = self::faker()->firstName();
        $lastName = self::faker()->lastName();

        return [
            'id' => Uuid::v7()->toRfc4122(),
            'email' => strtolower(self::faker()->unique()->safeEmail()),
            'passwordHash' => password_hash('Passw0rd!Passw0rd!', PASSWORD_BCRYPT),
            'firstName' => $firstName,
            'lastName' => $lastName,
            'createdAt' => new \DateTimeImmutable(),
        ];
    }
}
