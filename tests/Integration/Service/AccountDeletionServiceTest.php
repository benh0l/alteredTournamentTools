<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\Entity\Registration;
use App\Enum\Faction;
use App\Service\AccountDeletionService;
use App\Tests\Support\TeamFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AccountDeletionServiceTest extends KernelTestCase
{
    use TeamFixturesTrait;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
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

    public function testPartnerExportContainsTheirOwnDeckAndTeammate(): void
    {
        $captain = $this->createUser($this->em, 'Alice');
        $partner = $this->createUser($this->em, 'Bob');
        $tournament = $this->createTournament($this->em, $this->createUser($this->em, 'Orga'), 2);
        $registration = (new Registration($tournament, $captain))
            ->setFaction(Faction::AXIOM)
            ->setPartner($partner)
            ->setPartnerFaction(Faction::YZMIR)
            ->setPartnerDecklistUrl('https://altered.gg/deck/bob');
        $this->em->persist($registration);
        $this->em->flush();

        $export = static::getContainer()->get(AccountDeletionService::class)->exportUserData($partner);

        $this->assertCount(1, $export['registrations']);
        $this->assertSame('yzmir', $export['registrations'][0]['faction']);
        $this->assertSame('https://altered.gg/deck/bob', $export['registrations'][0]['decklist_url']);
        $this->assertSame($captain->getPseudo(), $export['registrations'][0]['teammate']);
    }

    public function testSoloExportIsUnchanged(): void
    {
        $player = $this->createUser($this->em, 'Alice');
        $tournament = $this->createTournament($this->em, $this->createUser($this->em, 'Orga'));
        $this->em->persist((new Registration($tournament, $player))->setFaction(Faction::MUNA));
        $this->em->flush();

        $export = static::getContainer()->get(AccountDeletionService::class)->exportUserData($player);

        $this->assertSame('muna', $export['registrations'][0]['faction']);
        $this->assertNull($export['registrations'][0]['teammate']);
    }

    public function testDeletingCaptainHandsTheRegistrationToThePartner(): void
    {
        $captain = $this->createUser($this->em, 'Alice');
        $partner = $this->createUser($this->em, 'Bob');
        $tournament = $this->createTournament($this->em, $this->createUser($this->em, 'Orga'), 2);
        $registration = (new Registration($tournament, $captain))
            ->setFaction(Faction::AXIOM)
            ->setPartner($partner)
            ->setPartnerFaction(Faction::YZMIR);
        $this->em->persist($registration);
        $this->em->flush();
        $captain->getRegistrations()->count(); // initialize the collection, like a real request may do
        $registrationId = $registration->getId();

        static::getContainer()->get(AccountDeletionService::class)->deleteAccount($captain);
        $this->em->clear();

        $reloaded = $this->em->find(Registration::class, $registrationId);
        $this->assertNotNull($reloaded);
        $this->assertSame($partner->getId(), $reloaded->getPlayer()->getId());
        $this->assertSame(Faction::YZMIR, $reloaded->getFaction());
        $this->assertNull($reloaded->getPartner());
        $this->assertTrue($reloaded->isIncompleteTeam());
    }

    public function testDeletingPartnerRemovesThemAndTheirDeckFromTheTeam(): void
    {
        $captain = $this->createUser($this->em, 'Alice');
        $partner = $this->createUser($this->em, 'Bob');
        $tournament = $this->createTournament($this->em, $this->createUser($this->em, 'Orga'), 2);
        $registration = (new Registration($tournament, $captain))
            ->setPartner($partner)
            ->setPartnerDecklistUrl('https://altered.gg/deck/bob');
        $this->em->persist($registration);
        $this->em->flush();
        $registrationId = $registration->getId();

        static::getContainer()->get(AccountDeletionService::class)->deleteAccount($partner);
        $this->em->clear();

        $reloaded = $this->em->find(Registration::class, $registrationId);
        $this->assertNotNull($reloaded);
        $this->assertSame($captain->getId(), $reloaded->getPlayer()->getId());
        $this->assertNull($reloaded->getPartner());
        $this->assertNull($reloaded->getPartnerDecklistUrl());
    }

    public function testDeletingSoloPlayerStillDeletesTheirRegistration(): void
    {
        $player = $this->createUser($this->em, 'Alice');
        $tournament = $this->createTournament($this->em, $this->createUser($this->em, 'Orga'));
        $registration = new Registration($tournament, $player);
        $this->em->persist($registration);
        $this->em->flush();
        $registrationId = $registration->getId();

        static::getContainer()->get(AccountDeletionService::class)->deleteAccount($player);
        $this->em->clear();

        $this->assertNull($this->em->find(Registration::class, $registrationId));
    }
}
