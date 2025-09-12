<?php
declare(strict_types=1);

namespace Tests\Generator;

use PHPUnit\Framework\TestCase;
use mschandr\WeightedRandom\Generator\WeightedRandomGenerator;
use mschandr\WeightedRandom\Generator\WeightedBagRandomGenerator;

/**
 * @covers \mschandr\WeightedRandom\Generator\WeightedBagRandomGenerator
 */
final class WeightedBagRandomGeneratorTest extends TestCase
{
    private WeightedBagRandomGenerator $bagGen;

    protected function setUp(): void
    {
        $base = new WeightedRandomGenerator();
        $this->bagGen = new WeightedBagRandomGenerator($base);
    }

    public function testBagSystemRespectsWeights(): void
    {
        $this->bagGen->registerValues([
            'a' => 2,
            'b' => 1,
        ]);

        $results = $this->bagGen->generateMultiple(3);

        $this->assertCount(3, $results);

        $counts = array_count_values($results);
        $this->assertEquals(2, $counts['a'] ?? 0, 'Bag should produce exactly 2x "a"');
        $this->assertEquals(1, $counts['b'] ?? 0, 'Bag should produce exactly 1x "b"');
    }

    public function testBagRefillsAfterExhaustion(): void
    {
        $this->bagGen->registerValues([
            'x' => 2,
            'y' => 1,
        ]);

        // First draw cycle
        $results1 = $this->bagGen->generateMultiple(3);

        // Second draw cycle (bag should refill)
        $results2 = $this->bagGen->generateMultiple(3);

        $this->assertCount(3, $results1);
        $this->assertCount(3, $results2);

        $counts1 = array_count_values($results1);
        $counts2 = array_count_values($results2);

        $this->assertEquals(2, $counts1['x'] ?? 0);
        $this->assertEquals(1, $counts1['y'] ?? 0);

        $this->as
