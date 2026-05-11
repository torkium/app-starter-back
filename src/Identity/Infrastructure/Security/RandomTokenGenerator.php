<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Application\Port\TokenGeneratorInterface;

final class RandomTokenGenerator implements TokenGeneratorInterface
{
    public function generate(): string
    {
        return bin2hex(random_bytes(24));
    }
}
