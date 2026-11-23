<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Registration;
use App\Entity\Tournament;
use App\Enum\TournamentVisibility;
use App\Repository\RegistrationRepository;
use App\Repository\TournamentMatchRepository;
use App\Service\StandingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Controller for tournament results and completion.
 *
 * FR54: System automatically displays final standings at tournament end.
 * FR55: Top 3 places shown as podium after last round.
 * FR56: Results published automatically for PUBLIC tournaments.
 * FR57: Organizer can choose to publish for PRIVATE tournaments.
 * FR58: Basic meta statistics (hero distribution, win rates).
 */
#[Route('/tournaments/{id}', requirements: ['id' => '\d+'])]
final class TournamentResultsController extends AbstractController
{
    public function __construct(
        private readonly StandingsService $standingsService,
        private readonly TournamentMatchRepository $matchRepository,
        private readonly RegistrationRepository $registrationRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * View final standings (FR54).
     * Available when tournament is completed and results are published.
     */
    #[Route('/final-standings', name: 'tournament_final_standings', methods: ['GET'])]
    public function finalStandings(Tournament $tournament): Response
    {
        // Check if results are viewable
        if (!$this->canViewResults($tournament)) {
            $this->addFlash('warning', $this->translator->trans('flash.warning.results_not_available'));

            return $this->redirectToRoute('tournament_show', ['id' => $tournament->getId()]);
        }

        // Get elimination rounds for bracket display
        $eliminationRounds = [];
        foreach ($tournament->getRounds() as $round) {
            if ($round->isEliminationRound()) {
                $eliminationRounds[] = $round;
            }
        }
        // Sort by round number
        usort($eliminationRounds, fn ($a, $b) => $a->getRoundNumber() <=> $b->getRoundNumber());

        // If tournament has elimination phase, show Swiss standings only
        // (the bracket already shows elimination results)
        $hasEliminationPhase = !empty($eliminationRounds);
        $standings = $hasEliminationPhase
            ? $this->standingsService->calculateSwissStandings($tournament)
            : $this->standingsService->calculateStandings($tournament);

        return $this->render('tournament_results/final_standings.html.twig', [
            'tournament' => $tournament,
            'standings' => $standings,
            'elimination_rounds' => $eliminationRounds,
            'has_elimination_phase' => $hasEliminationPhase,
        ]);
    }

    /**
     * View podium - top 3 players (FR55).
     */
    #[Route('/podium', name: 'tournament_podium', methods: ['GET'])]
    public function podium(Tournament $tournament): Response
    {
        // Check if results are viewable
        if (!$this->canViewResults($tournament)) {
            $this->addFlash('warning', $this->translator->trans('flash.warning.results_not_available'));

            return $this->redirectToRoute('tournament_show', ['id' => $tournament->getId()]);
        }

        $standings = $this->standingsService->calculateStandings($tournament);

        // Get top 3 for podium
        $podium = array_slice($standings, 0, 3);

        return $this->render('tournament_results/podium.html.twig', [
            'tournament' => $tournament,
            'podium' => $podium,
            'full_standings' => $standings,
        ]);
    }

    /**
     * View meta statistics (FR58).
     * Shows hero distribution, faction win rates, etc.
     */
    #[Route('/stats', name: 'tournament_stats', methods: ['GET'])]
    public function stats(Tournament $tournament): Response
    {
        // Check if results are viewable
        if (!$this->canViewResults($tournament)) {
            $this->addFlash('warning', $this->translator->trans('flash.warning.stats_not_available'));

            return $this->redirectToRoute('tournament_show', ['id' => $tournament->getId()]);
        }

        $standings = $this->standingsService->calculateStandings($tournament);
        $metaStats = $this->calculateMetaStatistics($tournament);
        $factionDistribution = $this->calculateFactionDistribution($tournament);
        $heroDistribution = $this->calculateHeroDistribution($tournament);
        $heroWinrates = $this->calculateHeroWinrates($tournament);
        $factionChampions = $this->calculateFactionChampions($standings);

        return $this->render('tournament_results/stats.html.twig', [
            'tournament' => $tournament,
            'standings' => $standings,
            'meta_stats' => $metaStats,
            'faction_distribution' => $factionDistribution,
            'hero_distribution' => $heroDistribution,
            'hero_winrates' => $heroWinrates,
            'faction_champions' => $factionChampions,
        ]);
    }

    /**
     * Publish results for PRIVATE tournaments (FR57).
     * Only the organizer can publish results.
     */
    #[Route('/publish-results', name: 'tournament_publish_results', methods: ['POST'])]
    #[IsGranted('TOURNAMENT_ORGANIZE', subject: 'tournament')]
    public function publishResults(Tournament $tournament): Response
    {
        if (!$tournament->isCompleted()) {
            $this->addFlash('error', $this->translator->trans('flash.error.tournament_not_completed'));

            return $this->redirectToRoute('tournament_dashboard', ['id' => $tournament->getId()]);
        }

        if ($tournament->isResultsPublished()) {
            $this->addFlash('info', $this->translator->trans('flash.info.results_already_published'));

            return $this->redirectToRoute('tournament_final_standings', ['id' => $tournament->getId()]);
        }

        $tournament->publishResults();
        $this->entityManager->flush();

        $this->addFlash('success', $this->translator->trans('flash.success.results_published'));

        return $this->redirectToRoute('tournament_final_standings', ['id' => $tournament->getId()]);
    }

    /**
     * Check if current user can view tournament results.
     */
    private function canViewResults(Tournament $tournament): bool
    {
        // Tournament must be completed
        if (!$tournament->isCompleted()) {
            return false;
        }

        // PUBLIC tournaments: results are automatically published
        if ($tournament->getVisibility() === TournamentVisibility::PUBLIC) {
            return true;
        }

        // PRIVATE tournaments: check if results are published
        if ($tournament->isResultsPublished()) {
            return true;
        }

        // Organizer can always see results of their own tournament
        $user = $this->getUser();
        if ($user !== null && $tournament->getOrganizer() === $user) {
            return true;
        }

        // Admins can always see results of any tournament
        if ($this->isGranted('ROLE_ADMIN')) {
            return true;
        }

        return false;
    }

    /**
     * Calculate meta statistics for the tournament (FR58).
     *
     * @return array{
     *     total_matches: int,
     *     total_games: int,
     *     player_count: int,
     *     rounds_played: int,
     *     avg_games_per_match: float
     * }
     */
    private function calculateMetaStatistics(Tournament $tournament): array
    {
        $matches = $this->matchRepository->findCompletedByTournament($tournament);

        $totalMatches = count($matches);
        $totalGames = 0;

        foreach ($matches as $match) {
            $player1Score = $match->getPlayer1Score();
            $player2Score = $match->getPlayer2Score();
            $totalGames += $player1Score + $player2Score;
        }

        $avgGamesPerMatch = $totalMatches > 0
            ? round($totalGames / $totalMatches, 2)
            : 0.0;

        return [
            'total_matches' => $totalMatches,
            'total_games' => $totalGames,
            'player_count' => $tournament->getRegistrationCount(),
            'rounds_played' => $tournament->getCompletedRoundsCount(),
            'avg_games_per_match' => $avgGamesPerMatch,
        ];
    }

    /**
     * Calculate faction distribution for pie chart.
     *
     * @return array<string, array{count: int, percentage: float, color: string, label: string}>
     */
    private function calculateFactionDistribution(Tournament $tournament): array
    {
        // One entry per deck: both teammates' decks count in 2v2
        $decks = $this->getAllDecks($tournament);
        $total = count($decks);

        if ($total === 0) {
            return [];
        }

        $distribution = [];
        $noFactionCount = 0;

        foreach ($decks as $deck) {
            $faction = $deck['faction'];
            if ($faction === null) {
                $noFactionCount++;
                continue;
            }

            $key = $faction->value;
            if (!isset($distribution[$key])) {
                $distribution[$key] = [
                    'count' => 0,
                    'percentage' => 0,
                    'color' => $faction->getColor(),
                    'label' => $faction->getLabel(),
                ];
            }
            $distribution[$key]['count']++;
        }

        // Calculate percentages
        foreach ($distribution as $key => $data) {
            $distribution[$key]['percentage'] = round(($data['count'] / $total) * 100, 1);
        }

        // Add "non renseigne" if there are registrations without faction
        if ($noFactionCount > 0) {
            $distribution['none'] = [
                'count' => $noFactionCount,
                'percentage' => round(($noFactionCount / $total) * 100, 1),
                'color' => '#9ca3af',
                'label' => 'Non renseigne',
            ];
        }

        // Sort by count descending
        uasort($distribution, fn($a, $b) => $b['count'] <=> $a['count']);

        return $distribution;
    }

    /**
     * Calculate hero distribution for pie chart.
     * Heroes are grouped by faction with color shades.
     *
     * @return array<string, array{count: int, percentage: float, color: string, label: string, image: string|null, faction: string|null}>
     */
    private function calculateHeroDistribution(Tournament $tournament): array
    {
        // One entry per deck: both teammates' decks count in 2v2
        $decks = $this->getAllDecks($tournament);
        $total = count($decks);

        if ($total === 0) {
            return [];
        }

        // Group heroes by faction
        $factionHeroes = [];
        $noHeroCount = 0;

        foreach ($decks as $deck) {
            $hero = $deck['hero'];
            if ($hero === null) {
                $noHeroCount++;
                continue;
            }

            $faction = $deck['faction'];
            $factionKey = $faction ? $faction->value : 'unknown';

            if (!isset($factionHeroes[$factionKey])) {
                $factionHeroes[$factionKey] = [
                    'color' => $faction ? $faction->getColor() : '#6b7280',
                    'heroes' => [],
                    'totalCount' => 0,
                ];
            }

            if (!isset($factionHeroes[$factionKey]['heroes'][$hero])) {
                $factionHeroes[$factionKey]['heroes'][$hero] = [
                    'count' => 0,
                    'label' => $deck['heroLabel'] ?? $hero,
                    'image' => $deck['heroImage'],
                    'faction' => $factionKey,
                ];
            }
            $factionHeroes[$factionKey]['heroes'][$hero]['count']++;
            $factionHeroes[$factionKey]['totalCount']++;
        }

        // Sort factions by total count descending
        uasort($factionHeroes, fn($a, $b) => $b['totalCount'] <=> $a['totalCount']);

        // Build final distribution with color shades
        $distribution = [];
        foreach ($factionHeroes as $factionKey => $factionData) {
            $baseColor = $factionData['color'];
            $heroes = $factionData['heroes'];

            // Sort heroes within faction by count descending
            uasort($heroes, fn($a, $b) => $b['count'] <=> $a['count']);

            // Assign color shades to heroes
            $heroCount = count($heroes);
            $index = 0;
            foreach ($heroes as $heroKey => $heroData) {
                // Calculate shade: from base color to lighter variants
                $shade = $this->adjustColorBrightness($baseColor, $index, $heroCount);

                $distribution[$heroKey] = [
                    'count' => $heroData['count'],
                    'percentage' => round(($heroData['count'] / $total) * 100, 1),
                    'color' => $shade,
                    'label' => $heroData['label'],
                    'image' => $heroData['image'],
                    'faction' => $factionKey,
                ];
                $index++;
            }
        }

        // Add "non renseigne" if there are registrations without hero
        if ($noHeroCount > 0) {
            $distribution['none'] = [
                'count' => $noHeroCount,
                'percentage' => round(($noHeroCount / $total) * 100, 1),
                'color' => '#9ca3af',
                'label' => 'Non renseigne',
                'image' => null,
                'faction' => null,
            ];
        }

        return $distribution;
    }

    /**
     * Adjust color brightness to create shades for heroes of the same faction.
     */
    private function adjustColorBrightness(string $hexColor, int $index, int $total): string
    {
        // Parse hex color
        $hex = ltrim($hexColor, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        // Calculate adjustment factor: first hero is darkest, last is lightest
        // Range from -20% (darker) to +30% (lighter)
        if ($total <= 1) {
            return $hexColor;
        }

        $factor = -0.2 + (0.5 * $index / ($total - 1));

        // Adjust RGB values
        $r = (int) min(255, max(0, $r + ($r * $factor)));
        $g = (int) min(255, max(0, $g + ($g * $factor)));
        $b = (int) min(255, max(0, $b + ($b * $factor)));

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /**
     * Calculate hero win rates from completed matches.
     *
     * @return array<string, array{wins: int, losses: int, draws: int, total: int, winrate: float, color: string, label: string, image: string|null}>
     */
    private function calculateHeroWinrates(Tournament $tournament): array
    {
        $matches = $this->matchRepository->findCompletedByTournament($tournament);

        $heroStats = [];

        foreach ($matches as $match) {
            // Skip BYE matches
            if ($match->isByeMatch()) {
                continue;
            }

            $winner = $match->getWinner();
            $loser = $match->getLoser();

            if ($winner === null) {
                // It's a draw - both sides get a draw
                $this->recordHeroResult($heroStats, $match->getPlayer1(), 'draws');
                $this->recordHeroResult($heroStats, $match->getPlayer2(), 'draws');
            } else {
                $this->recordHeroResult($heroStats, $winner, 'wins');
                $this->recordHeroResult($heroStats, $loser, 'losses');
            }
        }

        // Calculate win rates
        foreach ($heroStats as $hero => $stats) {
            if ($stats['total'] > 0) {
                // Win rate = (wins + 0.5 * draws) / total * 100
                $heroStats[$hero]['winrate'] = round(
                    (($stats['wins'] + 0.5 * $stats['draws']) / $stats['total']) * 100,
                    1
                );
            }
        }

        // Sort by winrate descending
        uasort($heroStats, fn($a, $b) => $b['winrate'] <=> $a['winrate']);

        return $heroStats;
    }

    /**
     * Record a match result for every hero of a side (both teammates' heroes in 2v2).
     *
     * @param array<string, array<string, mixed>> $heroStats
     * @param 'wins'|'losses'|'draws'             $result
     */
    private function recordHeroResult(array &$heroStats, ?Registration $side, string $result): void
    {
        foreach ($side?->getDecks() ?? [] as $deck) {
            $hero = $deck['hero'];
            if ($hero === null) {
                continue;
            }

            if (!isset($heroStats[$hero])) {
                $faction = $deck['faction'];
                $heroStats[$hero] = [
                    'wins' => 0,
                    'losses' => 0,
                    'draws' => 0,
                    'total' => 0,
                    'winrate' => 0.0,
                    'color' => $faction ? $faction->getColor() : '#6b7280',
                    'label' => $deck['heroLabel'] ?? $hero,
                    'image' => $deck['heroImage'],
                ];
            }

            $heroStats[$hero][$result]++;
            $heroStats[$hero]['total']++;
        }
    }

    /**
     * Every deck of the tournament: one per player in 1v1, two per team in 2v2.
     *
     * @return list<array{member: \App\Entity\User, faction: ?\App\Enum\Faction, faction2: ?\App\Enum\Faction, hero: ?string, heroLabel: ?string, heroImage: ?string, decklistUrl: ?string}>
     */
    private function getAllDecks(Tournament $tournament): array
    {
        $decks = [];
        foreach ($this->registrationRepository->findByTournament($tournament) as $registration) {
            foreach ($registration->getDecks() as $deck) {
                $decks[] = $deck;
            }
        }

        return $decks;
    }

    /**
     * Calculate faction champions - best ranked player for each faction.
     *
     * @param array<int, array<string, mixed>> $standings
     *
     * @return array<string, array{rank: int, player: string, faction: \App\Enum\Faction, hero: string|null, heroLabel: string|null, heroImage: string|null, matchPoints: int, wins: int, losses: int}>
     */
    private function calculateFactionChampions(array $standings): array
    {
        $champions = [];

        foreach ($standings as $rank => $entry) {
            /** @var Registration $registration */
            $registration = $entry['registration'];

            // In 2v2 each teammate's deck can make the team champion of its faction
            foreach ($registration->getDecks() as $deck) {
                $faction = $deck['faction'];
                if ($faction === null || isset($champions[$faction->value])) {
                    continue;
                }

                // Only keep the first (best ranked) player for each faction
                $champions[$faction->value] = [
                    'rank' => $rank + 1,
                    'player' => $deck['member']->getDisplayName(),
                    'playerAvatar' => $deck['member']->getAvatar(),
                    'faction' => $faction,
                    'hero' => $deck['hero'],
                    'heroLabel' => $deck['heroLabel'],
                    'heroImage' => $deck['heroImage'],
                    'matchPoints' => $entry['matchPoints'],
                    'wins' => $entry['wins'],
                    'losses' => $entry['losses'],
                ];
            }
        }

        // Sort by rank ascending
        uasort($champions, fn($a, $b) => $a['rank'] <=> $b['rank']);

        return $champions;
    }
}
