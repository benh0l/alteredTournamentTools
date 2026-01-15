<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\EmailService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:email:test',
    description: 'Send a test email to verify mailer configuration',
)]
final class TestEmailCommand extends Command
{
    public function __construct(
        private readonly EmailService $emailService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Recipient email address')
            ->addArgument('name', InputArgument::OPTIONAL, 'Recipient name', 'Test User');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getArgument('email');
        $name = $input->getArgument('name');

        try {
            $this->emailService->sendTestEmail($email, $name);
            $io->success(sprintf('Test email sent to %s', $email));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(sprintf('Failed to send email: %s', $e->getMessage()));

            return Command::FAILURE;
        }
    }
}
