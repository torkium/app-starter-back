<?php

declare(strict_types=1);

namespace App\Tests\Unit\Billing;

use App\Billing\Application\Port\StripeCheckoutGatewayInterface;
use App\Billing\Application\Service\BillingManager;
use App\Billing\Domain\Entity\BillingEvent;
use App\Identity\Domain\Entity\User;
use App\Shared\Application\Port\ClockInterface;
use App\Shared\Application\Port\OutboxRecorderInterface;
use App\Shared\Application\Port\TransactionManagerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

final class BillingManagerTest extends TestCase
{
    public function testHistoryUsesStableOrderingAndPaginationBounds(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository
            ->expects(self::once())
            ->method('findBy')
            ->with(
                self::callback(static fn (array $criteria): bool => ($criteria['user'] ?? null) instanceof User),
                ['occurredAt' => 'DESC', 'id' => 'DESC'],
                11,
                5,
            )
            ->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('getRepository')
            ->with(BillingEvent::class)
            ->willReturn($repository);

        $manager = new BillingManager(
            $entityManager,
            $this->createStub(StripeCheckoutGatewayInterface::class),
            $this->createStub(TransactionManagerInterface::class),
            $this->createStub(OutboxRecorderInterface::class),
            $this->createStub(ClockInterface::class),
            'https://app.example.test',
        );

        $result = $manager->history(
            new User(
                '018f6f0e-7c4b-7f44-9f6a-6a69341b7f39',
                'user@example.test',
                'hash',
                'Ada',
                'Lovelace',
                new \DateTimeImmutable('2026-05-14T10:00:00+00:00'),
            ),
            10,
            5,
        );

        self::assertSame([], $result['items']);
        self::assertSame([
            'limit' => 10,
            'offset' => 5,
            'nextOffset' => null,
            'hasMore' => false,
        ], $result['pagination']);
    }
}
