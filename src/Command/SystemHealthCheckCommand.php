<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Admin\SystemHealthService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * System health check command (FR75).
 *
 * Run this command via cron every 5 minutes:
 * /5 * * * * php /path/to/bin/console app:system-health-check
 */
#[AsCommand(
    name: 'app:system-health-check',
    description: 'Run system health checks and create alerts for issues',
)]
class SystemHealthCheckCommand extends Command
{
    public function __construct(
        private readonly SystemHealthService $healthService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('cleanup', null, InputOption::VALUE_NONE, 'Also cleanup old acknowledged alerts')
            ->addOption('cleanup-days', null, InputOption::VALUE_REQUIRED, 'Days to keep acknowledged alerts', '30');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('System Health Check');

        // Run all health checks
        $io->section('Running health checks...');

        $newAlerts = $this->healthService->runAllChecks();

        if (count($newAlerts) > 0) {
            $io->warning(sprintf('%d new alert(s) created:', count($newAlerts)));

            foreach ($newAlerts as $alert) {
                $io->writeln(sprintf(
                    '  [%s] %s: %s',
                    strtoupper($alert->getSeverity()->value),
                    $alert->getType()->getLabel(),
                    $alert->getMessage()
                ));
            }
        } else {
            $io->success('No issues detected.');
        }

        // Optional cleanup
        if ($input->getOption('cleanup')) {
            $days = (int) $input->getOption('cleanup-days');
            $io->section(sprintf('Cleaning up alerts older than %d days...', $days));

            $deleted = $this->healthService->cleanupOldAlerts($days);

            if ($deleted > 0) {
                $io->info(sprintf('Deleted %d old acknowledged alert(s).', $deleted));
            } else {
                $io->info('No old alerts to clean up.');
            }
        }

        // Summary
        $io->section('Current Alert Summary');
        $counts = $this->healthService->getAlertCounts();

        $io->table(
            ['Severity', 'Active Count'],
            [
                ['Critical', $counts['critical']],
                ['Warning', $counts['warning']],
                ['Info', $counts['info']],
            ]
        );

        return Command::SUCCESS;
    }
}
