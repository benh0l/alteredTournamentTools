<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * DTO for bulk registration import results.
 *
 * Tracks the outcome of each row processed during import:
 * - Successful registrations (linked existing users + created guests)
 * - Skipped rows (already registered)
 * - Errors (invalid data)
 */
final class ImportResult
{
    private int $successCount = 0;
    private int $linkedCount = 0;
    private int $createdCount = 0;
    private array $skipped = [];
    private array $errors = [];
    private array $details = [];

    public function addSuccess(): void
    {
        $this->successCount++;
    }

    public function addLinked(int $line, string $email): void
    {
        $this->linkedCount++;
        $this->details[] = ['line' => $line, 'type' => 'linked', 'email' => $email];
    }

    public function addCreated(int $line, string $email): void
    {
        $this->createdCount++;
        $this->details[] = ['line' => $line, 'type' => 'created', 'email' => $email];
    }

    public function addSkipped(int $line, string $reason): void
    {
        $this->skipped[] = ['line' => $line, 'reason' => $reason];
    }

    public function addError(int $line, string $message): void
    {
        $this->errors[] = ['line' => $line, 'message' => $message];
    }

    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    public function getLinkedCount(): int
    {
        return $this->linkedCount;
    }

    public function getCreatedCount(): int
    {
        return $this->createdCount;
    }

    public function getSkipped(): array
    {
        return $this->skipped;
    }

    public function getSkippedCount(): int
    {
        return count($this->skipped);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getErrorCount(): int
    {
        return count($this->errors);
    }

    public function getDetails(): array
    {
        return $this->details;
    }

    public function getTotalProcessed(): int
    {
        return $this->successCount + $this->getSkippedCount() + $this->getErrorCount();
    }

    public function hasErrors(): bool
    {
        return $this->getErrorCount() > 0;
    }

    public function toArray(): array
    {
        return [
            'success_count' => $this->successCount,
            'linked_count' => $this->linkedCount,
            'created_count' => $this->createdCount,
            'skipped_count' => $this->getSkippedCount(),
            'error_count' => $this->getErrorCount(),
            'total_processed' => $this->getTotalProcessed(),
            'skipped' => $this->skipped,
            'errors' => $this->errors,
            'details' => $this->details,
        ];
    }
}
