<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\DecklistTransparency;
use App\Enum\MatchFormat;
use App\Enum\TournamentFormat;
use App\Enum\TournamentStatus;
use App\Enum\TournamentStructure;
use App\Enum\TournamentVisibility;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Tournament-related enums.
 */
final class TournamentEnumsTest extends TestCase
{
    public function testTournamentFormatHasExpectedCases(): void
    {
        $cases = TournamentFormat::cases();

        $this->assertCount(2, $cases);
        $this->assertSame('constructed', TournamentFormat::CONSTRUCTED->value);
        $this->assertSame('limited', TournamentFormat::LIMITED->value);
    }

    public function testTournamentFormatGetLabel(): void
    {
        $this->assertSame('Construit', TournamentFormat::CONSTRUCTED->getLabel());
        $this->assertSame('Limite', TournamentFormat::LIMITED->getLabel());
    }

    public function testTournamentStructureHasExpectedCases(): void
    {
        $cases = TournamentStructure::cases();

        $this->assertCount(3, $cases);
        $this->assertSame('swiss_only', TournamentStructure::SWISS_ONLY->value);
        $this->assertSame('single_elimination', TournamentStructure::SINGLE_ELIMINATION->value);
        $this->assertSame('mixed', TournamentStructure::MIXED->value);
    }

    public function testTournamentStructureGetLabel(): void
    {
        $this->assertSame('Rondes Suisses uniquement', TournamentStructure::SWISS_ONLY->getLabel());
        $this->assertSame('Elimination directe', TournamentStructure::SINGLE_ELIMINATION->getLabel());
        $this->assertSame('Suisses + Top Cut', TournamentStructure::MIXED->getLabel());
    }

    public function testTournamentVisibilityHasExpectedCases(): void
    {
        $cases = TournamentVisibility::cases();

        $this->assertCount(2, $cases);
        $this->assertSame('public', TournamentVisibility::PUBLIC->value);
        $this->assertSame('private', TournamentVisibility::PRIVATE->value);
    }

    public function testTournamentVisibilityGetLabel(): void
    {
        $this->assertSame('Public', TournamentVisibility::PUBLIC->getLabel());
        $this->assertSame('Prive', TournamentVisibility::PRIVATE->getLabel());
    }

    public function testDecklistTransparencyHasExpectedCases(): void
    {
        $cases = DecklistTransparency::cases();

        $this->assertCount(2, $cases);
        $this->assertSame('open', DecklistTransparency::OPEN->value);
        $this->assertSame('closed', DecklistTransparency::CLOSED->value);
    }

    public function testDecklistTransparencyGetLabel(): void
    {
        $this->assertSame('Decklists ouvertes', DecklistTransparency::OPEN->getLabel());
        $this->assertSame('Decklists fermees', DecklistTransparency::CLOSED->getLabel());
    }

    public function testTournamentStatusHasExpectedCases(): void
    {
        $cases = TournamentStatus::cases();

        $this->assertCount(5, $cases);
        $this->assertSame('draft', TournamentStatus::DRAFT->value);
        $this->assertSame('published', TournamentStatus::PUBLISHED->value);
        $this->assertSame('ongoing', TournamentStatus::ONGOING->value);
        $this->assertSame('completed', TournamentStatus::COMPLETED->value);
        $this->assertSame('cancelled', TournamentStatus::CANCELLED->value);
    }

    public function testTournamentStatusGetLabel(): void
    {
        $this->assertSame('Brouillon', TournamentStatus::DRAFT->getLabel());
        $this->assertSame('Publie', TournamentStatus::PUBLISHED->getLabel());
        $this->assertSame('En cours', TournamentStatus::ONGOING->getLabel());
        $this->assertSame('Termine', TournamentStatus::COMPLETED->getLabel());
        $this->assertSame('Annule', TournamentStatus::CANCELLED->getLabel());
    }

    public function testTournamentStatusCanTransitionTo(): void
    {
        // DRAFT can transition to PUBLISHED or CANCELLED
        $this->assertTrue(TournamentStatus::DRAFT->canTransitionTo(TournamentStatus::PUBLISHED));
        $this->assertTrue(TournamentStatus::DRAFT->canTransitionTo(TournamentStatus::CANCELLED));
        $this->assertFalse(TournamentStatus::DRAFT->canTransitionTo(TournamentStatus::ONGOING));
        $this->assertFalse(TournamentStatus::DRAFT->canTransitionTo(TournamentStatus::COMPLETED));

        // PUBLISHED can transition to ONGOING or CANCELLED
        $this->assertTrue(TournamentStatus::PUBLISHED->canTransitionTo(TournamentStatus::ONGOING));
        $this->assertTrue(TournamentStatus::PUBLISHED->canTransitionTo(TournamentStatus::CANCELLED));
        $this->assertFalse(TournamentStatus::PUBLISHED->canTransitionTo(TournamentStatus::COMPLETED));

        // ONGOING can transition to COMPLETED or CANCELLED
        $this->assertTrue(TournamentStatus::ONGOING->canTransitionTo(TournamentStatus::COMPLETED));
        $this->assertTrue(TournamentStatus::ONGOING->canTransitionTo(TournamentStatus::CANCELLED));

        // COMPLETED and CANCELLED are terminal states
        $this->assertFalse(TournamentStatus::COMPLETED->canTransitionTo(TournamentStatus::CANCELLED));
        $this->assertFalse(TournamentStatus::CANCELLED->canTransitionTo(TournamentStatus::COMPLETED));
    }

    public function testTournamentStatusHelperMethods(): void
    {
        // isEditable
        $this->assertTrue(TournamentStatus::DRAFT->isEditable());
        $this->assertFalse(TournamentStatus::PUBLISHED->isEditable());
        $this->assertFalse(TournamentStatus::ONGOING->isEditable());

        // acceptsRegistrations
        $this->assertFalse(TournamentStatus::DRAFT->acceptsRegistrations());
        $this->assertTrue(TournamentStatus::PUBLISHED->acceptsRegistrations());
        $this->assertFalse(TournamentStatus::ONGOING->acceptsRegistrations());

        // isActive
        $this->assertFalse(TournamentStatus::DRAFT->isActive());
        $this->assertTrue(TournamentStatus::PUBLISHED->isActive());
        $this->assertTrue(TournamentStatus::ONGOING->isActive());
        $this->assertFalse(TournamentStatus::COMPLETED->isActive());

        // isFinished
        $this->assertFalse(TournamentStatus::DRAFT->isFinished());
        $this->assertFalse(TournamentStatus::ONGOING->isFinished());
        $this->assertTrue(TournamentStatus::COMPLETED->isFinished());
        $this->assertTrue(TournamentStatus::CANCELLED->isFinished());
    }

    public function testMatchFormatHasExpectedCases(): void
    {
        $cases = MatchFormat::cases();

        $this->assertCount(2, $cases);
        $this->assertSame('bo1', MatchFormat::BO1->value);
        $this->assertSame('bo3', MatchFormat::BO3->value);
    }

    public function testMatchFormatGetLabel(): void
    {
        $this->assertSame('Best of 1', MatchFormat::BO1->getLabel());
        $this->assertSame('Best of 3', MatchFormat::BO3->getLabel());
    }

    public function testEnumsCanBeCreatedFromValue(): void
    {
        $this->assertSame(TournamentFormat::CONSTRUCTED, TournamentFormat::from('constructed'));
        $this->assertSame(TournamentStructure::MIXED, TournamentStructure::from('mixed'));
        $this->assertSame(TournamentVisibility::PUBLIC, TournamentVisibility::from('public'));
        $this->assertSame(DecklistTransparency::OPEN, DecklistTransparency::from('open'));
        $this->assertSame(TournamentStatus::DRAFT, TournamentStatus::from('draft'));
        $this->assertSame(MatchFormat::BO3, MatchFormat::from('bo3'));
    }

    public function testEnumsTryFromReturnsNullForInvalidValue(): void
    {
        $this->assertNull(TournamentFormat::tryFrom('invalid'));
        $this->assertNull(TournamentStructure::tryFrom('invalid'));
        $this->assertNull(TournamentVisibility::tryFrom('invalid'));
        $this->assertNull(DecklistTransparency::tryFrom('invalid'));
        $this->assertNull(TournamentStatus::tryFrom('invalid'));
        $this->assertNull(MatchFormat::tryFrom('invalid'));
    }
}
