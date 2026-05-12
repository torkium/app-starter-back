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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

#[AsCommand(name: 'app:outbox:consume', description: 'Claim and dispatch pending outbox messages.')]
final class ConsumeOutboxCommand extends Command
{
    public const MAX_BATCH_SIZE = 500;
    private const MAX_DELIVERY_TIMEOUT_MINUTES = 1440;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('channel', null, InputOption::VALUE_REQUIRED, 'Optional outbox channel filter.')
            ->addOption('loop', null, InputOption::VALUE_NONE, 'Poll continuously until interrupted.')
            ->addOption('sleep', null, InputOption::VALUE_REQUIRED, 'Sleep duration in seconds between empty polls.', '1')
            ->addOption('batch', null, InputOption::VALUE_REQUIRED, 'Maximum batch size to claim per iteration.', '50')
            ->addOption('stale-minutes', null, InputOption::VALUE_REQUIRED, 'Release claims older than this number of minutes.', '5')
            ->addOption('delivery-timeout-minutes', null, InputOption::VALUE_REQUIRED, 'Mark in-flight deliveries as failed after this timeout.', '15');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $loop = (bool) $input->getOption('loop');
        $sleep = max(1, (int) $input->getOption('sleep'));
        $batchSize = min(self::MAX_BATCH_SIZE, max(1, (int) $input->getOption('batch')));
        $staleMinutes = max(1, (int) $input->getOption('stale-minutes'));
        $deliveryTimeoutMinutes = min(self::MAX_DELIVERY_TIMEOUT_MINUTES, max(1, (int) $input->getOption('delivery-timeout-minutes')));
        $channel = trim((string) $input->getOption('channel'));

        do {
            [$dispatched, $releasedTimedOut] = $this->claimAndDispatch($channel !== '' ? $channel : null, $batchSize, $staleMinutes, $deliveryTimeoutMinutes);

            if ($dispatched === 0) {
                $output->writeln(sprintf('Dispatched 0 outbox message(s). Released %d timed out message(s) for retry.', $releasedTimedOut));

                if (!$loop) {
                    return Command::SUCCESS;
                }

                sleep($sleep);
                continue;
            }

            $output->writeln(sprintf('Dispatched %d outbox message(s). Released %d timed out message(s) for retry.', $dispatched, $releasedTimedOut));
        } while ($loop);

        return Command::SUCCESS;
    }

    /**
     * @return array{0:int,1:int}
     */
    private function claimAndDispatch(?string $channel, int $batchSize, int $staleMinutes, int $deliveryTimeoutMinutes): array
    {
        $connection = $this->entityManager->getConnection();
        $now = $this->clock->now();
        $claimToken = Uuid::v7()->toRfc4122();
        $staleBefore = $now->modify(sprintf('-%d minutes', $staleMinutes));
        $deliveryTimedOutBefore = $now->modify(sprintf('-%d minutes', $deliveryTimeoutMinutes));

        $releasedTimedOut = $this->releaseTimedOutDeliveries($deliveryTimedOutBefore, $now, $channel);

        $releaseSql = 'UPDATE outbox_messages SET claim_token = NULL, claimed_at = NULL WHERE published_at IS NULL AND failed_at IS NULL AND delivery_started_at IS NULL AND claimed_at IS NOT NULL AND claimed_at < :stale_before';
        $connection->executeStatement($releaseSql, ['stale_before' => $staleBefore], ['stale_before' => 'datetime_immutable']);

        $claimSql = 'UPDATE outbox_messages SET claim_token = :claim_token, claimed_at = :claimed_at WHERE published_at IS NULL AND failed_at IS NULL AND delivery_started_at IS NULL AND claim_token IS NULL AND (next_attempt_at IS NULL OR next_attempt_at <= :now)';
        $params = ['claim_token' => $claimToken, 'claimed_at' => $now];
        $params['now'] = $now;
        $types = ['claimed_at' => 'datetime_immutable', 'now' => 'datetime_immutable'];

        if (null !== $channel) {
            $claimSql .= ' AND channel = :channel';
            $params['channel'] = $channel;
        }

        $claimSql .= ' ORDER BY created_at ASC LIMIT '.$batchSize;

        $claimed = $connection->executeStatement($claimSql, $params, $types);
        if ($claimed <= 0) {
            return [0, $releasedTimedOut];
        }

        /** @var list<OutboxMessage> $pending */
        $pending = $this->entityManager->getRepository(OutboxMessage::class)->findBy(['claimToken' => $claimToken], ['createdAt' => 'ASC']);
        foreach ($pending as $message) {
            $this->messageBus->dispatch(new DispatchOutboxMessage($message->getId(), $claimToken));
        }

        return [count($pending), $releasedTimedOut];
    }

    private function releaseTimedOutDeliveries(\DateTimeImmutable $deliveryTimedOutBefore, \DateTimeImmutable $now, ?string $channel): int
    {
        $legacyPlainMailSql = "UPDATE outbox_messages
            SET delivery_started_at = NULL,
                claim_token = NULL,
                claimed_at = NULL,
                failed_at = :now,
                failure_reason = :legacy_failure_reason,
                next_attempt_at = NULL,
                payload = JSON_OBJECT('redacted', TRUE, 'reason', 'mail_payload_protected')
            WHERE published_at IS NULL
              AND failed_at IS NULL
              AND delivery_started_at IS NOT NULL
              AND delivery_started_at < :delivery_timed_out_before
              AND channel = 'mail'
              AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(payload, '$._encrypted')), 'false') <> 'true'";
        $legacyPlainMailParams = [
            'now' => $now,
            'legacy_failure_reason' => 'Delivery timed out with an unprotected legacy mail payload.',
            'delivery_timed_out_before' => $deliveryTimedOutBefore,
        ];
        $legacyPlainMailTypes = [
            'now' => 'datetime_immutable',
            'delivery_timed_out_before' => 'datetime_immutable',
        ];

        if (null !== $channel) {
            $legacyPlainMailSql .= ' AND channel = :channel';
            $legacyPlainMailParams['channel'] = $channel;
        }

        $legacyPlainMailFailures = $this->entityManager->getConnection()->executeStatement($legacyPlainMailSql, $legacyPlainMailParams, $legacyPlainMailTypes);

        $sql = "UPDATE outbox_messages
            SET delivery_started_at = NULL,
                claim_token = NULL,
                claimed_at = NULL,
                failure_reason = :failure_reason,
                failed_at = CASE WHEN attempt_count >= :max_attempts THEN :now ELSE failed_at END,
                next_attempt_at = CASE WHEN attempt_count >= :max_attempts THEN NULL ELSE :next_attempt_at END
            WHERE published_at IS NULL
              AND failed_at IS NULL
              AND delivery_started_at IS NOT NULL
              AND delivery_started_at < :delivery_timed_out_before
              AND (channel <> 'mail' OR COALESCE(JSON_UNQUOTE(JSON_EXTRACT(payload, '$._encrypted')), 'false') = 'true')";
        $params = [
            'max_attempts' => OutboxMessage::MAX_DELIVERY_ATTEMPTS,
            'now' => $now,
            'failure_reason' => 'Delivery timed out before completion.',
            'next_attempt_at' => $now->modify('+60 seconds'),
            'delivery_timed_out_before' => $deliveryTimedOutBefore,
        ];
        $types = [
            'now' => 'datetime_immutable',
            'next_attempt_at' => 'datetime_immutable',
            'delivery_timed_out_before' => 'datetime_immutable',
        ];

        if (null !== $channel) {
            $sql .= ' AND channel = :channel';
            $params['channel'] = $channel;
        }

        return $legacyPlainMailFailures + $this->entityManager->getConnection()->executeStatement($sql, $params, $types);
    }
}
