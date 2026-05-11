<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Admin\Domain\Entity\AdminUser;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<AdminUser>
 */
final class AdminUserFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return AdminUser::class;
    }

    protected function defaults(): array
    {
        return [
            'id' => Uuid::v7()->toRfc4122(),
            'email' => strtolower(self::faker()->unique()->safeEmail()),
            'passwordHash' => password_hash('AdminPassw0rd!AdminPassw0rd!', PASSWORD_BCRYPT),
            'displayName' => self::faker()->name(),
            'roles' => ['ROLE_ADMIN'],
            'createdAt' => new \DateTimeImmutable(),
            'active' => true,
        ];
    }
}
