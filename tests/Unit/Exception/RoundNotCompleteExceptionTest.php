<?php

declare(strict_types=1);

namespace App\Tests\Unit\Exception;

use App\Exception\RoundNotCompleteException;
use PHPUnit\Framework\TestCase;

final class RoundNotCompleteExceptionTest extends TestCase
{
    public function testExceptionMessage(): void
    {
        $exception = new RoundNotCompleteException(2, 5, 8);

        $this->assertStringContainsString('ronde 2', $exception->getMessage());
        $this->assertStringContainsString('3 match', $exception->getMessage());
        $this->assertStringContainsString('8', $exception->getMessage());
    }

    public function testGetRoundNumber(): void
    {
        $exception = new RoundNotCompleteException(3, 4, 6);

        $this->assertSame(3, $exception->getRoundNumber());
    }

    public function testGetCompletedMatches(): void
    {
        $exception = new RoundNotCompleteException(2, 5, 8);

        $this->assertSame(5, $exception->getCompletedMatches());
    }

    public function testGetTotalMatches(): void
    {
        $exception = new RoundNotCompleteException(2, 5, 8);

        $this->assertSame(8, $exception->getTotalMatches());
    }

    public function testGetRemainingMatches(): void
    {
        $exception = new RoundNotCompleteException(2, 5, 8);

        $this->assertSame(3, $exception->getRemainingMatches());
    }

    public function testIsRuntimeException(): void
    {
        $exception = new RoundNotCompleteException(1, 0, 4);

        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }
}
