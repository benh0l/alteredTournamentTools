<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Registration;
use App\Entity\Tournament;
use App\Entity\TournamentMatch;
use App\Entity\User;
use App\Enum\MatchStatus;
use App\Enum\TournamentStatus;
use App\Repository\RegistrationRepository;
use App\Repository\TournamentMatchRepository;

/**
 * Service for player match experience.
 *
 * FR48: Players immediately see their ongoing match on home screen.
 * NFR30: Interface "action-first" affiche match en cours en < 1s après connexion joueur.
 */
class PlayerMatchService
{
    public function __construct(
        private readonly RegistrationRepository $registrationRepository,
        private readonly TournamentMatchRepository $matchRepository,
    ) {
    }

    /**
     * Find the player's current ongoing match (if any).
     *
     * Returns the first ongoing match for the player across all tournaments.
     * Returns null if no ongoing match exists.
     */
    public function findCurrentMatch(User $user): ?TournamentMatch
    {
        // Get all registrations for ongoing tournaments
        $registrations = $this->registrationRepository->findBy(['player' => $user]);

        foreach ($registrations as $registration) {
            $tournament = $registration->getTournament();

            // Only check tournaments in progress (ongoing or abandoned)
            if (!$tournament->getStatus()->isInProgress()) {
                continue;
            }

            $currentRound = $tournament->getCurrentRound();
            if ($currentRound === null) {
                continue;
            }

            // Find the player's match in the current round
            foreach ($currentRound->getMatches() as $match) {
                if ($this->isPlayerInMatch($registration, $match) && !$match->isCompleted()) {
                    return $match;
                }
            }
        }

        return null;
    }

    /**
     * Get all ongoing matches for a player across all tournaments.
     *
     * @return TournamentMatch[]
     */
    public function findAllOngoingMatches(User $user): array
    {
        $matches = [];
        $registrations = $this->registrationRepository->findBy(['player' => $user]);

        foreach ($registrations as $registration) {
            $tournament = $registration->getTournament();

            if (!$tournament->getStatus()->isInProgress()) {
                continue;
            }

            $currentRound = $tournament->getCurrentRound();
            if ($currentRound === null) {
                continue;
            }

            foreach ($currentRound->getMatches() as $match) {
                if ($this->isPlayerInMatch($registration, $match) && !$match->isCompleted()) {
                    $matches[] = $match;
                }
            }
        }

        return $matches;
    }

    /**
     * Get the player's registration for a match.
     */
    public function getPlayerRegistration(User $user, TournamentMatch $match): ?Registration
    {
        $tournament = $match->getRound()->getTournament();

        return $this->registrationRepository->findOneBy([
            'player' => $user,
            'tournament' => $tournament,
        ]);
    }

    /**
     * Get upcoming tournament registrations for the player.
     *
     * @return Registration[]
     */
    public function getUpcomingTournamentRegistrations(User $user, int $limit = 3): array
    {
        $registrations = $this->registrationRepository->findBy(['player' => $user]);
        $upcoming = [];

        foreach ($registrations as $registration) {
            $tournament = $registration->getTournament();

            if (in_array($tournament->getStatus(), [TournamentStatus::DRAFT, TournamentStatus::PUBLISHED], true)) {
                $upcoming[] = $registration;
            }
        }

        // Sort by tournament date
        usort($upcoming, fn (Registration $a, Registration $b) => $a->getTournament()->getDate() <=> $b->getTournament()->getDate());

        return array_slice($upcoming, 0, $limit);
    }

    /**
     * Get ongoing tournament registrations for the player.
     *
     * @return Registration[]
     */
    public function getOngoingTournamentRegistrations(User $user): array
    {
        $registrations = $this->registrationRepository->findBy(['player' => $user]);
        $ongoing = [];

        foreach ($registrations as $registration) {
            if ($registration->getTournament()->getStatus()->isInProgress()) {
                $ongoing[] = $registration;
            }
        }

        return $ongoing;
    }

    /**
     * Check if a registration is a player in the given match.
     */
    private function isPlayerInMatch(Registration $registration, TournamentMatch $match): bool
    {
        $player1 = $match->getPlayer1();
        $player2 = $match->getPlayer2();

        return ($player1 !== null && $player1->getId() === $registration->getId())
            || ($player2 !== null && $player2->getId() === $registration->getId());
    }
}
