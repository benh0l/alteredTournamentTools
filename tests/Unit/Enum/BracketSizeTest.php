<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\BracketSize;
use PHPUnit\Framework\TestCase;

final class BracketSizeTest extends TestCase
{
    public function testAllCasesExist(): void
    {
        $this->assertCount(4, BracketSize::cases());
        $this->assertSame(4, BracketSize::TOP_4->value);
        $this->assertSame(8, BracketSize::TOP_8->value);
        $this->assertSame(16, BracketSize::TOP_16->value);
        $this->assertSame(32, BracketSize::TOP_32->value);
    }

    public function testGetLabel(): void
    {
        $this->assertSame('Top 4', BracketSize::TOP_4->getLabel());
        $this->assertSame('Top 8', BracketSize::TOP_8->getLabel());
        $this->assertSame('Top 16', BracketSize::TOP_16->getLabel());
        $this->assertSame('Top 32', BracketSize::TOP_32->getLabel());
    }

    public function testGetRoundsCount(): void
    {
        // log2(4) = 2 (semis + final)
        $this->assertSame(2, BracketSize::TOP_4->getRoundsCount());
        // log2(8) = 3 (quarters + semis + final)
        $this->assertSame(3, BracketSize::TOP_8->getRoundsCount());
        // log2(16) = 4
        $this->assertSame(4, BracketSize::TOP_16->getRoundsCount());
        // log2(32) = 5
        $this->assertSame(5, BracketSize::TOP_32->getRoundsCount());
    }

    public function testGetRoundNameForTop8(): void
    {
        $size = BracketSize::TOP_8;

        // Round 1 of 3 = Quarters
        $this->assertSame('Quarts de finale', $size->getRoundName(1));
        // Round 2 of 3 = Semis
        $this->assertSame('Demi-finales', $size->getRoundName(2));
        // Round 3 of 3 = Final
        $this->assertSame('Finale', $size->getRoundName(3));
    }

    public function testGetRoundNameForTop4(): void
    {
        $size = BracketSize::TOP_4;

        // Round 1 of 2 = Semis
        $this->assertSame('Demi-finales', $size->getRoundName(1));
        // Round 2 of 2 = Final
        $this->assertSame('Finale', $size->getRoundName(2));
    }

    public function testGetRoundNameForTop16(): void
    {
        $size = BracketSize::TOP_16;

        // Round 1 of 4 = Round of 16
        $this->assertSame('Huitiemes de finale', $size->getRoundName(1));
        // Round 2 of 4 = Quarters
        $this->assertSame('Quarts de finale', $size->getRoundName(2));
        // Round 3 of 4 = Semis
        $this->assertSame('Demi-finales', $size->getRoundName(3));
        // Round 4 of 4 = Final
        $this->assertSame('Finale', $size->getRoundName(4));
    }

    public function testGetChoices(): void
    {
        $choices = BracketSize::getChoices();

        $this->assertCount(4, $choices);
        $this->assertSame(4, $choices['Top 4']);
        $this->assertSame(8, $choices['Top 8']);
        $this->assertSame(16, $choices['Top 16']);
        $this->assertSame(32, $choices['Top 32']);
    }

    public function testGetMinimumPlayers(): void
    {
        // Top 4: need at least 3 players (4/2 + 1)
        $this->assertSame(3, BracketSize::TOP_4->getMinimumPlayers());
        // Top 8: need at least 5 players (8/2 + 1)
        $this->assertSame(5, BracketSize::TOP_8->getMinimumPlayers());
        // Top 16: need at least 9 players
        $this->assertSame(9, BracketSize::TOP_16->getMinimumPlayers());
        // Top 32: need at least 17 players
        $this->assertSame(17, BracketSize::TOP_32->getMinimumPlayers());
    }
}
