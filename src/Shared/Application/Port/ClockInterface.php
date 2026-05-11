<?php

declare(strict_types=1);

namespace App\Shared\Application\Port;

interface ClockInterface
{
    public function now(): \DateTimeImmutable;
}
