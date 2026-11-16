<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:promote-super-admin',
    description: 'Grant or revoke ROLE_SUPER_ADMIN for an adult account.',
)]
final class PromoteSuperAdminCommand extends Command
{
    private const ROLE = 'ROLE_SUPER_ADMIN';

    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
        private readonly ActivityLogger $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'E-mail of the adult account')
            ->addOption('revoke', null, InputOption::VALUE_NONE, 'Remove the super-admin role instead of granting it');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = mb_strtolower(trim((string) $input->getArgument('email')));
        $revoke = (bool) $input->getOption('revoke');

        $user = $this->users->findOneBy(['email' => $email]);

        if (!$user instanceof User) {
            $io->error(sprintf('No adult account found for "%s".', $email));

            return Command::FAILURE;
        }

        if ($revoke) {
            $user->demote(self::ROLE);
        } else {
            $user->promote(self::ROLE);
        }

        $this->em->flush();
        $this->logger->log(
            $revoke ? 'admin.demote' : 'admin.promote',
            'user',
            $user->getId(),
            ['email' => $user->getEmail(), 'role' => self::ROLE],
        );

        $io->success(sprintf(
            '%s %s for %s.',
            self::ROLE,
            $revoke ? 'revoked' : 'granted',
            $user->getEmail(),
        ));

        return Command::SUCCESS;
    }
}
