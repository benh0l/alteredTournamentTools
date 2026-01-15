<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\NotificationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to send D-1 (day before) tournament notifications.
 *
 * This command should be scheduled to run daily at 8 AM.
 *
 * Usage:
 *   php bin/console app:send-day-before-notifications
 *
 * Cron example:
 *   0 8 * * * php /path/to/bin/console app:send-day-before-notifications
 */
#[AsCommand(
    name: 'app:send-day-before-notifications',
    description: 'Send reminder notifications to players for tournaments happening tomorrow',
)]
final class SendDayBeforeNotificationsCommand extends Command
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Run without actually sending notifications'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');

        $io->title('Sending Day-Before Tournament Notifications');

        if ($dryRun) {
            $io->note('DRY RUN - No notifications will be sent');
        }

        $this->logger->info('Starting day-before notifications job', [
            'dry_run' => $dryRun,
        ]);

        try {
            $tomorrow = (new \DateTimeImmutable())->modify('+1 day');
            $io->text(sprintf('Looking for tournaments on %s...', $tomorrow->format('Y-m-d')));

            if ($dryRun) {
                $io->success('Dry run completed - no notifications sent');

                return Command::SUCCESS;
            }

            $stats = $this->notificationService->queueDayBeforeNotifications();

            $this->logger->info('Day-before notifications job completed', $stats);

            $io->success(sprintf(
                'Job completed: %d notifications queued, %d skipped (already sent)',
                $stats['queued'],
                $stats['skipped']
            ));

            if ($stats['queued'] > 0) {
                $io->note('Notifications are queued for async processing via Symfony Messenger');
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->logger->error('Day-before notifications job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $io->error(sprintf('Job failed: %s', $e->getMessage()));

            return Command::FAILURE;
        }
    }
}
