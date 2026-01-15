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
 * Command to send H-1 (hour before) tournament notifications.
 *
 * This command should be scheduled to run every 15 minutes
 * to catch tournaments starting around the hour.
 *
 * Usage:
 *   php bin/console app:send-hour-before-notifications
 *
 * Cron example:
 *   \*\/15 * * * * php /path/to/bin/console app:send-hour-before-notifications
 */
#[AsCommand(
    name: 'app:send-hour-before-notifications',
    description: 'Send reminder notifications to players for tournaments starting in about 1 hour',
)]
final class SendHourBeforeNotificationsCommand extends Command
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

        $io->title('Sending Hour-Before Tournament Notifications');

        if ($dryRun) {
            $io->note('DRY RUN - No notifications will be sent');
        }

        $this->logger->info('Starting hour-before notifications job', [
            'dry_run' => $dryRun,
        ]);

        try {
            $now = new \DateTimeImmutable();
            $io->text(sprintf('Current time: %s', $now->format('Y-m-d H:i:s')));
            $io->text('Looking for tournaments starting in ~1 hour...');

            if ($dryRun) {
                $io->success('Dry run completed - no notifications sent');

                return Command::SUCCESS;
            }

            $stats = $this->notificationService->queueHourBeforeNotifications();

            $this->logger->info('Hour-before notifications job completed', $stats);

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
            $this->logger->error('Hour-before notifications job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $io->error(sprintf('Job failed: %s', $e->getMessage()));

            return Command::FAILURE;
        }
    }
}
