<?php

declare(strict_types=1);

namespace App\Identity\Application\Port;

interface TokenGeneratorInterface
{
    public function generate(): string;
}
