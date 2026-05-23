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
        // Labels are translation keys
        $this->assertSame('enum.pairing_mode.random', PairingMode::RANDOM->getLabel());
        $this->assertSame('enum.pairing_mode.registration_order', PairingMode::REGISTRATION_ORDER->getLabel());
    }

    public function testGetDescription(): void
    {
        // Descriptions are translation keys
        $this->assertSame('enum.pairing_mode_description.random', PairingMode::RANDOM->getDescription());
        $this->assertSame('enum.pairing_mode_description.registration_order', PairingMode::REGISTRATION_ORDER->getDescription());
    }
}
