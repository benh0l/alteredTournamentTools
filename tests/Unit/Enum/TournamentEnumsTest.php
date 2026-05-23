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

        $this->assertCount(8, $cases);
        $this->assertSame('constructed_standard', TournamentFormat::CONSTRUCTED_STANDARD->value);
        $this->assertSame('constructed_singleton', TournamentFormat::CONSTRUCTED_SINGLETON->value);
        $this->assertSame('constructed_nuc', TournamentFormat::CONSTRUCTED_NUC->value);
        $this->assertSame('constructed_hero_oof', TournamentFormat::CONSTRUCTED_HERO_OUT_OF_FACTION->value);
        $this->assertSame('constructed_bifaction', TournamentFormat::CONSTRUCTED_BIFACTION->value);
        $this->assertSame('limited', TournamentFormat::LIMITED->value);
        $this->assertSame('limited_draft', TournamentFormat::LIMITED_DRAFT->value);
        $this->assertSame('fun_expedition_krakn', TournamentFormat::FUN_EXPEDITION_KRAKN->value);
    }

    public function testTournamentFormatGetLabel(): void
    {
        $this->assertSame('enum.tournament_format.constructed_standard', TournamentFormat::CONSTRUCTED_STANDARD->getLabel());
        $this->assertSame('enum.tournament_format.limited', TournamentFormat::LIMITED->getLabel());
    }

    public function testTournamentStructureHasExpectedCases(): void
    {
        $cases = TournamentStructure::cases();

        $this->assertCount(5, $cases);
        $this->assertSame('swiss_only', TournamentStructure::SWISS_ONLY->value);
        $this->assertSame('single_elimination', TournamentStructure::SINGLE_ELIMINATION->value);
        $this->assertSame('mixed', TournamentStructure::MIXED->value);
        $this->assertSame('group_stage_elimination', TournamentStructure::GROUP_STAGE_ELIMINATION->value);
        $this->assertSame('round_robin', TournamentStructure::ROUND_ROBIN->value);
    }

    public function testTournamentStructureGetLabel(): void
    {
        $this->assertSame('enum.tournament_structure.swiss_only', TournamentStructure::SWISS_ONLY->getLabel());
        $this->assertSame('enum.tournament_structure.single_elimination', TournamentStructure::SINGLE_ELIMINATION->getLabel());
        $this->assertSame('enum.tournament_structure.mixed', TournamentStructure::MIXED->getLabel());
        $this->assertSame('enum.tournament_structure.round_robin', TournamentStructure::ROUND_ROBIN->getLabel());
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
        $this->assertSame('enum.tournament_visibility.public', TournamentVisibility::PUBLIC->getLabel());
        $this->assertSame('enum.tournament_visibility.private', TournamentVisibility::PRIVATE->getLabel());
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
        $this->assertSame('enum.decklist_transparency.open', DecklistTransparency::OPEN->getLabel());
        $this->assertSame('enum.decklist_transparency.closed', DecklistTransparency::CLOSED->getLabel());
    }

    public function testTournamentStatusHasExpectedCases(): void
    {
        $cases = TournamentStatus::cases();

        $this->assertCount(6, $cases);
        $this->assertSame('draft', TournamentStatus::DRAFT->value);
        $this->assertSame('published', TournamentStatus::PUBLISHED->value);
        $this->assertSame('ongoing', TournamentStatus::ONGOING->value);
        $this->assertSame('abandoned', TournamentStatus::ABANDONED->value);
        $this->assertSame('completed', TournamentStatus::COMPLETED->value);
        $this->assertSame('cancelled', TournamentStatus::CANCELLED->value);
    }

    public function testTournamentStatusGetLabel(): void
    {
        $this->assertSame('enum.tournament_status.draft', TournamentStatus::DRAFT->getLabel());
        $this->assertSame('enum.tournament_status.published', TournamentStatus::PUBLISHED->getLabel());
        $this->assertSame('enum.tournament_status.ongoing', TournamentStatus::ONGOING->getLabel());
        $this->assertSame('enum.tournament_status.abandoned', TournamentStatus::ABANDONED->getLabel());
        $this->assertSame('enum.tournament_status.completed', TournamentStatus::COMPLETED->getLabel());
        $this->assertSame('enum.tournament_status.cancelled', TournamentStatus::CANCELLED->getLabel());
    }

    public function testTournamentStatusCanTransitionTo(): void
    {
        // DRAFT can transition to PUBLISHED or CANCELLED
        $this->assertTrue(TournamentStatus::DRAFT->canTransitionTo(TournamentStatus::PUBLISHED));
        $this->assertTrue(TournamentStatus::DRAFT->canTransitionTo(TournamentStatus::CANCELLED));
        $this->assertFalse(TournamentStatus::DRAFT->canTransitionTo(TournamentStatus::ONGOING));
        $this->assertFalse(TournamentStatus::DRAFT->canTransitionTo(TournamentStatus::COMPLETED));
        $this->assertFalse(TournamentStatus::DRAFT->canTransitionTo(TournamentStatus::ABANDONED));

        // PUBLISHED can transition to ONGOING or CANCELLED
        $this->assertTrue(TournamentStatus::PUBLISHED->canTransitionTo(TournamentStatus::ONGOING));
        $this->assertTrue(TournamentStatus::PUBLISHED->canTransitionTo(TournamentStatus::CANCELLED));
        $this->assertFalse(TournamentStatus::PUBLISHED->canTransitionTo(TournamentStatus::COMPLETED));
        $this->assertFalse(TournamentStatus::PUBLISHED->canTransitionTo(TournamentStatus::ABANDONED));

        // ONGOING can transition to COMPLETED, ABANDONED or CANCELLED
        $this->assertTrue(TournamentStatus::ONGOING->canTransitionTo(TournamentStatus::COMPLETED));
        $this->assertTrue(TournamentStatus::ONGOING->canTransitionTo(TournamentStatus::ABANDONED));
        $this->assertTrue(TournamentStatus::ONGOING->canTransitionTo(TournamentStatus::CANCELLED));

        // ABANDONED can transition to ONGOING, COMPLETED or CANCELLED
        $this->assertTrue(TournamentStatus::ABANDONED->canTransitionTo(TournamentStatus::ONGOING));
        $this->assertTrue(TournamentStatus::ABANDONED->canTransitionTo(TournamentStatus::COMPLETED));
        $this->assertTrue(TournamentStatus::ABANDONED->canTransitionTo(TournamentStatus::CANCELLED));
        $this->assertFalse(TournamentStatus::ABANDONED->canTransitionTo(TournamentStatus::PUBLISHED));
        $this->assertFalse(TournamentStatus::ABANDONED->canTransitionTo(TournamentStatus::DRAFT));

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
        $this->assertFalse(TournamentStatus::ABANDONED->isEditable());

        // acceptsRegistrations
        $this->assertFalse(TournamentStatus::DRAFT->acceptsRegistrations());
        $this->assertTrue(TournamentStatus::PUBLISHED->acceptsRegistrations());
        $this->assertFalse(TournamentStatus::ONGOING->acceptsRegistrations());
        $this->assertFalse(TournamentStatus::ABANDONED->acceptsRegistrations());

        // isActive (includes PUBLISHED, ONGOING, ABANDONED)
        $this->assertFalse(TournamentStatus::DRAFT->isActive());
        $this->assertTrue(TournamentStatus::PUBLISHED->isActive());
        $this->assertTrue(TournamentStatus::ONGOING->isActive());
        $this->assertTrue(TournamentStatus::ABANDONED->isActive());
        $this->assertFalse(TournamentStatus::COMPLETED->isActive());
        $this->assertFalse(TournamentStatus::CANCELLED->isActive());

        // isFinished
        $this->assertFalse(TournamentStatus::DRAFT->isFinished());
        $this->assertFalse(TournamentStatus::ONGOING->isFinished());
        $this->assertFalse(TournamentStatus::ABANDONED->isFinished());
        $this->assertTrue(TournamentStatus::COMPLETED->isFinished());
        $this->assertTrue(TournamentStatus::CANCELLED->isFinished());

        // isInProgress (ONGOING or ABANDONED - allows match operations)
        $this->assertFalse(TournamentStatus::DRAFT->isInProgress());
        $this->assertFalse(TournamentStatus::PUBLISHED->isInProgress());
        $this->assertTrue(TournamentStatus::ONGOING->isInProgress());
        $this->assertTrue(TournamentStatus::ABANDONED->isInProgress());
        $this->assertFalse(TournamentStatus::COMPLETED->isInProgress());
        $this->assertFalse(TournamentStatus::CANCELLED->isInProgress());

        // isDeletable
        $this->assertTrue(TournamentStatus::DRAFT->isDeletable());
        $this->assertTrue(TournamentStatus::PUBLISHED->isDeletable());
        $this->assertFalse(TournamentStatus::ONGOING->isDeletable());
        $this->assertFalse(TournamentStatus::ABANDONED->isDeletable());
        $this->assertFalse(TournamentStatus::COMPLETED->isDeletable());
        $this->assertFalse(TournamentStatus::CANCELLED->isDeletable());
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
        // Labels are translation keys
        $this->assertSame('enum.match_format.bo1', MatchFormat::BO1->getLabel());
        $this->assertSame('enum.match_format.bo3', MatchFormat::BO3->getLabel());
    }

    public function testEnumsCanBeCreatedFromValue(): void
    {
        $this->assertSame(TournamentFormat::CONSTRUCTED_STANDARD, TournamentFormat::from('constructed_standard'));
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
