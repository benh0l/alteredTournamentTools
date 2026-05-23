<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ContactReportRepository;
use App\Repository\ResetPasswordRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * GDPR data retention cleanup command.
 *
 * Implements data retention policy:
 * - Password reset tokens: deleted after expiration (1 hour)
 * - Contact reports: deleted after 12 months
 * - Tournament data: anonymized after 36 months (future implementation)
 *
 * Run this command via cron daily:
 * 0 3 * * * php /path/to/bin/console app:gdpr-cleanup
 */
#[AsCommand(
    name: 'app:gdpr-cleanup',
    description: 'Clean up expired data according to GDPR retention policy',
)]
class GdprCleanupCommand extends Command
{
    private const DEFAULT_CONTACT_REPORT_RETENTION_MONTHS = 12;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ResetPasswordRequestRepository $resetPasswordRepository,
        private readonly ContactReportRepository $contactReportRepository,
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
                'Show what would be deleted without actually deleting'
            )
            ->addOption(
                'contact-months',
                null,
                InputOption::VALUE_REQUIRED,
                'Months to keep contact reports',
                (string) self::DEFAULT_CONTACT_REPORT_RETENTION_MONTHS
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $contactMonths = (int) $input->getOption('contact-months');

        $io->title('GDPR Data Retention Cleanup');

        if ($dryRun) {
            $io->note('DRY RUN MODE - No data will be deleted');
        }

        $totalDeleted = 0;

        // 1. Clean expired password reset tokens
        $io->section('Password Reset Tokens');
        $expiredTokens = $this->cleanExpiredPasswordTokens($io, $dryRun);
        $totalDeleted += $expiredTokens;

        // 2. Clean old contact reports
        $io->section('Contact Reports');
        $oldReports = $this->cleanOldContactReports($io, $dryRun, $contactMonths);
        $totalDeleted += $oldReports;

        // Summary
        $io->section('Summary');
        $io->table(
            ['Data Type', 'Deleted'],
            [
                ['Password Reset Tokens', $expiredTokens],
                ['Contact Reports', $oldReports],
            ]
        );

        if ($dryRun) {
            $io->warning(sprintf('DRY RUN: %d record(s) would be deleted.', $totalDeleted));
        } else {
            $io->success(sprintf('Cleanup complete. %d record(s) deleted.', $totalDeleted));

            $this->logger->info('GDPR cleanup completed', [
                'expired_tokens' => $expiredTokens,
                'old_reports' => $oldReports,
                'total' => $totalDeleted,
            ]);
        }

        return Command::SUCCESS;
    }

    private function cleanExpiredPasswordTokens(SymfonyStyle $io, bool $dryRun): int
    {
        if ($dryRun) {
            $count = $this->resetPasswordRepository->countExpired();
            $io->info(sprintf('Found %d expired token(s)', $count));

            return $count;
        }

        $deleted = $this->resetPasswordRepository->removeExpired();
        $io->info(sprintf('Deleted %d expired token(s)', $deleted));

        return $deleted;
    }

    private function cleanOldContactReports(SymfonyStyle $io, bool $dryRun, int $months): int
    {
        $cutoffDate = new \DateTimeImmutable(sprintf('-%d months', $months));

        $qb = $this->entityManager->createQueryBuilder()
            ->from(\App\Entity\ContactReport::class, 'c')
            ->where('c.createdAt < :cutoff')
            ->setParameter('cutoff', $cutoffDate);

        if ($dryRun) {
            $count = (int) (clone $qb)
                ->select('COUNT(c.id)')
                ->getQuery()
                ->getSingleScalarResult();

            $io->info(sprintf('Found %d report(s) older than %d months', $count, $months));

            return $count;
        }

        $count = (clone $qb)
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $qb->delete()->getQuery()->execute();

        $io->info(sprintf('Deleted %d report(s) older than %d months', $count, $months));

        return (int) $count;
    }
}
