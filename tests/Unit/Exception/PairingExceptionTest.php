<?php

declare(strict_types=1);

namespace App\Tests\Unit\Exception;

use App\Exception\PairingException;
use PHPUnit\Framework\TestCase;

final class PairingExceptionTest extends TestCase
{
    public function testDefaultMessage(): void
    {
        $exception = new PairingException();

        $this->assertStringContainsString('Impossible de generer', $exception->getMessage());
    }

    public function testCustomMessage(): void
    {
        $exception = new PairingException('Custom pairing error');

        $this->assertSame('Custom pairing error', $exception->getMessage());
    }

    public function testNoValidOpponent(): void
    {
        $exception = PairingException::noValidOpponent('PlayerX');

        $this->assertStringContainsString('PlayerX', $exception->getMessage());
        $this->assertStringContainsString('adversaire valide', $exception->getMessage());
    }

    public function testAllPairsExhausted(): void
    {
        $exception = PairingException::allPairsExhausted();

        $this->assertStringContainsString('appariements possibles', $exception->getMessage());
    }

    public function testIsRuntimeException(): void
    {
        $exception = new PairingException();

        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }
}
