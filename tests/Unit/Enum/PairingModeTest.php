<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\PairingMode;
use PHPUnit\Framework\TestCase;

final class PairingModeTest extends TestCase
{
    public function testAllCasesExist(): void
    {
        $this->assertCount(2, PairingMode::cases());
        $this->assertSame('random', PairingMode::RANDOM->value);
        $this->assertSame('registration_order', PairingMode::REGISTRATION_ORDER->value);
    }

    public function testGetLabel(): void
    {
        $this->assertSame('Aleatoire', PairingMode::RANDOM->getLabel());
        $this->assertSame('Ordre d\'inscription', PairingMode::REGISTRATION_ORDER->getLabel());
    }

    public function testGetDescription(): void
    {
        $this->assertStringContainsString('aleatoirement', PairingMode::RANDOM->getDescription());
        $this->assertStringContainsString('ordre d\'inscription', PairingMode::REGISTRATION_ORDER->getDescription());
    }
}
