<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\RoundStatus;
use PHPUnit\Framework\TestCase;

final class RoundStatusTest extends TestCase
{
    public function testAllCasesExist(): void
    {
        $this->assertCount(3, RoundStatus::cases());
        $this->assertSame('pending', RoundStatus::PENDING->value);
        $this->assertSame('ongoing', RoundStatus::ONGOING->value);
        $this->assertSame('completed', RoundStatus::COMPLETED->value);
    }

    public function testGetLabel(): void
    {
        // Labels are translation keys
        $this->assertSame('enum.round_status.pending', RoundStatus::PENDING->getLabel());
        $this->assertSame('enum.round_status.ongoing', RoundStatus::ONGOING->getLabel());
        $this->assertSame('enum.round_status.completed', RoundStatus::COMPLETED->getLabel());
    }

    public function testGetBadgeClasses(): void
    {
        $this->assertStringContainsString('gray', RoundStatus::PENDING->getBadgeClasses());
        $this->assertStringContainsString('blue', RoundStatus::ONGOING->getBadgeClasses());
        $this->assertStringContainsString('green', RoundStatus::COMPLETED->getBadgeClasses());
    }

    public function testCanTransitionFromPendingToOngoing(): void
    {
        $this->assertTrue(RoundStatus::PENDING->canTransitionTo(RoundStatus::ONGOING));
        $this->assertFalse(RoundStatus::PENDING->canTransitionTo(RoundStatus::COMPLETED));
        $this->assertFalse(RoundStatus::PENDING->canTransitionTo(RoundStatus::PENDING));
    }

    public function testCanTransitionFromOngoingToCompleted(): void
    {
        $this->assertTrue(RoundStatus::ONGOING->canTransitionTo(RoundStatus::COMPLETED));
        $this->assertFalse(RoundStatus::ONGOING->canTransitionTo(RoundStatus::PENDING));
        $this->assertFalse(RoundStatus::ONGOING->canTransitionTo(RoundStatus::ONGOING));
    }

    public function testCannotTransitionFromCompleted(): void
    {
        $this->assertFalse(RoundStatus::COMPLETED->canTransitionTo(RoundStatus::PENDING));
        $this->assertFalse(RoundStatus::COMPLETED->canTransitionTo(RoundStatus::ONGOING));
        $this->assertFalse(RoundStatus::COMPLETED->canTransitionTo(RoundStatus::COMPLETED));
    }
}
