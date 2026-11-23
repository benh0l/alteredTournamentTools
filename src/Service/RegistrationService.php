<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\DeckChoice;
use App\Entity\Registration;
use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\Faction;
use App\Enum\TournamentStatus;
use App\Exception\AlreadyRegisteredException;
use App\Exception\InvalidTeamException;
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
        ?string $hero = null,
        ?User $partner = null,
        ?DeckChoice $partnerDeck = null,
        ?Faction $faction2 = null
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

        $this->assertValidTeam($tournament, $player, $partner);

        $registration = new Registration($tournament, $player);
        $this->applyPartner($registration, $partner, $partnerDeck);

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
        bool $skipEmail = false,
        ?User $partner = null,
        ?DeckChoice $partnerDeck = null
    ): Registration {
        // Check tournament status allows registration (PUBLISHED, ONGOING or ABANDONED for late additions)
        if (!in_array($tournament->getStatus(), [TournamentStatus::PUBLISHED, TournamentStatus::ONGOING, TournamentStatus::ABANDONED], true)) {
            throw new TournamentNotOpenException();
        }

        // Check if already registered
        if ($this->isPlayerRegistered($player, $tournament)) {
            throw AlreadyRegisteredException::forTournament($tournament->getId() ?? 0);
        }

        $this->assertValidTeam($tournament, $player, $partner);

        $registration = new Registration($tournament, $player);
        $this->applyPartner($registration, $partner, $partnerDeck);

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

    /**
     * Update the partner's deck (faction, hero, decklist) of a team registration.
     * Only allowed while the tournament is still PUBLISHED, unless done by the organizer.
     */
    public function updatePartnerDeck(Registration $registration, DeckChoice $deck, bool $byOrganizer = false): void
    {
        if (!$byOrganizer && $registration->getTournament()->getStatus() !== TournamentStatus::PUBLISHED) {
            throw new \RuntimeException('Impossible de modifier l\'inscription une fois le tournoi commence.');
        }

        if ($registration->getPartner() === null) {
            throw InvalidTeamException::because(InvalidTeamException::PARTNER_NOT_ALLOWED);
        }

        $this->applyPartner($registration, $registration->getPartner(), $deck);
        $this->entityManager->flush();
    }

    /**
     * Give a new partner to a 2v2 team that lost theirs (see Registration::isIncompleteTeam()).
     * Only allowed while the tournament is still PUBLISHED, unless done by the organizer.
     *
     * @throws InvalidTeamException
     */
    public function assignPartner(Registration $registration, User $partner, ?DeckChoice $deck = null, bool $byOrganizer = false): void
    {
        $tournament = $registration->getTournament();

        if (!$byOrganizer && $tournament->getStatus() !== TournamentStatus::PUBLISHED) {
            throw new \RuntimeException('Impossible de modifier l\'inscription une fois le tournoi commence.');
        }

        $this->assertValidTeam($tournament, $registration->getPlayer(), $partner);
        $this->applyPartner($registration, $partner, $deck);
        $this->entityManager->flush();

        $this->logger->info('Partner assigned to incomplete team', [
            'registration_id' => $registration->getId(),
            'partner_id' => $partner->getId(),
            'tournament_id' => $tournament->getId(),
        ]);

        if ($this->messageBus !== null && $registration->getId() !== null) {
            $this->messageBus->dispatch(new SendRegistrationConfirmationMessage($registration->getId()));
        }
    }

    /**
     * Check that the partner given for a registration matches the tournament's team size
     * and that nobody ends up in two teams of the same tournament.
     *
     * @throws InvalidTeamException
     */
    public function assertValidTeam(Tournament $tournament, User $player, ?User $partner): void
    {
        if (!$tournament->isTeamTournament()) {
            if ($partner !== null) {
                throw InvalidTeamException::because(InvalidTeamException::PARTNER_NOT_ALLOWED);
            }

            return;
        }

        if ($partner === null) {
            throw InvalidTeamException::because(InvalidTeamException::PARTNER_REQUIRED);
        }

        if ($partner === $player || ($partner->getId() !== null && $partner->getId() === $player->getId())) {
            throw InvalidTeamException::because(InvalidTeamException::PARTNER_IS_PLAYER);
        }

        if ($this->isPlayerRegistered($partner, $tournament)) {
            throw InvalidTeamException::because(InvalidTeamException::PARTNER_ALREADY_REGISTERED);
        }
    }

    private function applyPartner(Registration $registration, ?User $partner, ?DeckChoice $deck): void
    {
        $registration->setPartner($partner);

        if ($partner === null) {
            return;
        }

        $deck ??= new DeckChoice();
        $registration
            ->setPartnerDecklistUrl($deck->decklistUrl)
            ->setPartnerFaction($deck->faction)
            ->setPartnerFaction2($deck->faction2)
            ->setPartnerHero($deck->hero);
    }
}
