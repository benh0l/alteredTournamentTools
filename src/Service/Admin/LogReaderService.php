<?php

declare(strict_types=1);

namespace App\Service\Admin;

use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Service for reading application log files (FR69).
 */
class LogReaderService
{
    private string $logDir;

    public function __construct(KernelInterface $kernel)
    {
        $this->logDir = $kernel->getLogDir();
    }

    /**
     * Read log entries with optional filtering.
     *
     * @param string|null              $level     Filter by log level
     * @param \DateTimeInterface|null  $startDate Filter from date
     * @param \DateTimeInterface|null  $endDate   Filter to date
     * @param string|null              $keyword   Search in message
     * @param int                      $page      Page number (1-based)
     * @param int                      $limit     Entries per page
     *
     * @return array{entries: array, total: int, page: int, limit: int, totalPages: int}
     */
    public function readLogs(
        ?string $level = null,
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null,
        ?string $keyword = null,
        int $page = 1,
        int $limit = 100
    ): array {
        $logFile = $this->getLogFilePath();

        if (!file_exists($logFile)) {
            return [
                'entries' => [],
                'total' => 0,
                'page' => $page,
                'limit' => $limit,
                'totalPages' => 0,
            ];
        }

        $entries = $this->parseLogFile($logFile, $level, $startDate, $endDate, $keyword);

        $total = \count($entries);
        $totalPages = (int) ceil($total / $limit);
        $offset = ($page - 1) * $limit;

        return [
            'entries' => \array_slice($entries, $offset, $limit),
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => $totalPages,
        ];
    }

    /**
     * Get available log files (for multi-file support with rotating logs).
     *
     * @return array<string, string>
     */
    public function getAvailableLogFiles(): array
    {
        $files = [];
        $pattern = $this->logDir . '/app*.log';
        $matches = glob($pattern);

        if ($matches === false) {
            return [];
        }

        foreach ($matches as $file) {
            $files[basename($file)] = $file;
        }

        // Sort by filename (most recent first for dated files)
        krsort($files);

        return $files;
    }

    /**
     * Get available log levels for filtering.
     *
     * @return array<string>
     */
    public function getAvailableLevels(): array
    {
        return ['DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];
    }

    /**
     * Get the main log file path.
     */
    private function getLogFilePath(): string
    {
        // Try dated log file first (rotating file format)
        $today = date('Y-m-d');
        $datedLogFile = $this->logDir . '/app-' . $today . '.log';

        if (file_exists($datedLogFile)) {
            return $datedLogFile;
        }

        // Fallback to main app.log
        return $this->logDir . '/app.log';
    }

    /**
     * Parse log file and filter entries.
     *
     * @return array<array>
     */
    private function parseLogFile(
        string $logFile,
        ?string $level,
        ?\DateTimeInterface $startDate,
        ?\DateTimeInterface $endDate,
        ?string $keyword
    ): array {
        $handle = fopen($logFile, 'r');
        if ($handle === false) {
            return [];
        }

        $entries = [];
        $levelUpper = $level !== null ? strtoupper($level) : null;

        // Read file line by line (from end for most recent first)
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            fclose($handle);
            return [];
        }

        // Process in reverse order (most recent first)
        foreach (array_reverse($lines) as $line) {
            $entry = $this->parseLogLine($line);

            if ($entry === null) {
                continue;
            }

            // Apply level filter
            if ($levelUpper !== null && strtoupper($entry['level_name']) !== $levelUpper) {
                continue;
            }

            // Apply date filters
            if ($startDate !== null || $endDate !== null) {
                $entryDate = $this->parseLogDate($entry['datetime'] ?? '');
                if ($entryDate === null) {
                    continue;
                }

                if ($startDate !== null && $entryDate < $startDate) {
                    continue;
                }

                if ($endDate !== null && $entryDate > $endDate) {
                    continue;
                }
            }

            // Apply keyword filter
            if ($keyword !== null) {
                $searchText = strtolower($entry['message'] ?? '');
                if (stripos($searchText, $keyword) === false) {
                    // Also search in context
                    $contextText = json_encode($entry['context'] ?? []);
                    if ($contextText === false || stripos($contextText, $keyword) === false) {
                        continue;
                    }
                }
            }

            $entries[] = $entry;
        }

        fclose($handle);

        return $entries;
    }

    /**
     * Parse a single log line (JSON format).
     *
     * @return array|null
     */
    private function parseLogLine(string $line): ?array
    {
        // Try JSON format first
        $entry = json_decode($line, true);

        if ($entry !== null && isset($entry['message'])) {
            return $entry;
        }

        // Try Symfony standard format: [2026-01-11T10:30:00.000000+00:00] app.INFO: Message {"context"} []
        if (preg_match('/^\[([^\]]+)\] (\w+)\.(\w+): (.+)$/', $line, $matches)) {
            $contextStart = strrpos($matches[4], ' {');
            $message = $contextStart !== false ? substr($matches[4], 0, $contextStart) : $matches[4];
            $contextJson = $contextStart !== false ? substr($matches[4], $contextStart + 1) : '{}';

            // Try to extract context
            $context = [];
            if (preg_match('/\{[^}]*\}/', $contextJson, $contextMatches)) {
                $decoded = json_decode($contextMatches[0], true);
                if ($decoded !== null) {
                    $context = $decoded;
                }
            }

            return [
                'datetime' => $matches[1],
                'channel' => $matches[2],
                'level_name' => $matches[3],
                'message' => trim($message),
                'context' => $context,
            ];
        }

        return null;
    }

    /**
     * Parse log date string to DateTimeInterface.
     */
    private function parseLogDate(string $dateString): ?\DateTimeInterface
    {
        // Try ISO format
        $date = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $dateString);
        if ($date !== false) {
            return $date;
        }

        // Try alternative format
        $date = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.uP', $dateString);
        if ($date !== false) {
            return $date;
        }

        // Try standard format
        $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', substr($dateString, 0, 19));
        if ($date !== false) {
            return $date;
        }

        return null;
    }
}
