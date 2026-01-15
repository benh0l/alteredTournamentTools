<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Registration;
use App\Entity\User;
use App\Enum\TournamentStatus;
use App\Repository\RegistrationRepository;

final class MyTournamentsService
{
    public function __construct(
        private readonly RegistrationRepository $registrationRepository
    ) {
    }

    /**
     * Get tournaments grouped by status for a player.
     *
     * @return array{upcoming: Registration[], ongoing: Registration[], completed: Registration[]}
     */
    public function getGroupedTournamentsForPlayer(User $player): array
    {
        $registrations = $this->registrationRepository->findByPlayerWithTournament($player);

        $grouped = [
            'upcoming' => [],
            'ongoing' => [],
            'completed' => [],
        ];

        foreach ($registrations as $registration) {
            $tournament = $registration->getTournament();
            $status = $tournament->getStatus();

            if ($status === TournamentStatus::PUBLISHED) {
                $grouped['upcoming'][] = $registration;
            } elseif ($status === TournamentStatus::ONGOING) {
                $grouped['ongoing'][] = $registration;
            } elseif ($status === TournamentStatus::COMPLETED) {
                $grouped['completed'][] = $registration;
            }
            // DRAFT and CANCELLED tournaments are not shown
        }

        // Sort upcoming by date ASC (nearest first)
        usort($grouped['upcoming'], static fn (Registration $a, Registration $b): int => $a->getTournament()->getDate() <=> $b->getTournament()->getDate());

        // Sort completed by date DESC (most recent first)
        usort($grouped['completed'], static fn (Registration $a, Registration $b): int => $b->getTournament()->getDate() <=> $a->getTournament()->getDate());

        return $grouped;
    }

    /**
     * Get all registrations for a player (for pagination).
     *
     * @return Registration[]
     */
    public function getAllRegistrationsForPlayer(User $player): array
    {
        return $this->registrationRepository->findByPlayerWithTournament($player);
    }

    /**
     * Get total count of registrations for a player.
     */
    public function getRegistrationCountForPlayer(User $player): int
    {
        return count($this->registrationRepository->findByPlayer($player));
    }
}
