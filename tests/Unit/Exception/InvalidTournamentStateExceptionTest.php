<?php

declare(strict_types=1);

namespace App\Tests\Unit\Exception;

use App\Enum\TournamentStatus;
use App\Exception\InvalidTournamentStateException;
use PHPUnit\Framework\TestCase;

final class InvalidTournamentStateExceptionTest extends TestCase
{
    public function testExceptionMessageWithCurrentStatus(): void
    {
        $exception = new InvalidTournamentStateException(
            TournamentStatus::DRAFT,
            [TournamentStatus::PUBLISHED]
        );

        // Message now contains translation keys
        $this->assertStringContainsString('enum.tournament_status.draft', $exception->getMessage());
    }

    public function testExceptionMessageWithExpectedStatuses(): void
    {
        $exception = new InvalidTournamentStateException(
            TournamentStatus::DRAFT,
            [TournamentStatus::PUBLISHED]
        );

        $this->assertStringContainsString('enum.tournament_status.published', $exception->getMessage());
    }

    public function testExceptionMessageWithOperation(): void
    {
        $exception = new InvalidTournamentStateException(
            TournamentStatus::DRAFT,
            [TournamentStatus::PUBLISHED],
            'demarrer le tournoi'
        );

        $this->assertStringContainsString('Impossible de demarrer le tournoi', $exception->getMessage());
    }

    public function testGetCurrentStatus(): void
    {
        $exception = new InvalidTournamentStateException(
            TournamentStatus::ONGOING,
            [TournamentStatus::PUBLISHED]
        );

        $this->assertSame(TournamentStatus::ONGOING, $exception->getCurrentStatus());
    }

    public function testGetExpectedStatuses(): void
    {
        $exception = new InvalidTournamentStateException(
            TournamentStatus::DRAFT,
            [TournamentStatus::PUBLISHED, TournamentStatus::ONGOING]
        );

        $expected = $exception->getExpectedStatuses();
        $this->assertCount(2, $expected);
        $this->assertContains(TournamentStatus::PUBLISHED, $expected);
        $this->assertContains(TournamentStatus::ONGOING, $expected);
    }

    public function testExceptionWithMultipleExpectedStatuses(): void
    {
        $exception = new InvalidTournamentStateException(
            TournamentStatus::CANCELLED,
            [TournamentStatus::PUBLISHED, TournamentStatus::ONGOING]
        );

        $message = $exception->getMessage();
        $this->assertStringContainsString('enum.tournament_status.published', $message);
        $this->assertStringContainsString('ou', $message);
        $this->assertStringContainsString('enum.tournament_status.ongoing', $message);
    }

    public function testExceptionWithEmptyExpectedStatuses(): void
    {
        $exception = new InvalidTournamentStateException(
            TournamentStatus::COMPLETED,
            []
        );

        $this->assertStringContainsString('enum.tournament_status.completed', $exception->getMessage());
    }

    public function testIsRuntimeException(): void
    {
        $exception = new InvalidTournamentStateException(
            TournamentStatus::DRAFT,
            []
        );

        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }
}
