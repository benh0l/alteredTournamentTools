<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;

/**
 * Sample unit test demonstrating PHPUnit configuration.
 */
final class ExampleServiceTest extends TestCase
{
    public function testAdditionWorks(): void
    {
        $result = 1 + 1;

        $this->assertSame(2, $result);
    }

    public function testStringContains(): void
    {
        $haystack = 'Hello, Altered Tournament Tools!';

        $this->assertStringContainsString('Altered', $haystack);
    }

    /**
     * @dataProvider additionProvider
     */
    public function testAdditionWithDataProvider(int $a, int $b, int $expected): void
    {
        $this->assertSame($expected, $a + $b);
    }

    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function additionProvider(): iterable
    {
        yield 'positive numbers' => [1, 2, 3];
        yield 'negative numbers' => [-1, -2, -3];
        yield 'mixed numbers' => [-1, 2, 1];
        yield 'zeros' => [0, 0, 0];
    }
}
