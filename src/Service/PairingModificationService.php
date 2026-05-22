<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\AssignByeRequest;
use App\DTO\PairingSwapRequest;
use App\DTO\SwapResult;
use App\Entity\Registration;
use App\Entity\Round;
use App\Entity\Tournament;
use App\Entity\TournamentMatch;
use App\Enum\MatchFormat;
use App\Enum\MatchStatus;
use App\Enum\RoundStatus;
use App\Enum\TournamentStructure;
use App\Exception\PairingModificationException;
use App\Repository\TournamentMatchRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for manually modifying tournament pairings.
 *
 * Allows organizers to:
 * - Swap players between tables
 * - Assign manual BYEs
 *
 * Restrictions:
 * - Only works for Swiss tournaments
 * - Only when round status is PENDING
 */
final class PairingModificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TournamentMatchRepository $matchRepository,
    ) {
    }

    /**
     * Swap two players between their matches.
     *
     * After swap:
     * - Player1 takes Player2's position (plays against Player2's original opponent)
     * - Player2 takes Player1's position (plays against Player1's original opponent)
     *
     * @throws PairingModificationException If validation fails or swap cannot be performed
     */
    public function swapPlayers(Round $round, PairingSwapRequest $request): SwapResult
    {
        $this->validateRoundCanBeModified($round);

        $player1 = $this->findRegistration($round->getTournament(), $request->player1Id);
        $player2 = $this->findRegistration($round->getTournament(), $request->player2Id);

        $this->validatePlayersAreActive($player1, $player2);

        $match1 = $this->findMatchForPlayer($round, $player1);
        $match2 = $this->findMatchForPlayer($round, $player2);

        if ($match1 === null || $match2 === null) {
            throw PairingModificationException::playerNotInRound();
        }

        // Same match = no swap needed
        if ($match1 === $match2) {
            throw PairingModificationException::playersInSameMatch();
        }

        // Special case: both players have BYE - merge them into one match
        if ($match1->isByeMatch() && $match2->isByeMatch()) {
            $this->mergeTwoByeMatches($player1, $player2, $match1, $match2, $round);
            $this->entityManager->flush();

            return SwapResult::success();
        }

        // Check for potential rematch after swap
        $rematchInfo = $this->checkForRematches($round->getTournament(), $player1, $player2, $match1, $match2);

        // If rematch detected and forceSwap is false, return warning
        if ($rematchInfo !== null && !$request->forceSwap) {
            return SwapResult::rematchWarning($rematchInfo);
        }

        // Perform the swap
        $this->performSwap($player1, $player2, $match1, $match2);

        $this->entityManager->flush();

        return SwapResult::success();
    }

    /**
     * Assign a manual BYE to a player.
     *
     * The orphaned opponent will:
     * - Take the previous BYE player's spot (if one exists)
     * - Get the BYE themselves (if no previous BYE player exists)
     *
     * @throws PairingModificationException If validation fails or BYE cannot be assigned
     */
    public function assignManualBye(Round $round, AssignByeRequest $request): ?int
    {
        $this->validateRoundCanBeModified($round);

        $player = $this->findRegistration($round->getTournament(), $request->playerId);

        if ($player->isDropped()) {
            throw PairingModificationException::playerDropped($player->getPlayer()->getPseudo());
        }

        $currentMatch = $this->findMatchForPlayer($round, $player);

        if ($currentMatch === null) {
            throw PairingModificationException::playerNotInRound();
        }

        // Already has BYE
        if ($currentMatch->isByeMatch() && $currentMatch->getPlayer1() === $player) {
            throw PairingModificationException::alreadyHasBye();
        }

        // Find the orphaned opponent
        $orphanedOpponent = $currentMatch->getOpponent($player);

        // Find existing BYE match in round
        $existingByeMatch = $this->findByeMatch($round);
        $previousByePlayerId = null;

        if ($existingByeMatch !== null) {
            // There's already a BYE player
            $previousByePlayer = $existingByeMatch->getPlayer1();
            $previousByePlayerId = $previousByePlayer->getId();

            // Restore the previous BYE match to a normal match with the orphaned opponent
            $existingByeMatch->setPlayer2($orphanedOpponent);
            $existingByeMatch->setIsBye(false);
            $existingByeMatch->setStatus(MatchStatus::PENDING);
            $existingByeMatch->setResult(null);
            $existingByeMatch->setCompletedAt(null);
        } else {
            // No existing BYE - orphaned opponent gets the BYE
            if ($orphanedOpponent !== null) {
                // Convert the current match to a BYE for the orphaned opponent
                $currentMatch->setPlayer1($orphanedOpponent);
                $currentMatch->prepareBye(); // Use prepareBye - will be completed when round starts
                $previousByePlayerId = null;
            }
        }

        // Now assign BYE to the requested player
        if ($existingByeMatch !== null || $orphanedOpponent === null) {
            // We need to convert current match to BYE for the player
            $currentMatch->setPlayer1($player);
            $currentMatch->setPlayer2(null);
            $currentMatch->prepareBye(); // Use prepareBye - will be completed when round starts
        } else {
            // The orphaned opponent took the BYE, create new BYE match for player
            $byeMatch = new TournamentMatch();
            $byeMatch->setRound($round);
            $byeMatch->setPlayer1($player);
            $byeMatch->setTableNumber($round->getMatches()->count() + 1);
            $byeMatch->prepareBye(); // Use prepareBye - will be completed when round starts
            $round->addMatch($byeMatch);
            $this->entityManager->persist($byeMatch);
        }

        $this->entityManager->flush();

        return $previousByePlayerId;
    }

    /**
     * Fill a BYE slot with a player from another match.
     *
     * Takes a player from their current match and adds them to a BYE match,
     * converting it to a normal match. The player's original opponent gets a BYE.
     *
     * @throws PairingModificationException If validation fails
     */
    public function fillByeSlot(Round $round, int $playerId, int $matchId): void
    {
        $this->validateRoundCanBeModified($round);

        $player = $this->findRegistration($round->getTournament(), $playerId);

        if ($player->isDropped()) {
            throw PairingModificationException::playerDropped($player->getPlayer()->getPseudo());
        }

        // Find the target BYE match
        $byeMatch = null;
        foreach ($round->getMatches() as $match) {
            if ($match->getId() === $matchId) {
                $byeMatch = $match;
                break;
            }
        }

        if ($byeMatch === null) {
            throw PairingModificationException::playerNotInRound();
        }

        if (!$byeMatch->isByeMatch()) {
            throw new PairingModificationException('Ce match n\'est pas un BYE.', 'not_bye_match');
        }

        // Find the player's current match
        $currentMatch = $this->findMatchForPlayer($round, $player);

        if ($currentMatch === null) {
            throw PairingModificationException::playerNotInRound();
        }

        // Can't fill into own match
        if ($currentMatch === $byeMatch) {
            throw PairingModificationException::playersInSameMatch();
        }

        // Get the player's current opponent
        $currentOpponent = $currentMatch->getOpponent($player);

        // Add player to the BYE match as player2
        $byeMatch->setPlayer2($player);
        $byeMatch->setIsBye(false);
        $byeMatch->setStatus(MatchStatus::PENDING);
        $byeMatch->setResult(null);
        $byeMatch->setCompletedAt(null);

        // Handle the player's original match
        if ($currentMatch->isByeMatch()) {
            // Player was alone with BYE - remove the match
            $round->removeMatch($currentMatch);
            $this->entityManager->remove($currentMatch);
        } elseif ($currentOpponent !== null) {
            // Convert original match to BYE for the opponent
            $currentMatch->setPlayer1($currentOpponent);
            $currentMatch->setPlayer2(null);
            $currentMatch->prepareBye();
        }

        $this->entityManager->flush();
    }

    /**
     * Check if swapping would create a rematch.
     *
     * @return array{player1: string, player2: string, previousRound: int}|null Rematch info or null if no rematch
     */
    public function checkRematch(Tournament $tournament, int $player1Id, int $player2Id): ?array
    {
        $player1 = $this->findRegistration($tournament, $player1Id);
        $player2 = $this->findRegistration($tournament, $player2Id);

        $previousRound = $this->findPreviousMatchRound($tournament, $player1, $player2);

        if ($previousRound === null) {
            return null;
        }

        return [
            'player1' => $player1->getPlayer()->getPseudo(),
            'player2' => $player2->getPlayer()->getPseudo(),
            'previousRound' => $previousRound,
        ];
    }

    /**
     * Validate that the round can be modified.
     *
     * @throws PairingModificationException If round cannot be modified
     */
    private function validateRoundCanBeModified(Round $round): void
    {
        // Must be PENDING status
        if ($round->getStatus() !== RoundStatus::PENDING) {
            throw PairingModificationException::roundNotPending($round->getStatus());
        }

        $tournament = $round->getTournament();

        // Must be Swiss structure (or MIXED in Swiss phase)
        $structure = $tournament->getStructure();
        $isSwissPhase = $structure === TournamentStructure::SWISS_ONLY
            || ($structure === TournamentStructure::MIXED && !$round->isEliminationRound());

        if (!$isSwissPhase) {
            throw PairingModificationException::notSwissFormat($structure);
        }
    }

    /**
     * Validate that both players are active (not dropped).
     */
    private function validatePlayersAreActive(Registration $player1, Registration $player2): void
    {
        if ($player1->isDropped()) {
            throw PairingModificationException::playerDropped($player1->getPlayer()->getPseudo());
        }

        if ($player2->isDropped()) {
            throw PairingModificationException::playerDropped($player2->getPlayer()->getPseudo());
        }
    }

    /**
     * Find a registration by ID in a tournament.
     *
     * @throws PairingModificationException If registration not found
     */
    private function findRegistration(Tournament $tournament, int $playerId): Registration
    {
        foreach ($tournament->getRegistrations() as $registration) {
            if ($registration->getId() === $playerId) {
                return $registration;
            }
        }

        throw PairingModificationException::playerNotFound($playerId);
    }

    /**
     * Find the match containing a player in a round.
     */
    private function findMatchForPlayer(Round $round, Registration $player): ?TournamentMatch
    {
        foreach ($round->getMatches() as $match) {
            if ($match->hasPlayer($player)) {
                return $match;
            }
        }

        return null;
    }

    /**
     * Find the existing BYE match in a round.
     */
    private function findByeMatch(Round $round): ?TournamentMatch
    {
        foreach ($round->getMatches() as $match) {
            if ($match->isByeMatch()) {
                return $match;
            }
        }

        return null;
    }

    /**
     * Check for potential rematches after a swap.
     *
     * @return array{player1: string, player2: string, previousRound: int}|null
     */
    private function checkForRematches(
        Tournament $tournament,
        Registration $player1,
        Registration $player2,
        TournamentMatch $match1,
        TournamentMatch $match2
    ): ?array {
        // After swap: player1 plays against player2's opponent
        $player2Opponent = $match2->getOpponent($player2);

        if ($player2Opponent !== null) {
            $previousRound = $this->findPreviousMatchRound($tournament, $player1, $player2Opponent);
            if ($previousRound !== null) {
                return [
                    'player1' => $player1->getPlayer()->getPseudo(),
                    'player2' => $player2Opponent->getPlayer()->getPseudo(),
                    'previousRound' => $previousRound,
                ];
            }
        }

        // After swap: player2 plays against player1's opponent
        $player1Opponent = $match1->getOpponent($player1);

        if ($player1Opponent !== null) {
            $previousRound = $this->findPreviousMatchRound($tournament, $player2, $player1Opponent);
            if ($previousRound !== null) {
                return [
                    'player1' => $player2->getPlayer()->getPseudo(),
                    'player2' => $player1Opponent->getPlayer()->getPseudo(),
                    'previousRound' => $previousRound,
                ];
            }
        }

        return null;
    }

    /**
     * Find the round number where two players previously faced each other.
     */
    private function findPreviousMatchRound(Tournament $tournament, Registration $player1, Registration $player2): ?int
    {
        foreach ($tournament->getRounds() as $round) {
            // Skip the current (pending) round
            if ($round->isPending()) {
                continue;
            }

            foreach ($round->getMatches() as $match) {
                if ($match->hasPlayer($player1) && $match->hasPlayer($player2)) {
                    return $round->getRoundNumber();
                }
            }
        }

        return null;
    }

    /**
     * Merge two BYE matches into one normal match.
     *
     * When both players have BYEs, combine them into a single match.
     */
    private function mergeTwoByeMatches(
        Registration $player1,
        Registration $player2,
        TournamentMatch $match1,
        TournamentMatch $match2,
        Round $round
    ): void {
        // Use match1 for the new match, remove match2
        $match1->setPlayer1($player1);
        $match1->setPlayer2($player2);
        $match1->setIsBye(false);
        $match1->setStatus(MatchStatus::PENDING);
        $match1->setResult(null);
        $match1->setCompletedAt(null);

        // Remove match2 from the round
        $round->removeMatch($match2);
        $this->entityManager->remove($match2);
    }

    /**
     * Perform the actual swap of players between matches.
     *
     * Handles BYE matches correctly by transferring the BYE flag.
     */
    private function performSwap(
        Registration $player1,
        Registration $player2,
        TournamentMatch $match1,
        TournamentMatch $match2
    ): void {
        $match1IsBye = $match1->isByeMatch();
        $match2IsBye = $match2->isByeMatch();

        // Get opponents before swap
        $player1Opponent = $match1->getOpponent($player1);
        $player2Opponent = $match2->getOpponent($player2);

        if ($match1IsBye && !$match2IsBye) {
            // Player1 has BYE, Player2 is in normal match
            // After swap: Player2 gets BYE, Player1 joins normal match

            // Match1 becomes normal match: Player2 vs Player1's old opponent (null for BYE)
            // But since Match1 was BYE, we need to convert it
            $match1->setPlayer1($player2);
            $match1->setPlayer2($player2Opponent);
            $match1->setIsBye(false);

            // Match2: Player1 takes Player2's spot
            $match2->setPlayer1($player1);
            $match2->setPlayer2($player1Opponent);

        } elseif (!$match1IsBye && $match2IsBye) {
            // Player1 is in normal match, Player2 has BYE
            // After swap: Player1 gets BYE, Player2 joins normal match

            // Match1: Player2 takes Player1's spot
            if ($match1->getPlayer1() === $player1) {
                $match1->setPlayer1($player2);
            } else {
                $match1->setPlayer2($player2);
            }

            // Match2 stays BYE with Player1
            $match2->setPlayer1($player1);
            // player2 stays null, isBye stays true

        } else {
            // Both normal matches or both BYE (rare) - standard swap
            $player1IsPlayer1InMatch = $match1->getPlayer1() === $player1;
            $player2IsPlayer1InMatch = $match2->getPlayer1() === $player2;

            if ($player1IsPlayer1InMatch) {
                $match1->setPlayer1($player2);
            } else {
                $match1->setPlayer2($player2);
            }

            if ($player2IsPlayer1InMatch) {
                $match2->setPlayer1($player1);
            } else {
                $match2->setPlayer2($player1);
            }
        }
    }
}
