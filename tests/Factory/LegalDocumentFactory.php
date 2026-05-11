<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Identity\Domain\Entity\LegalDocument;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<LegalDocument>
 */
final class LegalDocumentFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return LegalDocument::class;
    }

    protected function defaults(): array
    {
        return [
            'id' => Uuid::v7()->toRfc4122(),
            'code' => strtoupper(self::faker()->unique()->lexify('doc_????')),
            'version' => 'v1',
            'locale' => 'fr',
            'title' => self::faker()->sentence(3),
            'content' => self::faker()->paragraph(),
            'active' => true,
            'publishedAt' => new \DateTimeImmutable('-1 day'),
            'createdAt' => new \DateTimeImmutable('-1 day'),
        ];
    }
}
