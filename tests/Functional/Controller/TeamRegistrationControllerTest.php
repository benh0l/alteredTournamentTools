<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Tournament;
use App\Repository\RegistrationRepository;
use App\Tests\Support\TeamFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Player self-registration in a 2v2 tournament.
 */
final class TeamRegistrationControllerTest extends WebTestCase
{
    use TeamFixturesTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->em->getConnection()->isTransactionActive()) {
            $this->em->getConnection()->rollBack();
        }
        parent::tearDown();
    }

    public function testPlayerRegistersTeamWithPartnerPseudo(): void
    {
        $tournament = $this->createTournament($this->em, $this->createUser($this->em, 'Orga'), 2);
        $captain = $this->createUser($this->em, 'Alice');
        $partner = $this->createUser($this->em, 'Bob');
        $this->em->flush();

        $this->submitRegistration($tournament, $captain, $partner->getPseudo());

        $this->assertResponseRedirects('/tournaments/' . $tournament->getId());
        $registration = static::getContainer()->get(RegistrationRepository::class)
            ->findOneByTournamentAndPlayer($tournament, $partner);
        $this->assertNotNull($registration);
        $this->assertSame($captain->getId(), $registration->getPlayer()->getId());
        $this->assertSame('lyra', $registration->getPartnerFaction()?->value);
    }

    public function testUnknownPartnerShowsFormError(): void
    {
        $tournament = $this->createTournament($this->em, $this->createUser($this->em, 'Orga'), 2);
        $captain = $this->createUser($this->em, 'Alice');
        $this->em->flush();

        $this->submitRegistration($tournament, $captain, 'personne_' . bin2hex(random_bytes(4)));

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('form[name="registration"]', $this->trans('flash.error.team.partner_not_found'));
        $this->assertFalse(static::getContainer()->get(RegistrationRepository::class)->isPlayerRegistered($tournament, $captain));
    }

    public function testPartnerAlreadyInATeamCannotBeRegisteredAgain(): void
    {
        $tournament = $this->createTournament($this->em, $this->createUser($this->em, 'Orga'), 2);
        $partner = $this->createUser($this->em, 'Bob');
        $first = $this->createUser($this->em, 'Alice');
        $second = $this->createUser($this->em, 'Carol');
        $this->em->flush();

        $this->submitRegistration($tournament, $first, $partner->getPseudo());
        $this->assertResponseRedirects();

        $this->submitRegistration($tournament, $second, $partner->getPseudo());

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('form[name="registration"]', $this->trans('flash.error.team.partner_already_registered'));
        $this->assertFalse(static::getContainer()->get(RegistrationRepository::class)->isPlayerRegistered($tournament, $second));
    }

    public function testSoloRegistrationPageHasNoPartnerField(): void
    {
        $tournament = $this->createTournament($this->em, $this->createUser($this->em, 'Orga'));
        $player = $this->createUser($this->em, 'Alice');
        $this->em->flush();

        $this->client->loginUser($player);
        $this->client->request('GET', '/tournaments/' . $tournament->getId() . '/register');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('[name="registration[partnerPseudo]"]');
    }

    public function testOrganizerAddsAndRemovesGuestTeam(): void
    {
        $organizer = $this->createUser($this->em, 'Orga');
        $tournament = $this->createTournament($this->em, $organizer, 2);
        $this->em->flush();

        $this->client->loginUser($organizer);
        $crawler = $this->client->request('GET', '/tournaments/' . $tournament->getId() . '/registrations');
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form[action$="/registrations/add-guest"]')->form([
            'guest_name' => 'Invité Un',
            'partner_guest_name' => 'Invité Deux',
            'partner_faction' => 'ordis',
        ]);
        $this->client->submit($form);
        $this->assertResponseRedirects('/tournaments/' . $tournament->getId() . '/registrations');

        $this->em->clear();
        $registrations = static::getContainer()->get(RegistrationRepository::class)->findBy(['tournament' => $tournament->getId()]);
        $this->assertCount(1, $registrations);
        $this->assertSame('Invité Deux', $registrations[0]->getPartner()?->getName());
        $this->assertSame('ordis', $registrations[0]->getPartnerFaction()?->value);

        $crawler = $this->client->request('GET', '/tournaments/' . $tournament->getId() . '/registrations');
        $removeForm = $crawler->filter('form[action$="/registrations/' . $registrations[0]->getId() . '/remove"]')->form();
        $this->client->submit($removeForm);

        $this->assertResponseRedirects('/tournaments/' . $tournament->getId() . '/registrations');
        $this->em->clear();
        $this->assertCount(0, static::getContainer()->get(RegistrationRepository::class)->findBy(['tournament' => $tournament->getId()]));
    }

    public function testCsvImportIsNotAvailableForTeamTournaments(): void
    {
        $organizer = $this->createUser($this->em, 'Orga');
        $tournament = $this->createTournament($this->em, $organizer, 2);
        $this->em->flush();

        $this->client->loginUser($organizer);
        $this->client->request('GET', '/tournaments/' . $tournament->getId() . '/registrations/import');

        $this->assertResponseRedirects('/tournaments/' . $tournament->getId() . '/registrations');
    }

    public function testPartnerCanCheckInTheTeam(): void
    {
        $tournament = $this->createTournament($this->em, $this->createUser($this->em, 'Orga'), 2);
        $tournament->setCheckInEnabled(true)->setCheckInOpen(true);
        $partner = $this->createUser($this->em, 'Bob');
        $registration = (new \App\Entity\Registration($tournament, $this->createUser($this->em, 'Alice')))->setPartner($partner);
        $this->em->persist($registration);
        $this->em->flush();

        $this->client->loginUser($partner);
        $crawler = $this->client->request('GET', '/tournaments/' . $tournament->getId());
        $this->assertResponseIsSuccessful();

        $this->client->submit($crawler->filter('form[action$="/check-in"]')->form());

        $this->em->clear();
        $this->assertTrue(static::getContainer()->get(RegistrationRepository::class)->find($registration->getId())?->isCheckedIn());
    }

    public function testOrganizerEditsPartnerDeckFromModal(): void
    {
        $organizer = $this->createUser($this->em, 'Orga');
        $tournament = $this->createTournament($this->em, $organizer, 2);
        $registration = (new \App\Entity\Registration($tournament, $this->createUser($this->em, 'Alice')))
            ->setPartner($this->createUser($this->em, 'Bob'));
        $this->em->persist($registration);
        $this->em->flush();

        $this->client->loginUser($organizer);
        $crawler = $this->client->request('GET', '/tournaments/' . $tournament->getId() . '/registrations');
        $button = $crawler->filter('[data-edit-registration-id="' . $registration->getId() . '"]');
        $this->assertSame('Bob', substr($button->attr('data-edit-registration-partner'), 0, 3));

        $this->client->request('POST', '/tournaments/' . $tournament->getId() . '/registrations/' . $registration->getId() . '/edit', [
            '_token' => $button->attr('data-edit-registration-csrf'),
            'faction' => 'axiom',
            'partner_faction' => 'muna',
            'partner_decklist_url' => 'https://altered.gg/deck/bob',
        ]);

        $this->assertResponseRedirects();
        $this->em->clear();
        $reloaded = static::getContainer()->get(RegistrationRepository::class)->find($registration->getId());
        $this->assertSame('muna', $reloaded?->getPartnerFaction()?->value);
        $this->assertSame('https://altered.gg/deck/bob', $reloaded?->getPartnerDecklistUrl());
    }

    public function testIncompleteTeamCanNameANewPartner(): void
    {
        $tournament = $this->createTournament($this->em, $this->createUser($this->em, 'Orga'), 2);
        $captain = $this->createUser($this->em, 'Alice');
        $newPartner = $this->createUser($this->em, 'Carol');
        $registration = new \App\Entity\Registration($tournament, $captain);
        $this->em->persist($registration);
        $this->em->flush();

        $this->client->loginUser($captain);
        $crawler = $this->client->request('GET', '/tournaments/' . $tournament->getId() . '/registration/edit');
        $this->assertResponseIsSuccessful();

        $this->client->submit($crawler->filter('form[name="registration"]')->form([
            'registration[partnerPseudo]' => $newPartner->getPseudo(),
        ]));

        $this->assertResponseRedirects();
        $this->em->clear();
        $reloaded = static::getContainer()->get(RegistrationRepository::class)->find($registration->getId());
        $this->assertSame($newPartner->getId(), $reloaded?->getPartner()?->getId());
    }

    public function testBifactionSelfRegistrationSavesSecondFaction(): void
    {
        $tournament = $this->createTournament($this->em, $this->createUser($this->em, 'Orga'));
        $tournament->setFormat(\App\Enum\TournamentFormat::CONSTRUCTED_BIFACTION);
        $player = $this->createUser($this->em, 'Alice');
        $this->em->flush();

        $this->client->loginUser($player);
        $crawler = $this->client->request('GET', '/tournaments/' . $tournament->getId() . '/register');
        $this->client->submit($crawler->filter('form[name="registration"]')->form([
            'registration[faction]' => 'axiom',
            'registration[faction2]' => 'bravos',
        ]));

        $this->assertResponseRedirects();
        $registration = static::getContainer()->get(RegistrationRepository::class)->findOneByTournamentAndPlayer($tournament, $player);
        $this->assertSame('bravos', $registration?->getFaction2()?->value);
    }

    public function testPublicPageCountsTeams(): void
    {
        $tournament = $this->createTournament($this->em, $this->createUser($this->em, 'Orga'), 2);
        $this->em->persist((new \App\Entity\Registration($tournament, $this->createUser($this->em, 'Alice')))
            ->setPartner($this->createUser($this->em, 'Bob')));
        $this->em->flush();
        $this->em->refresh($tournament);

        $this->client->request('GET', '/tournaments/' . $tournament->getId() . '/details');

        $this->assertResponseIsSuccessful();
        $expected = static::getContainer()->get('translator')->trans(
            'tournament.capacity.teams_registered',
            ['%count%' => 1],
            null,
            $this->client->getRequest()->getLocale()
        );
        $this->assertStringContainsString('1', $expected);
        $this->assertSelectorTextContains('body', $expected);
    }

    private function trans(string $key): string
    {
        return static::getContainer()->get('translator')->trans($key, [], null, $this->client->getRequest()->getLocale());
    }

    private function submitRegistration(Tournament $tournament, \App\Entity\User $player, string $partnerPseudo): void
    {
        $this->client->loginUser($player);
        $crawler = $this->client->request('GET', '/tournaments/' . $tournament->getId() . '/register');
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form[name="registration"]')->form([
            'registration[faction]' => 'axiom',
            'registration[partnerPseudo]' => $partnerPseudo,
            'registration[partnerFaction]' => 'lyra',
        ]);
        $this->client->submit($form);
    }
}
