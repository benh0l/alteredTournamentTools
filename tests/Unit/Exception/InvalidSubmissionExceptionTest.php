<?php

declare(strict_types=1);

namespace App\Tests\Unit\Exception;

use App\Exception\InvalidSubmissionException;
use PHPUnit\Framework\TestCase;

final class InvalidSubmissionExceptionTest extends TestCase
{
    public function testMatchAlreadyCompleted(): void
    {
        $exception = InvalidSubmissionException::matchAlreadyCompleted();

        $this->assertInstanceOf(InvalidSubmissionException::class, $exception);
        $this->assertStringContainsString('deja termine', $exception->getMessage());
    }

    public function testCannotSubmitForBye(): void
    {
        $exception = InvalidSubmissionException::cannotSubmitForBye();

        $this->assertInstanceOf(InvalidSubmissionException::class, $exception);
        $this->assertStringContainsString('BYE', $exception->getMessage());
    }

    public function testNotAParticipant(): void
    {
        $exception = InvalidSubmissionException::notAParticipant();

        $this->assertInstanceOf(InvalidSubmissionException::class, $exception);
        $this->assertStringContainsString('participant', $exception->getMessage());
    }

    public function testAlreadySubmitted(): void
    {
        $exception = InvalidSubmissionException::alreadySubmitted();

        $this->assertInstanceOf(InvalidSubmissionException::class, $exception);
        $this->assertStringContainsString('deja soumis', $exception->getMessage());
    }

    public function testInvalidScoreWithoutReason(): void
    {
        $exception = InvalidSubmissionException::invalidScore();

        $this->assertInstanceOf(InvalidSubmissionException::class, $exception);
        $this->assertStringContainsString('Score invalide', $exception->getMessage());
    }

    public function testInvalidScoreWithReason(): void
    {
        $exception = InvalidSubmissionException::invalidScore('Le score BO3 doit etre 2-0 ou 2-1');

        $this->assertInstanceOf(InvalidSubmissionException::class, $exception);
        $this->assertStringContainsString('Score invalide', $exception->getMessage());
        $this->assertStringContainsString('BO3', $exception->getMessage());
    }

    public function testInvalidWinner(): void
    {
        $exception = InvalidSubmissionException::invalidWinner();

        $this->assertInstanceOf(InvalidSubmissionException::class, $exception);
        $this->assertStringContainsString('vainqueur', $exception->getMessage());
    }

    public function testExceptionIsDomainException(): void
    {
        $exception = InvalidSubmissionException::matchAlreadyCompleted();

        $this->assertInstanceOf(\DomainException::class, $exception);
    }
}
