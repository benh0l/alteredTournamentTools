<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Registration;
use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\Faction;
use App\Enum\TournamentStatus;
use App\Exception\AlreadyRegisteredException;
use App\Exception\RegistrationClosedException;
use App\Exception\TournamentNotOpenException;
use App\Message\SendRegistrationConfirmationMessage;
use App\Repository\RegistrationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class RegistrationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RegistrationRepository $registrationRepository,
        private readonly LoggerInterface $logger,
        private readonly ?MessageBusInterface $messageBus = null
    ) {
    }

    public function registerPlayerToTournament(
        User $player,
        Tournament $tournament,
        ?string $decklistUrl = null,
        ?Faction $faction = null,
        ?string $hero = null
    ): Registration {
        // Check tournament is open for registration
        if ($tournament->getStatus() !== TournamentStatus::PUBLISHED) {
            $this->logger->warning('Attempted registration to non-published tournament', [
                'player_id' => $player->getId(),
                'tournament_id' => $tournament->getId(),
                'tournament_status' => $tournament->getStatus()->value,
            ]);
            throw new TournamentNotOpenException();
        }

        // Check if registrations are closed
        if ($tournament->isRegistrationsClosed()) {
            $this->logger->info('Attempted registration to closed tournament', [
                'player_id' => $player->getId(),
                'tournament_id' => $tournament->getId(),
            ]);
            throw new RegistrationClosedException();
        }

        // Check if already registered
        if ($this->isPlayerRegistered($player, $tournament)) {
            $this->logger->warning('Attempted duplicate registration', [
                'player_id' => $player->getId(),
                'tournament_id' => $tournament->getId(),
            ]);
            throw AlreadyRegisteredException::forTournament($tournament->getId() ?? 0);
        }

        $registration = new Registration($tournament, $player);

        if ($decklistUrl !== null && $decklistUrl !== '') {
            $registration->setDecklistUrl($decklistUrl);
        }

        if ($faction !== null) {
            $registration->setFaction($faction);
        }

        if ($hero !== null && $hero !== '') {
            $registration->setHero($hero);
        }

        $this->entityManager->persist($registration);
        $this->entityManager->flush();

        // Check if this is organizer self-registration
        $isOrganizerSelfRegistration = $tournament->getOrganizer()->getId() === $player->getId();

        $this->logger->info('Player registered to tournament', [
            'player_id' => $player->getId(),
            'player_pseudo' => $player->getPseudo(),
            'tournament_id' => $tournament->getId(),
            'tournament_name' => $tournament->getName(),
            'is_organizer' => $isOrganizerSelfRegistration,
        ]);

        // Dispatch email confirmation message asynchronously (skip for organizer self-registration)
        if ($this->messageBus !== null && $registration->getId() !== null && !$isOrganizerSelfRegistration) {
            $this->messageBus->dispatch(new SendRegistrationConfirmationMessage($registration->getId()));
            $this->logger->info('Registration confirmation email dispatched', [
                'registration_id' => $registration->getId(),
            ]);
        }

        return $registration;
    }

    /**
     * Register a player to a tournament by the organizer.
     * This bypasses the "registrations closed" check but still prevents duplicates.
     *
     * @param bool $skipEmail If true, don't send confirmation email (useful for bulk import)
     */
    public function registerPlayerByOrganizer(
        User $player,
        Tournament $tournament,
        ?string $decklistUrl = null,
        ?Faction $faction = null,
        ?string $hero = null,
        ?Faction $faction2 = null,
        bool $skipEmail = false
    ): Registration {
        // Check tournament status allows registration (PUBLISHED or ONGOING for late additions)
        if (!in_array($tournament->getStatus(), [TournamentStatus::PUBLISHED, TournamentStatus::ONGOING], true)) {
            throw new TournamentNotOpenException();
        }

        // Check if already registered
        if ($this->isPlayerRegistered($player, $tournament)) {
            throw AlreadyRegisteredException::forTournament($tournament->getId() ?? 0);
        }

        $registration = new Registration($tournament, $player);

        if ($decklistUrl !== null && $decklistUrl !== '') {
            $registration->setDecklistUrl($decklistUrl);
        }

        if ($faction !== null) {
            $registration->setFaction($faction);
        }

        if ($faction2 !== null) {
            $registration->setFaction2($faction2);
        }

        if ($hero !== null && $hero !== '') {
            $registration->setHero($hero);
        }

        $this->entityManager->persist($registration);
        $this->entityManager->flush();

        $this->logger->info('Player registered to tournament by organizer', [
            'player_id' => $player->getId(),
            'player_pseudo' => $player->getPseudo(),
            'tournament_id' => $tournament->getId(),
            'tournament_name' => $tournament->getName(),
        ]);

        // Send confirmation email (unless skipped for bulk import)
        if (!$skipEmail && $this->messageBus !== null && $registration->getId() !== null) {
            $this->messageBus->dispatch(new SendRegistrationConfirmationMessage($registration->getId()));
        }

        return $registration;
    }

    public function isPlayerRegistered(User $player, Tournament $tournament): bool
    {
        return $this->registrationRepository->isPlayerRegistered($tournament, $player);
    }

    public function unregisterPlayer(User $player, Tournament $tournament): void
    {
        $registration = $this->registrationRepository->findOneByTournamentAndPlayer($tournament, $player);

        if ($registration === null) {
            throw new \InvalidArgumentException('Le joueur n\'est pas inscrit a ce tournoi.');
        }

        // Only allow unregistration if tournament is still PUBLISHED
        if ($tournament->getStatus() !== TournamentStatus::PUBLISHED) {
            throw new \RuntimeException('Impossible de se desinscrire une fois le tournoi commence.');
        }

        $this->entityManager->remove($registration);
        $this->entityManager->flush();

        $this->logger->info('Player unregistered from tournament', [
            'player_id' => $player->getId(),
            'player_pseudo' => $player->getPseudo(),
            'tournament_id' => $tournament->getId(),
            'tournament_name' => $tournament->getName(),
        ]);
    }

    public function getRegistrationForPlayer(User $player, Tournament $tournament): ?Registration
    {
        return $this->registrationRepository->findOneByTournamentAndPlayer($tournament, $player);
    }

    public function updateDecklist(Registration $registration, ?string $decklistUrl): void
    {
        $tournament = $registration->getTournament();

        // Only allow update if tournament is still PUBLISHED
        if ($tournament->getStatus() !== TournamentStatus::PUBLISHED) {
            throw new \RuntimeException('Impossible de modifier la decklist une fois le tournoi commence.');
        }

        $registration->setDecklistUrl($decklistUrl);
        $this->entityManager->flush();

        $this->logger->info('Player updated decklist', [
            'player_id' => $registration->getPlayer()->getId(),
            'tournament_id' => $tournament->getId(),
        ]);
    }

    /**
     * Update a player's registration (faction, hero, decklist).
     * Only allowed if tournament is still PUBLISHED.
     */
    public function updateRegistration(
        Registration $registration,
        ?string $decklistUrl = null,
        ?Faction $faction = null,
        ?string $hero = null,
        ?Faction $faction2 = null
    ): void {
        $tournament = $registration->getTournament();

        // Only allow update if tournament is still PUBLISHED
        if ($tournament->getStatus() !== TournamentStatus::PUBLISHED) {
            throw new \RuntimeException('Impossible de modifier l\'inscription une fois le tournoi commence.');
        }

        $registration->setDecklistUrl($decklistUrl);
        $registration->setFaction($faction);
        $registration->setFaction2($faction2);
        $registration->setHero($hero);

        $this->entityManager->flush();

        $this->logger->info('Player updated registration', [
            'player_id' => $registration->getPlayer()->getId(),
            'tournament_id' => $tournament->getId(),
            'faction' => $faction?->value,
            'hero' => $hero,
        ]);
    }
}
