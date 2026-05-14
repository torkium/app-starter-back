<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

interface DomainEventInterface
{
    public function topic(): string;

    public function channel(): string;

    /**
     * @return array<string, mixed>
     */
    public function payload(): array;
}
