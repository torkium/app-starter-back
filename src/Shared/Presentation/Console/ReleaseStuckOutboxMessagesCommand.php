<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Console;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:outbox:release-stuck', description: 'Release stuck outbox deliveries for manual retry.')]
final class ReleaseStuckOutboxMessagesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('channel', null, InputOption::VALUE_REQUIRED, 'Optional channel filter.')
            ->addOption('older-than-minutes', null, InputOption::VALUE_REQUIRED, 'Only release deliveries older than this age.', '15')
            ->addOption('include-failed', null, InputOption::VALUE_NONE, 'Also release permanently failed messages for an explicit manual retry.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $olderThanMinutes = max(1, (int) $input->getOption('older-than-minutes'));
        $channel = trim((string) $input->getOption('channel'));
        $includeFailed = (bool) $input->getOption('include-failed');
        $before = new \DateTimeImmutable(sprintf('-%d minutes', $olderThanMinutes));

        $sql = 'UPDATE outbox_messages
            SET delivery_started_at = NULL, failed_at = NULL, failure_reason = NULL, claim_token = NULL, claimed_at = NULL
            WHERE published_at IS NULL
              AND delivery_started_at IS NOT NULL
              AND delivery_started_at < :before';

        if ($includeFailed) {
            $sql = 'UPDATE outbox_messages
                SET delivery_started_at = NULL,
                    failed_at = NULL,
                    failure_reason = NULL,
                    claim_token = NULL,
                    claimed_at = NULL,
                    attempt_count = 0,
                    next_attempt_at = NULL
                WHERE published_at IS NULL
                  AND (
                    (delivery_started_at IS NOT NULL AND delivery_started_at < :before)
                    OR (failed_at IS NOT NULL AND failed_at < :before)
                  )';
        }
        $params = ['before' => $before];
        $types = ['before' => 'datetime_immutable'];

        if ('' !== $channel) {
            $sql .= ' AND channel = :channel';
            $params['channel'] = $channel;
        }

        $released = $this->entityManager->getConnection()->executeStatement($sql, $params, $types);

        $output->writeln(sprintf('Released %d stuck outbox message(s).', $released));

        return Command::SUCCESS;
    }
}
