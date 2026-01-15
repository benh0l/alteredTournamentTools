<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\MatchStatus;
use PHPUnit\Framework\TestCase;

final class MatchStatusTest extends TestCase
{
    public function testAllCasesExist(): void
    {
        $this->assertCount(4, MatchStatus::cases());
        $this->assertSame('pending', MatchStatus::PENDING->value);
        $this->assertSame('ongoing', MatchStatus::ONGOING->value);
        $this->assertSame('completed', MatchStatus::COMPLETED->value);
        $this->assertSame('dispute', MatchStatus::DISPUTE->value);
    }

    public function testGetLabel(): void
    {
        $this->assertSame('En attente', MatchStatus::PENDING->getLabel());
        $this->assertSame('En cours', MatchStatus::ONGOING->getLabel());
        $this->assertSame('Termine', MatchStatus::COMPLETED->getLabel());
        $this->assertSame('Litige', MatchStatus::DISPUTE->getLabel());
    }

    public function testGetBadgeClasses(): void
    {
        $this->assertStringContainsString('gray', MatchStatus::PENDING->getBadgeClasses());
        $this->assertStringContainsString('blue', MatchStatus::ONGOING->getBadgeClasses());
        $this->assertStringContainsString('green', MatchStatus::COMPLETED->getBadgeClasses());
        $this->assertStringContainsString('red', MatchStatus::DISPUTE->getBadgeClasses());
    }

    public function testIsFinished(): void
    {
        $this->assertFalse(MatchStatus::PENDING->isFinished());
        $this->assertFalse(MatchStatus::ONGOING->isFinished());
        $this->assertTrue(MatchStatus::COMPLETED->isFinished());
        $this->assertFalse(MatchStatus::DISPUTE->isFinished());
    }

    public function testRequiresResolution(): void
    {
        $this->assertFalse(MatchStatus::PENDING->requiresResolution());
        $this->assertFalse(MatchStatus::ONGOING->requiresResolution());
        $this->assertFalse(MatchStatus::COMPLETED->requiresResolution());
        $this->assertTrue(MatchStatus::DISPUTE->requiresResolution());
    }
}
