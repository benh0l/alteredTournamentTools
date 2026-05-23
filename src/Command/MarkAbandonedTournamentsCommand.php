<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\TournamentStatus;
use App\Repository\TournamentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to mark inactive ONGOING tournaments as ABANDONED.
 *
 * Tournaments are considered abandoned when they have no activity
 * (no tournament updates, no match completions) for 3+ days.
 *
 * This command should be scheduled to run daily.
 *
 * Usage:
 *   php bin/console app:mark-abandoned-tournaments
 *
 * Cron example:
 *   0 2 * * * php /path/to/bin/console app:mark-abandoned-tournaments
 */
#[AsCommand(
    name: 'app:mark-abandoned-tournaments',
    description: 'Mark inactive ONGOING tournaments as ABANDONED after 3 days without activity',
)]
final class MarkAbandonedTournamentsCommand extends Command
{
    private const DEFAULT_INACTIVE_DAYS = 3;

    public function __construct(
        private readonly TournamentRepository $tournamentRepository,
        private readonly EntityManagerInterface $entityManager,
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
                'Run without actually updating tournaments'
            )
            ->addOption(
                'days',
                'd',
                InputOption::VALUE_REQUIRED,
                'Number of inactive days before marking as abandoned',
                (string) self::DEFAULT_INACTIVE_DAYS
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $inactiveDays = (int) $input->getOption('days');

        $io->title('Marking Abandoned Tournaments');

        if ($dryRun) {
            $io->note('DRY RUN - No tournaments will be updated');
        }

        $io->text(sprintf('Looking for ONGOING tournaments with no activity for %d+ days...', $inactiveDays));

        $this->logger->info('Starting mark-abandoned-tournaments job', [
            'dry_run' => $dryRun,
            'inactive_days' => $inactiveDays,
        ]);

        try {
            $tournaments = $this->tournamentRepository->findInactiveOngoingTournaments($inactiveDays);

            if (empty($tournaments)) {
                $io->success('No inactive tournaments found.');
                $this->logger->info('No inactive tournaments to mark as abandoned');

                return Command::SUCCESS;
            }

            $io->text(sprintf('Found %d inactive tournament(s)', count($tournaments)));

            if ($dryRun) {
                $io->section('Tournaments that would be marked as ABANDONED:');
                foreach ($tournaments as $tournament) {
                    $io->text(sprintf(
                        '  - [ID: %d] %s (last update: %s)',
                        $tournament->getId(),
                        $tournament->getName(),
                        $tournament->getUpdatedAt()?->format('Y-m-d H:i:s') ?? 'N/A'
                    ));
                }
                $io->success('Dry run completed - no tournaments updated');

                return Command::SUCCESS;
            }

            $count = 0;
            foreach ($tournaments as $tournament) {
                $tournament->setStatus(TournamentStatus::ABANDONED);
                $count++;

                $this->logger->info('Tournament marked as abandoned', [
                    'tournament_id' => $tournament->getId(),
                    'tournament_name' => $tournament->getName(),
                ]);
            }

            $this->entityManager->flush();

            $this->logger->info('Mark-abandoned-tournaments job completed', [
                'tournaments_updated' => $count,
            ]);

            $io->success(sprintf('%d tournament(s) marked as ABANDONED', $count));

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->logger->error('Mark-abandoned-tournaments job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $io->error(sprintf('Job failed: %s', $e->getMessage()));

            return Command::FAILURE;
        }
    }
}
