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
