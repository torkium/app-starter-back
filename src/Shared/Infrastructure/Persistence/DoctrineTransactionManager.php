<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

use App\Shared\Application\Port\TransactionManagerInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineTransactionManager implements TransactionManagerInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function run(callable $callback): mixed
    {
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $result = $callback();
            $this->entityManager->flush();
            $connection->commit();

            return $result;
        } catch (\Throwable $throwable) {
            $connection->rollBack();
            throw $throwable;
        }
    }
}
