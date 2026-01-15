<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Security\Roles;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:role',
    description: 'Assign or remove a role from a user',
)]
final class AssignRoleCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'User email address')
            ->addArgument('role', InputArgument::REQUIRED, 'Role to assign (ROLE_ORGANIZER, ROLE_ADMIN)')
            ->addOption('remove', 'r', InputOption::VALUE_NONE, 'Remove the role instead of adding it');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getArgument('email');
        $role = strtoupper($input->getArgument('role'));
        $remove = $input->getOption('remove');

        // Validate role
        if (!in_array($role, Roles::assignable(), true)) {
            $io->error(sprintf(
                'Invalid role "%s". Valid roles: %s',
                $role,
                implode(', ', Roles::assignable()),
            ));

            return Command::FAILURE;
        }

        // Find user
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            $io->error(sprintf('User with email "%s" not found.', $email));

            return Command::FAILURE;
        }

        $currentRoles = $user->getRoles();

        if ($remove) {
            // Remove role
            if (!in_array($role, $currentRoles, true)) {
                $io->warning(sprintf('User does not have role "%s".', $role));

                return Command::SUCCESS;
            }

            $newRoles = array_filter($currentRoles, fn (string $r): bool => $r !== $role);
            $user->setRoles(array_values($newRoles));
            $this->entityManager->flush();

            $io->success(sprintf('Role "%s" removed from user "%s".', $role, $email));
        } else {
            // Add role
            if (in_array($role, $currentRoles, true)) {
                $io->warning(sprintf('User already has role "%s".', $role));

                return Command::SUCCESS;
            }

            $currentRoles[] = $role;
            $user->setRoles($currentRoles);
            $this->entityManager->flush();

            $io->success(sprintf('Role "%s" assigned to user "%s".', $role, $email));
        }

        $io->table(['Email', 'Current Roles'], [
            [$user->getEmail(), implode(', ', $user->getRoles())],
        ]);

        return Command::SUCCESS;
    }
}
