<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:schedule:run')]
final class RunScheduleCommand extends Command
{
    public function __construct(
        private readonly ConsumeOutboxCommand $outboxCommand,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->outboxCommand->run(new ArrayInput([
            '--batch' => '50',
        ]), $output);
    }
}
