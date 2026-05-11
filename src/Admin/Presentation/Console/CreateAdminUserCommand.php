<?php

declare(strict_types=1);

namespace App\Admin\Presentation\Console;

use App\Admin\Domain\Entity\AdminUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

#[AsCommand(name: 'app:admin-user:create', description: 'Create a back-office admin user.')]
final class CreateAdminUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED)
            ->addArgument('password', InputArgument::OPTIONAL)
            ->addArgument('display-name', InputArgument::OPTIONAL, 'Displayed admin name')
            ->addOption('super-admin', null, InputOption::VALUE_NONE, 'Grant ROLE_SUPER_ADMIN as well.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = strtolower(trim((string) $input->getArgument('email')));
        $password = trim((string) ($input->getArgument('password') ?? ''));
        $displayName = trim((string) ($input->getArgument('display-name') ?? ''));

        if ('' === $password) {
            if (!$input->isInteractive()) {
                $io->error('Password argument is required in non-interactive mode.');

                return Command::FAILURE;
            }

            $question = new Question('Admin password: ');
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            $password = trim((string) $this->getHelper('question')->ask($input, $output, $question));
        }

        if ('' === $password) {
            $io->error('Admin password cannot be empty.');

            return Command::FAILURE;
        }

        if ('' === $displayName) {
            $displayName = $email;
        }

        $existing = $this->entityManager->getRepository(AdminUser::class)->findOneBy(['email' => $email]);
        if ($existing instanceof AdminUser) {
            $io->error(sprintf('An admin user already exists for "%s".', $email));

            return Command::FAILURE;
        }

        $roles = $input->getOption('super-admin') ? ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'] : ['ROLE_ADMIN'];

        $adminUser = new AdminUser(
            Uuid::v7()->toRfc4122(),
            $email,
            '',
            $displayName,
            $roles,
            new \DateTimeImmutable(),
        );
        $adminUser->setPasswordHash($this->passwordHasher->hashPassword($adminUser, $password));

        $this->entityManager->persist($adminUser);
        $this->entityManager->flush();

        $io->success(sprintf('Admin user "%s" created.', $email));

        return Command::SUCCESS;
    }
}
