<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Registration;
use App\Entity\Round;
use App\Entity\Tournament;
use App\Entity\TournamentMatch;
use App\Enum\PairingMode;
use App\Enum\TournamentStatus;
use App\Exception\InsufficientPlayersException;
use App\Exception\InvalidTournamentStateException;
use App\Exception\PairingException;
use App\Exception\RoundNotCompleteException;
use App\Repository\TournamentMatchRepository;
use App\Service\Pairing\PlayerStandings;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service responsible for generating pairings for tournament rounds.
 *
 * This service implements the Swiss pairing algorithm as used in TCG tournaments:
 * - Round 1: Random or registration order pairing
 * - Subsequent rounds: Pair by match points, avoiding rematches
 *
 * Supports BYE handling for odd player counts.
 *
 * @see https://en.wikipedia.org/wiki/Swiss-system_tournament
 */
class PairingService
{
    private const MINIMUM_PLAYERS = 2;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private TournamentMatchRepository $matchRepository,
    ) {
    }

    /**
     * Generate pairings for Round 1 of a tournament.
     *
     * This method:
     * 1. Validates the tournament can start (PUBLISHED status, enough players)
     * 2. Creates a new Round entity with roundNumber=1
     * 3. Retrieves all registrations
     * 4. If odd number of players, assigns one player a BYE
     * 5. Pairs players according to the specified mode
     * 6. Creates Match entities for all pairings
     * 7. Updates tournament status to ONGOING
     *
     * @param Tournament  $tournament  The tournament to start
     * @param PairingMode $mode        How to pair players (random or by registration order)
     *
     * @return Round The created round with all matches
     *
     * @throws InsufficientPlayersException    If not enough players are registered
     * @throws InvalidTournamentStateException If tournament is not in PUBLISHED status
     */
    public function generateRound1Pairings(Tournament $tournament, PairingMode $mode = PairingMode::RANDOM): Round
    {
        $this->validateTournamentCanStart($tournament);

        $registrations = $tournament->getRegistrations()->toArray();
        $playerCount = count($registrations);

        if ($playerCount < self::MINIMUM_PLAYERS) {
            throw new InsufficientPlayersException($playerCount, self::MINIMUM_PLAYERS);
        }

        // Order registrations based on pairing mode
        $orderedRegistrations = $this->orderRegistrations($registrations, $mode);

        // Create Round 1
        $round = new Round();
        $round->setTournament($tournament);
        $round->setRoundNumber(1);
        $tournament->addRound($round);

        // Handle BYE if odd number of players
        $byePlayer = null;
        if (count($orderedRegistrations) % 2 !== 0) {
            $byePlayer = $this->selectByePlayer($orderedRegistrations);
            $orderedRegistrations = array_values(array_filter(
                $orderedRegistrations,
                fn (Registration $r): bool => $r !== $byePlayer
            ));
        }

        // Create matches
        $tableNumber = 1;
        for ($i = 0; $i < count($orderedRegistrations); $i += 2) {
            $match = $this->createMatch(
                $round,
                $orderedRegistrations[$i],
                $orderedRegistrations[$i + 1],
                $tableNumber
            );
            $round->addMatch($match);
            $tableNumber++;
        }

        // Create BYE match if needed
        if ($byePlayer !== null) {
            $byeMatch = $this->createByeMatch($round, $byePlayer, $tableNumber);
            $round->addMatch($byeMatch);
        }

        // Update tournament status
        $tournament->setStatus(TournamentStatus::ONGOING);
        $tournament->setStartedAt(new \DateTimeImmutable());

        // Start the round
        $round->start();

        $this->entityManager->persist($round);
        $this->entityManager->flush();

        return $round;
    }

    /**
     * Generate pairings for subsequent rounds (Round 2+) of a tournament.
     *
     * This method implements the Swiss pairing algorithm:
     * 1. Validates the previous round is complete
     * 2. Calculates current standings (match points, tiebreakers)
     * 3. Groups players by match points
     * 4. Pairs players within each group, avoiding rematches
     * 5. Handles odd player groups by pairing down
     * 6. Assigns BYE to lowest-ranked player who hasn't had one
     *
     * @param Tournament $tournament The tournament to continue
     *
     * @return Round The created round with all matches
     *
     * @throws InvalidTournamentStateException If tournament is not ONGOING
     * @throws RoundNotCompleteException       If previous round is not complete
     * @throws PairingException                If valid pairings cannot be generated
     */
    public function generateSubsequentRoundPairings(Tournament $tournament): Round
    {
        $this->validateTournamentCanContinue($tournament);

        $previousRound = $tournament->getLatestRound();
        if ($previousRound === null) {
            throw new InvalidTournamentStateException(
                $tournament->getStatus(),
                [TournamentStatus::PUBLISHED],
                'generer la prochaine ronde (aucune ronde precedente)'
            );
        }

        $this->validateRoundComplete($previousRound);

        // Complete the previous round
        if (!$previousRound->isCompleted()) {
            $previousRound->complete();
        }

        // Calculate current standings
        $standings = $this->calculateStandings($tournament);

        // Create the new round
        $newRoundNumber = $previousRound->getRoundNumber() + 1;
        $round = new Round();
        $round->setTournament($tournament);
        $round->setRoundNumber($newRoundNumber);
        $tournament->addRound($round);

        // Generate pairings using Swiss algorithm
        $pairings = $this->generateSwissPairings($standings);

        // Create matches
        $tableNumber = 1;
        foreach ($pairings as $pairing) {
            if ($pairing['isBye']) {
                $byeMatch = $this->createByeMatch($round, $pairing['player1'], $tableNumber);
                $round->addMatch($byeMatch);
            } else {
                $match = $this->createMatch($round, $pairing['player1'], $pairing['player2'], $tableNumber);
                $round->addMatch($match);
            }
            $tableNumber++;
        }

        // Start the round
        $round->start();

        $this->entityManager->persist($round);
        $this->entityManager->flush();

        return $round;
    }

    /**
     * Calculate standings for all players in a tournament.
     *
     * Includes both Swiss tiebreakers (OMWP) and Round-Robin tiebreakers
     * (game differential, games won, head-to-head).
     *
     * @return array<int, PlayerStandings> Map of registration ID to standings
     */
    public function calculateStandings(Tournament $tournament): array
    {
        $registrations = $tournament->getRegistrations()->toArray();
        $standings = [];

        // Initialize standings for all players
        foreach ($registrations as $registration) {
            $standings[$registration->getId()] = new PlayerStandings($registration);
        }

        // Process all matches from all rounds
        foreach ($tournament->getRounds() as $round) {
            foreach ($round->getMatches() as $match) {
                if (!$match->isCompleted()) {
                    continue;
                }

                $player1 = $match->getPlayer1();
                $player2 = $match->getPlayer2();

                if ($match->isByeMatch()) {
                    // BYE match: 2-0 win for player1
                    if (isset($standings[$player1->getId()])) {
                        $standings[$player1->getId()]->addBye();
                        // BYE gives 2-0 game score
                        $standings[$player1->getId()]->addGameResults(2, 0);
                    }
                    continue;
                }

                // Track opponents
                if ($player2 !== null) {
                    $standings[$player1->getId()]->addOpponent($player2);
                    $standings[$player2->getId()]->addOpponent($player1);
                }

                // Track game scores (for Round-Robin BO3 tiebreakers)
                $p1Score = $match->getPlayer1Score();
                $p2Score = $match->getPlayer2Score();
                $standings[$player1->getId()]->addGameResults($p1Score, $p2Score);
                if ($player2 !== null) {
                    $standings[$player2->getId()]->addGameResults($p2Score, $p1Score);
                }

                // Determine winner/loser
                $winner = $match->getWinner();
                $loser = $match->getLoser();

                if ($winner !== null && $loser !== null) {
                    $standings[$winner->getId()]->addWin();
                    $standings[$loser->getId()]->addLoss();

                    // Track head-to-head results (for Round-Robin tiebreaker)
                    if ($winner === $player1) {
                        $standings[$player1->getId()]->addHeadToHeadResult($player2, 'win');
                        $standings[$player2->getId()]->addHeadToHeadResult($player1, 'loss');
                    } else {
                        $standings[$player1->getId()]->addHeadToHeadResult($player2, 'loss');
                        $standings[$player2->getId()]->addHeadToHeadResult($player1, 'win');
                    }
                } elseif ($winner === null && $loser === null && $player2 !== null) {
                    // Draw (rare in TCG, but supported)
                    $standings[$player1->getId()]->addDraw();
                    $standings[$player2->getId()]->addDraw();

                    // Track head-to-head draw
                    $standings[$player1->getId()]->addHeadToHeadResult($player2, 'draw');
                    $standings[$player2->getId()]->addHeadToHeadResult($player1, 'draw');
                }
            }
        }

        // Calculate Opponent Match Win Percentage (OMWP) for Swiss tiebreakers
        foreach ($standings as $standing) {
            $omwp = $this->calculateOMWP($standing, $standings);
            $standing->setOpponentMatchWinPercentage($omwp);
        }

        return $standings;
    }

    /**
     * Calculate Opponent Match Win Percentage (OMWP) for a player.
     *
     * OMWP is the average match win percentage of all opponents faced.
     * This is the primary tiebreaker in Swiss tournaments.
     *
     * @param PlayerStandings                $standing  The player's standings
     * @param array<int, PlayerStandings> $allStandings All player standings
     */
    private function calculateOMWP(PlayerStandings $standing, array $allStandings): float
    {
        $opponents = $standing->getOpponents();

        if (empty($opponents)) {
            return 0.33; // Minimum per Swiss rules
        }

        $totalMWP = 0.0;
        foreach ($opponents as $opponent) {
            if (isset($allStandings[$opponent->getId()])) {
                $totalMWP += $allStandings[$opponent->getId()]->getMatchWinPercentage();
            }
        }

        return $totalMWP / count($opponents);
    }

    /**
     * Sort standings for Round-Robin (Championship) tournaments.
     *
     * Tiebreaker order:
     * 1. Match points (3 per win, 0 per loss, 1 per draw)
     * 2. Game differential (games won - games lost) - useful for BO3
     * 3. Games won - useful for BO3
     * 4. Head-to-head (direct confrontation between tied players)
     *
     * @param array<int, PlayerStandings> $standings Map of registration ID to standings
     * @return PlayerStandings[] Sorted array of standings (best first)
     */
    public function sortRoundRobinStandings(array $standings): array
    {
        $sorted = array_values($standings);

        usort($sorted, function (PlayerStandings $a, PlayerStandings $b): int {
            // 1. Match points (descending)
            $pointsDiff = $b->getMatchPoints() <=> $a->getMatchPoints();
            if ($pointsDiff !== 0) {
                return $pointsDiff;
            }

            // 2. Game differential (descending)
            $diffA = $a->getGameDifferential();
            $diffB = $b->getGameDifferential();
            $gameDiffDiff = $diffB <=> $diffA;
            if ($gameDiffDiff !== 0) {
                return $gameDiffDiff;
            }

            // 3. Games won (descending)
            $gamesWonDiff = $b->getGamesWon() <=> $a->getGamesWon();
            if ($gamesWonDiff !== 0) {
                return $gamesWonDiff;
            }

            // 4. Head-to-head (direct confrontation)
            $h2h = $a->compareHeadToHead($b);
            if ($h2h !== 0) {
                // a beat b => a should be higher (return -1)
                // b beat a => b should be higher (return 1)
                return -$h2h;
            }

            // Still tied - maintain original order
            return 0;
        });

        return $sorted;
    }

    /**
     * Calculate and sort standings for a tournament based on its structure.
     *
     * Uses appropriate tiebreakers:
     * - Swiss: Match points, then OMWP
     * - Round-Robin: Match points, game differential, games won, head-to-head
     *
     * @return PlayerStandings[] Sorted array of standings (best first)
     */
    public function calculateSortedStandings(Tournament $tournament): array
    {
        $standings = $this->calculateStandings($tournament);

        if ($tournament->getStructure()?->hasRoundRobin()) {
            return $this->sortRoundRobinStandings($standings);
        }

        // Swiss/other: sort by match points, then OMWP
        $sorted = array_values($standings);
        usort($sorted, function (PlayerStandings $a, PlayerStandings $b): int {
            $pointsDiff = $b->getMatchPoints() <=> $a->getMatchPoints();
            if ($pointsDiff !== 0) {
                return $pointsDiff;
            }

            return $b->getOpponentMatchWinPercentage() <=> $a->getOpponentMatchWinPercentage();
        });

        return $sorted;
    }

    /**
     * Generate Swiss pairings based on current standings.
     *
     * Algorithm:
     * 1. Sort players by match points (descending), then by OMWP (descending)
     * 2. Handle BYE for odd player count
     * 3. Group players by match points
     * 4. Within each group, pair top player with lowest unpaired player they haven't faced
     * 5. If no valid opponent in group, pair down to next group
     *
     * @param array<int, PlayerStandings> $standings
     *
     * @return array<int, array{player1: Registration, player2?: Registration, isBye: bool}>
     *
     * @throws PairingException If valid pairings cannot be generated
     */
    private function generateSwissPairings(array $standings): array
    {
        // Sort by match points (desc), then OMWP (desc)
        $sortedStandings = array_values($standings);
        usort($sortedStandings, function (PlayerStandings $a, PlayerStandings $b): int {
            $pointsDiff = $b->getMatchPoints() <=> $a->getMatchPoints();
            if ($pointsDiff !== 0) {
                return $pointsDiff;
            }

            return $b->getOpponentMatchWinPercentage() <=> $a->getOpponentMatchWinPercentage();
        });

        $pairings = [];
        $pairedPlayers = [];

        // Handle BYE if odd number of players
        if (count($sortedStandings) % 2 !== 0) {
            $byePlayer = $this->selectByePlayerFromStandings($sortedStandings);
            if ($byePlayer !== null) {
                $pairings[] = [
                    'player1' => $byePlayer->getRegistration(),
                    'isBye' => true,
                ];
                $pairedPlayers[$byePlayer->getRegistration()->getId()] = true;
            }
        }

        // Pair remaining players
        foreach ($sortedStandings as $standing) {
            $player = $standing->getRegistration();

            // Skip if already paired
            if (isset($pairedPlayers[$player->getId()])) {
                continue;
            }

            // Find best available opponent
            $opponent = $this->findBestOpponent($standing, $sortedStandings, $pairedPlayers);

            if ($opponent === null) {
                throw PairingException::noValidOpponent($player->getPlayer()->getPseudo());
            }

            $pairings[] = [
                'player1' => $player,
                'player2' => $opponent->getRegistration(),
                'isBye' => false,
            ];

            $pairedPlayers[$player->getId()] = true;
            $pairedPlayers[$opponent->getRegistration()->getId()] = true;
        }

        return $pairings;
    }

    /**
     * Select a player to receive a BYE from standings.
     *
     * Selects the lowest-ranked player who hasn't already received a BYE.
     *
     * @param PlayerStandings[] $sortedStandings Players sorted by rank (highest first)
     */
    private function selectByePlayerFromStandings(array $sortedStandings): ?PlayerStandings
    {
        // Start from bottom of rankings
        for ($i = count($sortedStandings) - 1; $i >= 0; $i--) {
            if (!$sortedStandings[$i]->hasReceivedBye()) {
                return $sortedStandings[$i];
            }
        }

        // If everyone has had a BYE (very rare), give it to the last person
        return $sortedStandings[count($sortedStandings) - 1];
    }

    /**
     * Find the best available opponent for a player.
     *
     * Preference:
     * 1. Same match points, haven't played before
     * 2. Different match points, haven't played before
     * 3. Same match points, rematch (fallback when no other option)
     * 4. Different match points, rematch (fallback when no other option)
     *
     * @param PlayerStandings             $player         The player seeking an opponent
     * @param PlayerStandings[]           $sortedStandings All players sorted by rank
     * @param array<int, bool>            $pairedPlayers  Already paired player IDs
     */
    private function findBestOpponent(
        PlayerStandings $player,
        array $sortedStandings,
        array $pairedPlayers
    ): ?PlayerStandings {
        $playerId = $player->getRegistration()->getId();
        $playerPoints = $player->getMatchPoints();
        $samePointsOpponents = [];
        $otherOpponents = [];
        // Fallback: opponents already faced (rematches)
        $samePointsRematches = [];
        $otherRematches = [];

        foreach ($sortedStandings as $candidate) {
            $candidateId = $candidate->getRegistration()->getId();

            // Skip self
            if ($candidateId === $playerId) {
                continue;
            }

            // Skip already paired
            if (isset($pairedPlayers[$candidateId])) {
                continue;
            }

            $isRematch = $player->hasPlayedAgainst($candidate->getRegistration());

            // Categorize by point group and rematch status
            if ($candidate->getMatchPoints() === $playerPoints) {
                if ($isRematch) {
                    $samePointsRematches[] = $candidate;
                } else {
                    $samePointsOpponents[] = $candidate;
                }
            } else {
                if ($isRematch) {
                    $otherRematches[] = $candidate;
                } else {
                    $otherOpponents[] = $candidate;
                }
            }
        }

        // Priority 1: Prefer opponent with same points, no rematch
        if (!empty($samePointsOpponents)) {
            return $samePointsOpponents[0];
        }

        // Priority 2: Opponent with different points, no rematch
        if (!empty($otherOpponents)) {
            return $otherOpponents[0];
        }

        // Priority 3: Allow rematch with same points (fallback)
        if (!empty($samePointsRematches)) {
            return $samePointsRematches[0];
        }

        // Priority 4: Allow rematch with different points (fallback)
        if (!empty($otherRematches)) {
            return $otherRematches[0];
        }

        return null;
    }

    /**
     * Validate that a tournament can continue (is ongoing and hasn't reached round limit).
     *
     * @throws InvalidTournamentStateException If tournament is not ONGOING
     * @throws PairingException If round limit has been reached
     */
    private function validateTournamentCanContinue(Tournament $tournament): void
    {
        if (!$tournament->isOngoing()) {
            throw new InvalidTournamentStateException(
                $tournament->getStatus(),
                [TournamentStatus::ONGOING],
                'generer la prochaine ronde'
            );
        }

        if ($tournament->hasReachedSwissRoundLimit()) {
            throw new PairingException(sprintf(
                'Limite de rondes Swiss atteinte (%d/%d). Terminez le tournoi.',
                $tournament->getRoundsCount(),
                $tournament->getSwissRounds()
            ));
        }
    }

    /**
     * Validate that a round is complete (all matches finished).
     *
     * @throws RoundNotCompleteException If round has incomplete matches
     */
    private function validateRoundComplete(Round $round): void
    {
        if (!$round->areAllMatchesCompleted()) {
            $totalMatches = $round->getMatches()->count();
            $completedMatches = $round->getCompletedMatchesCount();

            throw new RoundNotCompleteException(
                $round->getRoundNumber(),
                $completedMatches,
                $totalMatches
            );
        }
    }

    /**
     * Validate that a tournament can start.
     *
     * @throws InvalidTournamentStateException If tournament is not in PUBLISHED status
     */
    private function validateTournamentCanStart(Tournament $tournament): void
    {
        if (!$tournament->isPublished()) {
            throw new InvalidTournamentStateException(
                $tournament->getStatus(),
                [TournamentStatus::PUBLISHED],
                'demarrer le tournoi'
            );
        }
    }

    /**
     * Order registrations based on pairing mode.
     *
     * @param Registration[] $registrations
     *
     * @return Registration[]
     */
    private function orderRegistrations(array $registrations, PairingMode $mode): array
    {
        return match ($mode) {
            PairingMode::RANDOM => $this->shuffleRegistrations($registrations),
            PairingMode::REGISTRATION_ORDER => $this->sortByRegistrationOrder($registrations),
        };
    }

    /**
     * Shuffle registrations randomly using cryptographically secure randomization.
     *
     * @param Registration[] $registrations
     *
     * @return Registration[]
     */
    private function shuffleRegistrations(array $registrations): array
    {
        // Use Fisher-Yates shuffle with random_int for cryptographic randomness
        $count = count($registrations);
        for ($i = $count - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$registrations[$i], $registrations[$j]] = [$registrations[$j], $registrations[$i]];
        }

        return $registrations;
    }

    /**
     * Sort registrations by registration date (earliest first).
     *
     * @param Registration[] $registrations
     *
     * @return Registration[]
     */
    private function sortByRegistrationOrder(array $registrations): array
    {
        usort(
            $registrations,
            fn (Registration $a, Registration $b): int => $a->getRegisteredAt() <=> $b->getRegisteredAt()
        );

        return $registrations;
    }

    /**
     * Select a player to receive a BYE.
     *
     * For Round 1, the last player in the ordered list receives the BYE.
     * For subsequent rounds, players who haven't had a BYE and have the
     * lowest score are prioritized.
     *
     * @param Registration[] $registrations Ordered list of registrations
     *
     * @return Registration The player who will receive the BYE
     */
    private function selectByePlayer(array $registrations): Registration
    {
        // For Round 1, simply select the last player in the ordered list
        return $registrations[count($registrations) - 1];
    }

    /**
     * Create a match between two players.
     */
    private function createMatch(
        Round $round,
        Registration $player1,
        Registration $player2,
        int $tableNumber
    ): TournamentMatch {
        $match = new TournamentMatch();
        $match->setRound($round);
        $match->setPlayer1($player1);
        $match->setPlayer2($player2);
        $match->setTableNumber($tableNumber);

        return $match;
    }

    /**
     * Create a BYE match for a player.
     *
     * A BYE match is automatically completed with the player as winner
     * and a 2-0 score (standard for TCG BYE matches).
     */
    private function createByeMatch(Round $round, Registration $player, int $tableNumber): TournamentMatch
    {
        $match = new TournamentMatch();
        $match->setRound($round);
        $match->setPlayer1($player);
        $match->setTableNumber($tableNumber);
        $match->assignBye();

        return $match;
    }

    /**
     * Generate pairings for a round-robin tournament round.
     *
     * Uses the circle method (Berger tables) to ensure each player
     * plays every other player exactly once across all rounds.
     *
     * For Round 1: Players are shuffled randomly and the order is established.
     * For Round 2+: The player order is reconstructed from Round 1 matches
     * to ensure consistent pairings across all rounds.
     *
     * @param Tournament $tournament The tournament
     * @param int $roundNumber The round number to generate (1-based)
     *
     * @return Round The created round with all matches
     */
    public function generateRoundRobinPairings(Tournament $tournament, int $roundNumber = 1): Round
    {
        if ($roundNumber === 1) {
            $this->validateTournamentCanStart($tournament);

            // Get all registrations and shuffle for round 1
            $registrations = $tournament->getRegistrations()->toArray();
            $registrations = $this->shuffleRegistrations($registrations);
            $registrations = array_values($registrations);

            // Add null for BYE if odd number of players
            if (count($registrations) % 2 !== 0) {
                $registrations[] = null;
            }
        } else {
            $this->validateTournamentCanContinueRoundRobin($tournament);

            $previousRound = $tournament->getLatestRound();
            if ($previousRound !== null) {
                $this->validateRoundComplete($previousRound);
                if (!$previousRound->isCompleted()) {
                    $previousRound->complete();
                }
            }

            // Reconstruct player order from Round 1 for consistent pairings
            $registrations = $this->reconstructRoundRobinOrder($tournament);
        }

        $playerCount = count(array_filter($registrations, fn (?Registration $r): bool => $r !== null));

        if ($playerCount < self::MINIMUM_PLAYERS) {
            throw new InsufficientPlayersException($playerCount, self::MINIMUM_PLAYERS);
        }

        // Create round
        $round = new Round();
        $round->setTournament($tournament);
        $round->setRoundNumber($roundNumber);
        $tournament->addRound($round);

        // Generate pairings using circle method
        $pairings = $this->generateCircleMethodPairings($registrations, $roundNumber);

        // Create matches
        $tableNumber = 1;
        foreach ($pairings as $pairing) {
            if ($pairing['player1'] === null || $pairing['player2'] === null) {
                // BYE match - opponent of null gets the BYE
                $actualPlayer = $pairing['player1'] ?? $pairing['player2'];
                if ($actualPlayer !== null) {
                    $byeMatch = $this->createByeMatch($round, $actualPlayer, $tableNumber);
                    $round->addMatch($byeMatch);
                    $tableNumber++;
                }
            } else {
                $match = $this->createMatch($round, $pairing['player1'], $pairing['player2'], $tableNumber);
                $round->addMatch($match);
                $tableNumber++;
            }
        }

        // Update tournament status for round 1
        if ($roundNumber === 1) {
            $tournament->setStatus(TournamentStatus::ONGOING);
            $tournament->setStartedAt(new \DateTimeImmutable());
        }

        $this->entityManager->persist($round);
        $this->entityManager->flush();

        return $round;
    }

    /**
     * Reconstruct the player order from Round 1 matches.
     *
     * The circle method in Round 1 creates matches as:
     * - Match at table 1: player[0] vs player[n-1]
     * - Match at table 2: player[1] vs player[n-2]
     * - Match at table k: player[k-1] vs player[n-k]
     *
     * This method reverse-engineers the original player order from those pairings
     * to ensure subsequent rounds use the same order for consistent circle rotation.
     *
     * @return array<int, Registration|null> The original player order (null = BYE slot)
     *
     * @throws \LogicException If Round 1 is not found
     */
    private function reconstructRoundRobinOrder(Tournament $tournament): array
    {
        $round1 = null;
        foreach ($tournament->getRounds() as $round) {
            if ($round->getRoundNumber() === 1) {
                $round1 = $round;
                break;
            }
        }

        if ($round1 === null) {
            throw new \LogicException('Round 1 not found for round-robin reconstruction');
        }

        $matches = $round1->getMatches()->toArray();

        // Sort by table number to ensure correct order
        usort($matches, fn (TournamentMatch $a, TournamentMatch $b): int => $a->getTableNumber() <=> $b->getTableNumber());

        // Calculate total positions (2 per match)
        $n = count($matches) * 2;
        $players = array_fill(0, $n, null);

        // Reconstruct positions from matches
        foreach ($matches as $index => $match) {
            // Position index -> player1
            // Position (n - 1 - index) -> player2 (or null if BYE)
            $players[$index] = $match->getPlayer1();

            if (!$match->isByeMatch()) {
                $players[$n - 1 - $index] = $match->getPlayer2();
            }
            // If BYE match, player2 position remains null (BYE slot)
        }

        return $players;
    }

    /**
     * Generate pairings using the circle method (Berger tables).
     *
     * The circle method works as follows:
     * - Fix one player (the first one) in position
     * - Rotate all other players around a "circle"
     * - For round k, rotate (k-1) times
     * - Pair player at position i with player at position (n-1-i)
     *
     * @param array<int, Registration|null> $players Array of players (null = BYE)
     * @param int $roundNumber The round number (1-indexed)
     *
     * @return array<int, array{player1: Registration|null, player2: Registration|null}>
     */
    private function generateCircleMethodPairings(array $players, int $roundNumber): array
    {
        $n = count($players);

        // Rotate players (except first) for this round
        // For round 1, no rotation
        // For round k, rotate (k-1) times
        $rotations = $roundNumber - 1;

        // Split: first player is fixed, rest rotate
        $fixed = $players[0];
        $rotating = array_slice($players, 1);

        // Rotate the array (move last element to front)
        for ($i = 0; $i < $rotations; $i++) {
            $last = array_pop($rotating);
            array_unshift($rotating, $last);
        }

        // Rebuild the array with fixed player at position 0
        $arranged = array_merge([$fixed], $rotating);

        // Create pairings: player i plays player (n-1-i)
        $pairings = [];
        $half = (int) ($n / 2);

        for ($i = 0; $i < $half; $i++) {
            $pairings[] = [
                'player1' => $arranged[$i],
                'player2' => $arranged[$n - 1 - $i],
            ];
        }

        return $pairings;
    }

    /**
     * Validate that a round-robin tournament can continue.
     *
     * @throws InvalidTournamentStateException If tournament is not ONGOING
     * @throws PairingException If all rounds have been played
     */
    private function validateTournamentCanContinueRoundRobin(Tournament $tournament): void
    {
        if (!$tournament->isOngoing()) {
            throw new InvalidTournamentStateException(
                $tournament->getStatus(),
                [TournamentStatus::ONGOING],
                'generer la prochaine ronde'
            );
        }

        // For round-robin, max rounds = players - 1 (or players if odd for BYE rounds)
        $playerCount = $tournament->getRegistrations()->count();

        $maxRounds = $playerCount - 1;
        if ($playerCount % 2 !== 0) {
            $maxRounds = $playerCount;
        }

        $currentRounds = $tournament->getRoundsCount();

        if ($currentRounds >= $maxRounds) {
            throw new PairingException(sprintf(
                'Toutes les rondes du championnat ont été jouées (%d/%d).',
                $currentRounds,
                $maxRounds
            ));
        }
    }
}
