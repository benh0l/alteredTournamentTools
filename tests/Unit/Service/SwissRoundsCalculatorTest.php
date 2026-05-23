<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DTO\SwissRoundsSuggestion;
use App\Enum\MatchFormat;
use App\Service\SwissRoundsCalculator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Service\SwissRoundsCalculator
 */
final class SwissRoundsCalculatorTest extends TestCase
{
    private SwissRoundsCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new SwissRoundsCalculator();
    }

    // ====== calculateBaseRounds Tests ======

    /**
     * @dataProvider playerCountProvider
     */
    public function testCalculateBaseRoundsWithVariousPlayerCounts(int $playerCount, int $expectedRounds): void
    {
        $result = $this->calculator->calculateBaseRounds($playerCount);
        $this->assertSame($expectedRounds, $result);
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function playerCountProvider(): array
    {
        return [
            '4 players = 2 rounds' => [4, 2],
            '5 players = 3 rounds' => [5, 3],
            '6 players = 3 rounds' => [6, 3],
            '7 players = 3 rounds' => [7, 3],
            '8 players = 3 rounds' => [8, 3],
            '9 players = 4 rounds' => [9, 4],
            '16 players = 4 rounds' => [16, 4],
            '17 players = 5 rounds' => [17, 5],
            '32 players = 5 rounds' => [32, 5],
            '33 players = 6 rounds' => [33, 6],
            '64 players = 6 rounds' => [64, 6],
            '65 players = 7 rounds' => [65, 7],
            '128 players = 7 rounds' => [128, 7],
        ];
    }

    public function testCalculateBaseRoundsWithLessThanMinimumPlayers(): void
    {
        // Below minimum should return minimum rounds
        $this->assertSame(2, $this->calculator->calculateBaseRounds(2));
        $this->assertSame(2, $this->calculator->calculateBaseRounds(3));
    }

    public function testCalculateBaseRoundsRespectsMaximumRounds(): void
    {
        // Very large player count should cap at max rounds
        $result = $this->calculator->calculateBaseRounds(2048);
        $this->assertSame(10, $result);
    }

    // ====== calculateWithBO1Rule Tests ======

    /**
     * @dataProvider bo1PlayerCountProvider
     */
    public function testCalculateWithBO1RuleAddsExtraRound(int $playerCount, int $expectedBO3Rounds, int $expectedBO1Rounds): void
    {
        $bo3Result = $this->calculator->calculateWithBO1Rule($playerCount, MatchFormat::BO3);
        $bo1Result = $this->calculator->calculateWithBO1Rule($playerCount, MatchFormat::BO1);

        $this->assertSame($expectedBO3Rounds, $bo3Result);
        $this->assertSame($expectedBO1Rounds, $bo1Result);
        $this->assertSame($bo3Result + 1, $bo1Result);
    }

    /**
     * @return array<string, array{int, int, int}>
     */
    public static function bo1PlayerCountProvider(): array
    {
        return [
            '8 players: BO3=3, BO1=4' => [8, 3, 4],
            '16 players: BO3=4, BO1=5' => [16, 4, 5],
            '32 players: BO3=5, BO1=6' => [32, 5, 6],
            '64 players: BO3=6, BO1=7' => [64, 6, 7],
        ];
    }

    public function testCalculateWithBO1RuleRespectsMaximum(): void
    {
        // At max rounds (10), BO1 should not exceed 10
        $result = $this->calculator->calculateWithBO1Rule(2048, MatchFormat::BO1);
        $this->assertSame(10, $result);
    }

    public function testCalculateWithBO3ReturnsBaseRounds(): void
    {
        $baseRounds = $this->calculator->calculateBaseRounds(16);
        $bo3Rounds = $this->calculator->calculateWithBO1Rule(16, MatchFormat::BO3);

        $this->assertSame($baseRounds, $bo3Rounds);
    }

    // ====== getSuggestion Tests ======

    public function testGetSuggestionReturnsCorrectDTO(): void
    {
        $suggestion = $this->calculator->getSuggestion(16, MatchFormat::BO3);

        $this->assertInstanceOf(SwissRoundsSuggestion::class, $suggestion);
        $this->assertSame(4, $suggestion->baseRounds);
        $this->assertSame(4, $suggestion->suggestedRounds);
        $this->assertFalse($suggestion->isBO1Adjusted);
        $this->assertStringContainsString('16', $suggestion->explanation);
        $this->assertStringContainsString('4', $suggestion->explanation);
    }

    public function testGetSuggestionWithBO1ShowsAdjustment(): void
    {
        $suggestion = $this->calculator->getSuggestion(16, MatchFormat::BO1);

        $this->assertSame(4, $suggestion->baseRounds);
        $this->assertSame(5, $suggestion->suggestedRounds);
        $this->assertTrue($suggestion->isBO1Adjusted);
        $this->assertStringContainsString('BO1', $suggestion->explanation);
        $this->assertStringContainsString('16', $suggestion->explanation);
    }

    public function testGetSuggestionExplanationFormat(): void
    {
        $suggestion = $this->calculator->getSuggestion(32, MatchFormat::BO3);

        $this->assertStringContainsString('rondes', $suggestion->explanation);
        $this->assertStringContainsString('joueurs', $suggestion->explanation);
        $this->assertStringNotContainsString('BO1', $suggestion->explanation);
    }

    public function testGetSuggestionBO1ExplanationIncludesRule(): void
    {
        $suggestion = $this->calculator->getSuggestion(32, MatchFormat::BO1);

        $this->assertStringContainsString('+1 ronde pour BO1', $suggestion->explanation);
        $this->assertStringContainsString('Altered TCG', $suggestion->explanation);
    }

    // ====== isValidPlayerCount Tests ======

    /**
     * @dataProvider validPlayerCountProvider
     */
    public function testIsValidPlayerCountWithValidCounts(int $playerCount): void
    {
        $this->assertTrue($this->calculator->isValidPlayerCount($playerCount));
    }

    /**
     * @return array<string, array{int}>
     */
    public static function validPlayerCountProvider(): array
    {
        return [
            'minimum (4)' => [4],
            'typical small (8)' => [8],
            'typical medium (16)' => [16],
            'typical large (32)' => [32],
            'large (64)' => [64],
            'maximum (128)' => [128],
        ];
    }

    /**
     * @dataProvider invalidPlayerCountProvider
     */
    public function testIsValidPlayerCountWithInvalidCounts(int $playerCount): void
    {
        $this->assertFalse($this->calculator->isValidPlayerCount($playerCount));
    }

    /**
     * @return array<string, array{int}>
     */
    public static function invalidPlayerCountProvider(): array
    {
        return [
            'zero' => [0],
            'one' => [1],
            'two' => [2],
            'three' => [3],
            'above max (257)' => [257],
            'way above max (512)' => [512],
            'negative' => [-1],
        ];
    }

    // ====== getMinPlayers / getMaxPlayers Tests ======

    public function testGetMinPlayersReturnsExpectedValue(): void
    {
        $this->assertSame(4, $this->calculator->getMinPlayers());
    }

    public function testGetMaxPlayersReturnsExpectedValue(): void
    {
        $this->assertSame(256, $this->calculator->getMaxPlayers());
    }

    public function testMinMaxPlayersAreConsistent(): void
    {
        $min = $this->calculator->getMinPlayers();
        $max = $this->calculator->getMaxPlayers();

        $this->assertTrue($this->calculator->isValidPlayerCount($min));
        $this->assertTrue($this->calculator->isValidPlayerCount($max));
        $this->assertFalse($this->calculator->isValidPlayerCount($min - 1));
        $this->assertFalse($this->calculator->isValidPlayerCount($max + 1));
    }

    // ====== Edge Cases ======

    public function testCalculationConsistencyAcrossMethods(): void
    {
        $playerCount = 24;

        $baseRounds = $this->calculator->calculateBaseRounds($playerCount);
        $bo3Rounds = $this->calculator->calculateWithBO1Rule($playerCount, MatchFormat::BO3);
        $suggestion = $this->calculator->getSuggestion($playerCount, MatchFormat::BO3);

        $this->assertSame($baseRounds, $bo3Rounds);
        $this->assertSame($baseRounds, $suggestion->baseRounds);
        $this->assertSame($baseRounds, $suggestion->suggestedRounds);
    }

    public function testPowerOfTwoPlayersCalculation(): void
    {
        // Powers of 2 should have clean log2 results
        $this->assertSame(3, $this->calculator->calculateBaseRounds(8));
        $this->assertSame(4, $this->calculator->calculateBaseRounds(16));
        $this->assertSame(5, $this->calculator->calculateBaseRounds(32));
        $this->assertSame(6, $this->calculator->calculateBaseRounds(64));
        $this->assertSame(7, $this->calculator->calculateBaseRounds(128));
    }

    public function testSuggestionToArrayFormat(): void
    {
        $suggestion = $this->calculator->getSuggestion(16, MatchFormat::BO1);
        $array = $suggestion->toArray();

        $this->assertArrayHasKey('baseRounds', $array);
        $this->assertArrayHasKey('suggestedRounds', $array);
        $this->assertArrayHasKey('isBO1Adjusted', $array);
        $this->assertArrayHasKey('explanation', $array);

        $this->assertSame(4, $array['baseRounds']);
        $this->assertSame(5, $array['suggestedRounds']);
        $this->assertTrue($array['isBO1Adjusted']);
        $this->assertIsString($array['explanation']);
    }
}
