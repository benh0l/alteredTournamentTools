<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\DecklistTransparency;
use App\Enum\MatchFormat;
use App\Enum\TournamentFormat;
use App\Enum\TournamentStatus;
use App\Enum\TournamentStructure;
use App\Enum\TournamentVisibility;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Helpers to create persisted users and tournaments in database tests.
 * Tests using it are expected to run inside a transaction rolled back in tearDown().
 */
trait TeamFixturesTrait
{
    private function createUser(EntityManagerInterface $em, string $pseudo): User
    {
        $user = new User();
        $user->setEmail(strtolower($pseudo) . '-' . bin2hex(random_bytes(4)) . '@test.local');
        $user->setPseudo($pseudo . '_' . bin2hex(random_bytes(3)));
        $user->setPassword('not-used');
        $em->persist($user);

        return $user;
    }

    private function createTournament(EntityManagerInterface $em, User $organizer, int $teamSize = 1): Tournament
    {
        $tournament = new Tournament();
        $tournament->setName('Tournoi test ' . $teamSize . 'v' . $teamSize);
        $tournament->setOrganizer($organizer);
        $tournament->setDate(new \DateTimeImmutable('+1 week'));
        $tournament->setFormat(TournamentFormat::CONSTRUCTED_STANDARD);
        $tournament->setStructure(TournamentStructure::SWISS_ONLY);
        $tournament->setVisibility(TournamentVisibility::PUBLIC);
        $tournament->setDecklistTransparency(DecklistTransparency::OPEN);
        $tournament->setSwissMatchFormat(MatchFormat::BO3);
        $tournament->setStatus(TournamentStatus::PUBLISHED);
        $tournament->setTeamSize($teamSize);
        $em->persist($tournament);

        return $tournament;
    }
}
