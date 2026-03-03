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
                $currentMatch->assignBye();
                $previousByePlayerId = null;
            }
        }

        // Now assign BYE to the requested player
        if ($existingByeMatch !== null || $orphanedOpponent === null) {
            // We need to convert current match to BYE for the player
            $currentMatch->setPlayer1($player);
            $currentMatch->setPlayer2(null);
            $currentMatch->assignBye();
        } else {
            // The orphaned opponent took the BYE, create new BYE match for player
            $byeMatch = new TournamentMatch();
            $byeMatch->setRound($round);
            $byeMatch->setPlayer1($player);
            $byeMatch->setTableNumber($round->getMatches()->count() + 1);
            $byeMatch->assignBye();
            $round->addMatch($byeMatch);
            $this->entityManager->persist($byeMatch);
        }

        $this->entityManager->flush();

        return $previousByePlayerId;
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
     * Perform the actual swap of players between matches.
     */
    private function performSwap(
        Registration $player1,
        Registration $player2,
        TournamentMatch $match1,
        TournamentMatch $match2
    ): void {
        // Determine positions
        $player1IsPlayer1InMatch = $match1->getPlayer1() === $player1;
        $player2IsPlayer1InMatch = $match2->getPlayer1() === $player2;

        // Swap: put player2 in player1's position
        if ($player1IsPlayer1InMatch) {
            $match1->setPlayer1($player2);
        } else {
            $match1->setPlayer2($player2);
        }

        // Swap: put player1 in player2's position
        if ($player2IsPlayer1InMatch) {
            $match2->setPlayer1($player1);
        } else {
            $match2->setPlayer2($player1);
        }
    }
}
