<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\Registration;
use App\Repository\RegistrationRepository;
use App\Tests\Support\TeamFixturesTrait;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Member-aware queries: a 2v2 partner must be found like the captain.
 */
final class RegistrationRepositoryTest extends KernelTestCase
{
    use TeamFixturesTrait;

    private EntityManagerInterface $em;
    private RegistrationRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->repository = static::getContainer()->get(RegistrationRepository::class);
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->em->getConnection()->isTransactionActive()) {
            $this->em->getConnection()->rollBack();
        }
        parent::tearDown();
    }

    public function testPartnerIsFoundByMemberQueries(): void
    {
        $organizer = $this->createUser($this->em, 'Orga');
        $captain = $this->createUser($this->em, 'Alice');
        $partner = $this->createUser($this->em, 'Bob');
        $outsider = $this->createUser($this->em, 'Carol');
        $tournament = $this->createTournament($this->em, $organizer, 2);
        $registration = (new Registration($tournament, $captain))->setPartner($partner);
        $this->em->persist($registration);
        $this->em->flush();
        $this->em->clear();

        $tournament = $this->em->getReference($tournament::class, $tournament->getId());

        $this->assertSame($registration->getId(), $this->repository->findOneByTournamentAndPlayer($tournament, $partner)?->getId());
        $this->assertTrue($this->repository->isPlayerRegistered($tournament, $partner));
        $this->assertTrue($this->repository->isPlayerRegistered($tournament, $captain));
        $this->assertFalse($this->repository->isPlayerRegistered($tournament, $outsider));
        $this->assertCount(1, $this->repository->findByPlayer($partner));
        $this->assertCount(1, $this->repository->findUpcomingByPlayer($partner));
        $this->assertCount(1, $this->repository->findByPlayerWithTournament($partner));
    }

    public function testSamePartnerCannotBeInTwoTeamsOfTheSameTournament(): void
    {
        $organizer = $this->createUser($this->em, 'Orga');
        $partner = $this->createUser($this->em, 'Bob');
        $tournament = $this->createTournament($this->em, $organizer, 2);
        $this->em->persist((new Registration($tournament, $this->createUser($this->em, 'Alice')))->setPartner($partner));
        $this->em->persist((new Registration($tournament, $this->createUser($this->em, 'Dave')))->setPartner($partner));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->em->flush();
    }

    public function testDeletingPartnerKeepsTheTeamRegistration(): void
    {
        $organizer = $this->createUser($this->em, 'Orga');
        $partner = $this->createUser($this->em, 'Bob');
        $tournament = $this->createTournament($this->em, $organizer, 2);
        $registration = (new Registration($tournament, $this->createUser($this->em, 'Alice')))->setPartner($partner);
        $this->em->persist($registration);
        $this->em->flush();

        $this->em->getConnection()->executeStatement('DELETE FROM users WHERE id = ?', [$partner->getId()]);
        $this->em->clear();

        $reloaded = $this->repository->find($registration->getId());
        $this->assertNotNull($reloaded);
        $this->assertNull($reloaded->getPartner());
    }
}
