<?php

declare(strict_types=1);

namespace App\Tests\Unit\Exception;

use App\Exception\InsufficientPlayersException;
use PHPUnit\Framework\TestCase;

final class InsufficientPlayersExceptionTest extends TestCase
{
    public function testExceptionMessage(): void
    {
        $exception = new InsufficientPlayersException(1, 2);

        $this->assertStringContainsString('2 joueurs', $exception->getMessage());
        $this->assertStringContainsString('1 joueur', $exception->getMessage());
    }

    public function testGetPlayerCount(): void
    {
        $exception = new InsufficientPlayersException(5, 10);

        $this->assertSame(5, $exception->getPlayerCount());
    }

    public function testGetMinimumRequired(): void
    {
        $exception = new InsufficientPlayersException(5, 10);

        $this->assertSame(10, $exception->getMinimumRequired());
    }

    public function testDefaultMinimumRequired(): void
    {
        $exception = new InsufficientPlayersException(1);

        $this->assertSame(2, $exception->getMinimumRequired());
    }

    public function testIsRuntimeException(): void
    {
        $exception = new InsufficientPlayersException(1);

        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }
}
