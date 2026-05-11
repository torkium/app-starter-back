<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Console;

use App\Shared\Application\Message\DispatchOutboxMessage;
use App\Shared\Application\Port\ClockInterface;
use App\Shared\Domain\Entity\OutboxMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

#[AsCommand(name: 'app:schedule:run')]
final class RunScheduleCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connection = $this->entityManager->getConnection();
        $now = $this->clock->now();
        $claimToken = Uuid::v7()->toRfc4122();
        $staleBefore = $now->modify('-5 minutes');
        $deliveryTimedOutBefore = $now->modify('-15 minutes');

        $timedOut = $connection->executeStatement(
            "UPDATE outbox_messages
             SET failed_at = :failed_at, failure_reason = :failure_reason, claim_token = NULL, claimed_at = NULL
             WHERE published_at IS NULL
               AND failed_at IS NULL
               AND delivery_started_at IS NOT NULL
               AND delivery_started_at < :delivery_timed_out_before",
            [
                'failed_at' => $now,
                'failure_reason' => 'Delivery timed out before completion.',
                'delivery_timed_out_before' => $deliveryTimedOutBefore,
            ],
            [
                'failed_at' => 'datetime',
                'delivery_timed_out_before' => 'datetime',
            ],
        );

        $connection->executeStatement(
            'UPDATE outbox_messages SET claim_token = NULL, claimed_at = NULL WHERE published_at IS NULL AND failed_at IS NULL AND delivery_started_at IS NULL AND claimed_at IS NOT NULL AND claimed_at < :stale_before',
            ['stale_before' => $staleBefore],
            ['stale_before' => 'datetime'],
        );

        $claimed = $connection->executeStatement(
            'UPDATE outbox_messages SET claim_token = :claim_token, claimed_at = :claimed_at WHERE published_at IS NULL AND failed_at IS NULL AND delivery_started_at IS NULL AND claim_token IS NULL ORDER BY created_at ASC LIMIT 50',
            ['claim_token' => $claimToken, 'claimed_at' => $now],
            ['claimed_at' => 'datetime'],
        );

        if ($claimed <= 0) {
            $output->writeln(sprintf('Dispatched 0 outbox message(s). Marked %d timed out message(s) as failed.', $timedOut));

            return Command::SUCCESS;
        }

        /** @var list<OutboxMessage> $pending */
        $pending = $this->entityManager->getRepository(OutboxMessage::class)->findBy(['claimToken' => $claimToken], ['createdAt' => 'ASC']);

        foreach ($pending as $message) {
            $this->messageBus->dispatch(new DispatchOutboxMessage($message->getId(), $claimToken));
        }

        $output->writeln(sprintf('Dispatched %d outbox message(s). Marked %d timed out message(s) as failed.', count($pending), $timedOut));

        return Command::SUCCESS;
    }
}
