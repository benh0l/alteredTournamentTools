<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Enum\DecklistTransparency;
use App\Enum\MatchFormat;
use App\Enum\TournamentFormat;
use App\Enum\TournamentStatus;
use App\Enum\TournamentStructure;
use App\Enum\TournamentVisibility;
use App\Service\SwissRoundsCalculator;
use App\Service\TournamentWizardService;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Service\TournamentWizardService
 */
final class TournamentWizardServiceTest extends TestCase
{
    private TournamentWizardService $service;

    protected function setUp(): void
    {
        $calculator = new SwissRoundsCalculator();
        $this->service = new TournamentWizardService($calculator);
    }

    public function testBuildTournamentFromCompleteWizardData(): void
    {
        $wizardData = $this->createCompleteWizardData();
        $user = $this->createMock(User::class);

        $tournament = $this->service->buildTournamentFromWizardData($wizardData, $user);

        $this->assertSame('Test Tournament', $tournament->getName());
        $this->assertSame(TournamentFormat::CONSTRUCTED_STANDARD, $tournament->getFormat());
        $this->assertSame(TournamentStructure::SWISS_ONLY, $tournament->getStructure());
        $this->assertSame(MatchFormat::BO3, $tournament->getSwissMatchFormat());
        $this->assertSame(16, $tournament->getExpectedPlayers());
        $this->assertSame(TournamentVisibility::PUBLIC, $tournament->getVisibility());
        $this->assertSame(DecklistTransparency::OPEN, $tournament->getDecklistTransparency());
        $this->assertSame($user, $tournament->getOrganizer());
        $this->assertSame(TournamentStatus::DRAFT, $tournament->getStatus());
    }

    public function testBuildTournamentAutoCalculatesSwissRounds(): void
    {
        $wizardData = $this->createCompleteWizardData();
        // Remove manually set swiss rounds
        unset($wizardData[6]['swissRounds']);

        $user = $this->createMock(User::class);
        $tournament = $this->service->buildTournamentFromWizardData($wizardData, $user);

        // With 16 players and BO3, should be 4 rounds (ceil(log2(16)))
        $this->assertSame(4, $tournament->getSwissRounds());
    }

    public function testBuildTournamentAutoCalculatesSwissRoundsWithBO1(): void
    {
        $wizardData = $this->createCompleteWizardData();
        $wizardData[5]['swissMatchFormat'] = MatchFormat::BO1;
        unset($wizardData[6]['swissRounds']);

        $user = $this->createMock(User::class);
        $tournament = $this->service->buildTournamentFromWizardData($wizardData, $user);

        // With 16 players and BO1, should be 5 rounds (4 + 1 for BO1 rule)
        $this->assertSame(5, $tournament->getSwissRounds());
    }

    public function testBuildTournamentUsesManualSwissRoundsIfProvided(): void
    {
        $wizardData = $this->createCompleteWizardData();
        $wizardData[6]['swissRounds'] = 6;

        $user = $this->createMock(User::class);
        $tournament = $this->service->buildTournamentFromWizardData($wizardData, $user);

        $this->assertSame(6, $tournament->getSwissRounds());
    }

    public function testBuildTournamentWithOptionalDetails(): void
    {
        $wizardData = $this->createCompleteWizardData();
        $wizardData[9] = [
            'description' => 'A great tournament',
            'entryFee' => '10 EUR',
            'prizes' => 'Booster Box for 1st place',
            'alteredGgLink' => 'https://altered.gg/event/123',
        ];

        $user = $this->createMock(User::class);
        $tournament = $this->service->buildTournamentFromWizardData($wizardData, $user);

        $this->assertSame('A great tournament', $tournament->getDescription());
        $this->assertSame('10 EUR', $tournament->getEntryFee());
        $this->assertSame('Booster Box for 1st place', $tournament->getPrizes());
        $this->assertSame('https://altered.gg/event/123', $tournament->getAlteredGgLink());
    }

    public function testBuildTournamentWithTopCut(): void
    {
        $wizardData = $this->createCompleteWizardData();
        $wizardData[4]['structure'] = TournamentStructure::MIXED;
        $wizardData[5]['eliminationMatchFormat'] = MatchFormat::BO3;
        $wizardData[6]['topCutSize'] = 8;

        $user = $this->createMock(User::class);
        $tournament = $this->service->buildTournamentFromWizardData($wizardData, $user);

        $this->assertSame(TournamentStructure::MIXED, $tournament->getStructure());
        $this->assertSame(MatchFormat::BO3, $tournament->getEliminationMatchFormat());
        $this->assertSame(8, $tournament->getTopCutSize());
    }

    public function testBuildSummaryFromWizardData(): void
    {
        $wizardData = $this->createCompleteWizardData();
        $summary = $this->service->buildSummaryFromWizardData($wizardData);

        $this->assertSame('Test Tournament', $summary['name']);
        $this->assertSame('Construit', $summary['format']);
        $this->assertSame('Rondes Suisses uniquement', $summary['structure']);
        $this->assertSame('Best of 3', $summary['swissMatchFormat']);
        $this->assertSame(16, $summary['expectedPlayers']);
        $this->assertSame('Public', $summary['visibility']);
        $this->assertSame('Decklists ouvertes', $summary['decklistTransparency']);
    }

    public function testBuildSummaryAutoSuggestsRounds(): void
    {
        $wizardData = $this->createCompleteWizardData();
        unset($wizardData[6]['swissRounds']);

        $summary = $this->service->buildSummaryFromWizardData($wizardData);

        $this->assertSame(4, $summary['swissRounds']);
        $this->assertTrue($summary['swissRoundsAutoSuggested']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function createCompleteWizardData(): array
    {
        return [
            1 => ['name' => 'Test Tournament'],
            2 => [
                'date' => new \DateTime('2026-02-15'),
                'time' => new \DateTime('14:00'),
                'location' => 'Game Store Paris',
            ],
            3 => ['format' => TournamentFormat::CONSTRUCTED_STANDARD],
            4 => ['structure' => TournamentStructure::SWISS_ONLY],
            5 => [
                'swissMatchFormat' => MatchFormat::BO3,
                'eliminationMatchFormat' => null,
            ],
            6 => [
                'expectedPlayers' => 16,
                'swissRounds' => 4,
                'topCutSize' => null,
            ],
            7 => ['visibility' => TournamentVisibility::PUBLIC],
            8 => ['decklistTransparency' => DecklistTransparency::OPEN],
            9 => [],
        ];
    }
}
