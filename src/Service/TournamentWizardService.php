<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\DecklistTransparency;
use App\Enum\GroupFormationMethod;
use App\Enum\MatchFormat;
use App\Enum\TournamentFormat;
use App\Enum\TournamentStructure;
use App\Enum\TournamentVisibility;

/**
 * Service for building Tournament entities from wizard data.
 */
final class TournamentWizardService
{
    public function __construct(
        private readonly SwissRoundsCalculator $swissRoundsCalculator
    ) {
    }

    /**
     * Build a Tournament entity from wizard step data.
     *
     * @param array<int, array<string, mixed>> $wizardData
     */
    public function buildTournamentFromWizardData(array $wizardData, User $organizer): Tournament
    {
        $tournament = new Tournament();

        // Step 1: Name
        if (isset($wizardData[1]['name'])) {
            $tournament->setName($wizardData[1]['name']);
        }

        // Step 2: Date and location
        if (isset($wizardData[2])) {
            if (isset($wizardData[2]['date'])) {
                $date = $wizardData[2]['date'];
                if ($date instanceof \DateTimeInterface) {
                    $tournament->setDate(\DateTimeImmutable::createFromInterface($date));
                }
            }
            if (isset($wizardData[2]['time'])) {
                $time = $wizardData[2]['time'];
                if ($time instanceof \DateTimeInterface) {
                    $tournament->setTime(\DateTimeImmutable::createFromInterface($time));
                }
            }
            if (isset($wizardData[2]['location'])) {
                $tournament->setLocation($wizardData[2]['location']);
            }
            // Geocoding coordinates from address autocomplete
            if (isset($wizardData[2]['latitude']) && $wizardData[2]['latitude'] !== '') {
                $tournament->setLatitude((float) $wizardData[2]['latitude']);
            }
            if (isset($wizardData[2]['longitude']) && $wizardData[2]['longitude'] !== '') {
                $tournament->setLongitude((float) $wizardData[2]['longitude']);
            }
        }

        // Step 3: Game format
        if (isset($wizardData[3]['format'])) {
            $format = $wizardData[3]['format'];
            if ($format instanceof TournamentFormat) {
                $tournament->setFormat($format);
            }
        }

        // Step 4: Tournament structure
        if (isset($wizardData[4]['structure'])) {
            $structure = $wizardData[4]['structure'];
            if ($structure instanceof TournamentStructure) {
                $tournament->setStructure($structure);
            }
        }

        // Step 5: Match format
        if (isset($wizardData[5])) {
            if (isset($wizardData[5]['swissMatchFormat'])) {
                $swissFormat = $wizardData[5]['swissMatchFormat'];
                if ($swissFormat instanceof MatchFormat) {
                    $tournament->setSwissMatchFormat($swissFormat);
                }
            }
            if (isset($wizardData[5]['eliminationMatchFormat'])) {
                $elimFormat = $wizardData[5]['eliminationMatchFormat'];
                if ($elimFormat instanceof MatchFormat) {
                    $tournament->setEliminationMatchFormat($elimFormat);
                }
            }
        }

        // Step 6: Expected players and rounds
        if (isset($wizardData[6])) {
            $expectedPlayers = $wizardData[6]['expectedPlayers'] ?? null;
            $swissRounds = $wizardData[6]['swissRounds'] ?? null;
            $topCutSize = $wizardData[6]['topCutSize'] ?? null;

            if ($expectedPlayers !== null) {
                $tournament->setExpectedPlayers((int) $expectedPlayers);
            }

            if ($topCutSize !== null) {
                $tournament->setTopCutSize((int) $topCutSize);
            }

            // Auto-calculate Swiss rounds if not provided (only for Swiss-based structures)
            if ($tournament->hasSwiss()) {
                if ($swissRounds !== null) {
                    $tournament->setSwissRounds((int) $swissRounds);
                } elseif ($expectedPlayers !== null && $tournament->getSwissMatchFormat() !== null) {
                    $suggestedRounds = $this->swissRoundsCalculator->calculateWithBO1Rule(
                        (int) $expectedPlayers,
                        $tournament->getSwissMatchFormat()
                    );
                    $tournament->setSwissRounds($suggestedRounds);
                }
            }

            // Group stage configuration (for GROUP_STAGE_ELIMINATION)
            if ($tournament->hasGroupStage()) {
                $groupCount = $wizardData[6]['groupCount'] ?? null;
                $playersPerGroup = $wizardData[6]['playersPerGroup'] ?? null;
                $qualifiersPerGroup = $wizardData[6]['qualifiersPerGroup'] ?? null;
                $groupFormationMethod = $wizardData[6]['groupFormationMethod'] ?? null;

                if ($groupCount !== null) {
                    $tournament->setGroupCount((int) $groupCount);
                }
                if ($playersPerGroup !== null) {
                    $tournament->setPlayersPerGroup((int) $playersPerGroup);
                }
                if ($qualifiersPerGroup !== null) {
                    $tournament->setQualifiersPerGroup((int) $qualifiersPerGroup);
                }
                if ($groupFormationMethod instanceof GroupFormationMethod) {
                    $tournament->setGroupFormationMethod($groupFormationMethod);
                }
            }
        }

        // Step 7: Visibility
        if (isset($wizardData[7]['visibility'])) {
            $visibility = $wizardData[7]['visibility'];
            if ($visibility instanceof TournamentVisibility) {
                $tournament->setVisibility($visibility);
            }
        }

        // Step 8: Decklist transparency
        if (isset($wizardData[8]['decklistTransparency'])) {
            $transparency = $wizardData[8]['decklistTransparency'];
            if ($transparency instanceof DecklistTransparency) {
                $tournament->setDecklistTransparency($transparency);
            }
        }

        // Step 9: Additional details
        if (isset($wizardData[9])) {
            if (isset($wizardData[9]['description'])) {
                $tournament->setDescription($wizardData[9]['description']);
            }
            if (isset($wizardData[9]['entryFee'])) {
                $tournament->setEntryFee($wizardData[9]['entryFee']);
            }
            if (isset($wizardData[9]['prizes'])) {
                $tournament->setPrizes($wizardData[9]['prizes']);
            }
            if (isset($wizardData[9]['alteredGgLink'])) {
                $tournament->setAlteredGgLink($wizardData[9]['alteredGgLink']);
            }
            if (isset($wizardData[9]['isTumult'])) {
                $tournament->setIsTumult((bool) $wizardData[9]['isTumult']);
            }
            if (isset($wizardData[9]['isSeasonFinalsQualifier'])) {
                $tournament->setIsSeasonFinalsQualifier((bool) $wizardData[9]['isSeasonFinalsQualifier']);
            }
            if (isset($wizardData[9]['checkInEnabled'])) {
                $tournament->setCheckInEnabled((bool) $wizardData[9]['checkInEnabled']);
            }
        }

        $tournament->setOrganizer($organizer);

        return $tournament;
    }

    /**
     * Build a summary array for display in step 10.
     *
     * @param array<int, array<string, mixed>> $wizardData
     *
     * @return array<string, mixed>
     */
    public function buildSummaryFromWizardData(array $wizardData): array
    {
        $summary = [];

        // Step 1: Name
        $summary['name'] = $wizardData[1]['name'] ?? '';

        // Step 2: Date and location
        $summary['date'] = $wizardData[2]['date'] ?? null;
        $summary['time'] = $wizardData[2]['time'] ?? null;
        $summary['location'] = $wizardData[2]['location'] ?? '';

        // Step 3: Format
        $format = $wizardData[3]['format'] ?? null;
        $summary['format'] = $format instanceof TournamentFormat ? $format->getLabel() : '';

        // Step 4: Structure
        $structure = $wizardData[4]['structure'] ?? null;
        $summary['structure'] = $structure instanceof TournamentStructure ? $structure->getLabel() : '';

        // Step 5: Match format
        $swissFormat = $wizardData[5]['swissMatchFormat'] ?? null;
        $elimFormat = $wizardData[5]['eliminationMatchFormat'] ?? null;
        $summary['swissMatchFormat'] = $swissFormat instanceof MatchFormat ? $swissFormat->getLabel() : '';
        $summary['eliminationMatchFormat'] = $elimFormat instanceof MatchFormat ? $elimFormat->getLabel() : '';

        // Step 6: Players
        $summary['expectedPlayers'] = $wizardData[6]['expectedPlayers'] ?? null;
        $summary['swissRounds'] = $wizardData[6]['swissRounds'] ?? null;
        $summary['topCutSize'] = $wizardData[6]['topCutSize'] ?? null;

        // Group stage configuration (for GROUP_STAGE_ELIMINATION)
        $summary['groupCount'] = $wizardData[6]['groupCount'] ?? null;
        $summary['playersPerGroup'] = $wizardData[6]['playersPerGroup'] ?? null;
        $summary['qualifiersPerGroup'] = $wizardData[6]['qualifiersPerGroup'] ?? null;
        $groupFormationMethod = $wizardData[6]['groupFormationMethod'] ?? null;
        $summary['groupFormationMethod'] = $groupFormationMethod instanceof GroupFormationMethod
            ? $groupFormationMethod->getLabel()
            : '';

        // Calculate total players and qualifiers for group stage
        if ($summary['groupCount'] !== null && $summary['playersPerGroup'] !== null) {
            $summary['totalGroupPlayers'] = $summary['groupCount'] * $summary['playersPerGroup'];
        }
        if ($summary['groupCount'] !== null && $summary['qualifiersPerGroup'] !== null) {
            $summary['totalQualifiers'] = $summary['groupCount'] * $summary['qualifiersPerGroup'];
        }

        // Auto-suggest rounds if not set (only for Swiss structures)
        $hasSwiss = $structure instanceof TournamentStructure && $structure->hasSwiss();
        if ($hasSwiss && $summary['swissRounds'] === null && $summary['expectedPlayers'] !== null && $swissFormat !== null) {
            $summary['swissRounds'] = $this->swissRoundsCalculator->calculateWithBO1Rule(
                (int) $summary['expectedPlayers'],
                $swissFormat
            );
            $summary['swissRoundsAutoSuggested'] = true;
        }

        // Calculate rounds for round-robin (championship) format
        $hasRoundRobin = $structure instanceof TournamentStructure && $structure->hasRoundRobin();
        if ($hasRoundRobin && $summary['expectedPlayers'] !== null) {
            $summary['roundRobinRounds'] = (int) $summary['expectedPlayers'] - 1;
        }

        // Step 7: Visibility
        $visibility = $wizardData[7]['visibility'] ?? null;
        $summary['visibility'] = $visibility instanceof TournamentVisibility ? $visibility->getLabel() : '';

        // Step 8: Decklist transparency
        $transparency = $wizardData[8]['decklistTransparency'] ?? null;
        $summary['decklistTransparency'] = $transparency instanceof DecklistTransparency ? $transparency->getLabel() : '';

        // Step 9: Details
        $summary['description'] = $wizardData[9]['description'] ?? '';
        $summary['entryFee'] = $wizardData[9]['entryFee'] ?? '';
        $summary['prizes'] = $wizardData[9]['prizes'] ?? '';
        $summary['alteredGgLink'] = $wizardData[9]['alteredGgLink'] ?? '';

        return $summary;
    }
}
