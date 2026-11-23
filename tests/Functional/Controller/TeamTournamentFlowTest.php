<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Registration;
use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\Faction;
use App\Enum\TournamentStatus;
use App\Service\PairingService;
use App\Service\ResultSubmissionService;
use App\Tests\Support\TeamFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * End-to-end 2v2: teams are paired like players, results are submitted by teammates,
 * and the round and standings pages show team names.
 */
final class TeamTournamentFlowTest extends WebTestCase
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

    public function testSwissRoundWithTeams(): void
    {
        $organizer = $this->createUser($this->em, 'Orga');
        $tournament = $this->createTournament($this->em, $organizer, 2);
        $teams = [];
        for ($i = 1; $i <= 5; ++$i) {
            $team = (new Registration($tournament, $this->createUser($this->em, 'Cap' . $i)))
                ->setFaction(Faction::AXIOM)
                ->setHero('sierra-oddball')
                ->setPartner($this->createUser($this->em, 'Mate' . $i))
                ->setPartnerFaction(Faction::BRAVOS)
                ->setPartnerHero('subhash-marmo');
            $tournament->addRegistration($team);
            $this->em->persist($team);
            $teams[] = $team;
        }
        $this->em->flush();

        $round = static::getContainer()->get(PairingService::class)->generateRound1Pairings($tournament);
        $tournament->setStatus(TournamentStatus::ONGOING);
        $this->em->flush();

        // 5 teams: 2 matches + 1 bye, every team appears exactly once
        $this->assertCount(3, $round->getMatches());
        $seen = [];
        foreach ($round->getMatches() as $match) {
            $seen[] = $match->getPlayer1()->getId();
            if (!$match->isByeMatch()) {
                $seen[] = $match->getPlayer2()?->getId();
            }
        }
        sort($seen);
        $expected = array_map(static fn (Registration $r) => $r->getId(), $teams);
        sort($expected);
        $this->assertSame($expected, $seen);

        // Partners (not captains) submit matching results for a real match
        $match = null;
        foreach ($round->getMatches() as $candidate) {
            if (!$candidate->isByeMatch()) {
                $match = $candidate;
                break;
            }
        }
        $this->assertNotNull($match);
        $submissions = static::getContainer()->get(ResultSubmissionService::class);
        $winnerId = $match->getPlayer1()->getId();
        $submissions->submitResult($match, $match->getPlayer1()->getPartner(), $winnerId, 2, 0);
        $submissions->submitResult($match, $match->getPlayer2()->getPartner(), $winnerId, 2, 0);
        $this->assertTrue($match->isCompleted());

        $this->client->loginUser($organizer);
        $this->client->request('GET', '/tournaments/' . $tournament->getId() . '/rounds/1');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', $match->getPlayer1()->getDisplayName());

        $this->client->request('GET', '/tournaments/' . $tournament->getId() . '/rounds/standings');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', $this->teamName($teams[0]));
        // Both teammates' heroes are shown in the standings
        $this->assertSelectorTextContains('body', 'Sierra & Oddball');
        $this->assertSelectorTextContains('body', 'Subhash & Marmo');

        $this->client->request('GET', '/tournaments/' . $tournament->getId() . '/registrations');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Subhash & Marmo');

        // Results pages (podium, stats, final standings and their share images) show both heroes
        // The entity manager is reset between requests: reload the tournament from the current one
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->find(Tournament::class, $tournament->getId())?->setStatus(TournamentStatus::COMPLETED);
        $em->flush();
        foreach (['podium', 'stats', 'final-standings'] as $page) {
            $crawler = $this->client->request('GET', '/tournaments/' . $tournament->getId() . '/' . $page);
            $this->assertResponseIsSuccessful($page);
            $this->assertStringContainsString('Sierra &amp; Oddball', $this->client->getResponse()->getContent(), $page);
            $this->assertStringContainsString('Subhash &amp; Marmo', $this->client->getResponse()->getContent(), $page);
        }
    }

    private function teamName(Registration $team): string
    {
        return implode(' & ', array_map(static fn (User $u) => $u->getDisplayName(), $team->getMembers()));
    }
}
