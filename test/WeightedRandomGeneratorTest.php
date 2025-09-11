<?php
declare(strict_types=1);

use Assert\AssertionFailedException;
use mschandr\WeightedRandom\WeightedRandom;
use mschandr\WeightedRandom\WeightedRandomGenerator;
use mschandr\WeightedRandom\WeightedValue;
use PHPUnit\Framework\TestCase;
use Random\RandomException;

/**
 * Class WeightedRandomGeneratorTest
 */
final class WeightedRandomGeneratorTest extends TestCase
{
    /**
     * @var WeightedRandomGenerator
     */
    private WeightedRandomGenerator $generator;

    /**
     *
     */
    public function setUp(): void
    {
        $this->generator = new WeightedRandomGenerator();
    }

    /**
     * Test registering values of multiple types.
     */
    public function testRegisterValue(): void
    {
        $values = [
            123,
            '123',
            [1,2,3],
            new \stdClass(),
            false,
            null
        ];

        foreach ($values as $value) {
            $this->generator->registerValue($value);
        }

        $sample = iterator_to_array(
            $this->generator->generateMultipleWithoutDuplicates(count($values))
        );

        $this->assertEqualsCanonicalizing(
            $this->normalizeForComparison($values),
            $this->normalizeForComparison($sample)
        );
    }


    /**
     * Test registering a value with the WeightedValue model.
     */
    public function testRegisterValueWithModel()
    {
        $weightedValue = new WeightedValue('test', 3);
        $this->generator->registerWeightedValue($weightedValue);

        $retrievedWeightedValue = $this->generator->getWeightedValue($weightedValue->getValue());

        $this->assertEquals($weightedValue->getArrayCopy(), $retrievedWeightedValue->getArrayCopy());
    }

    /**
     * Test registering multiple values and weights via the registerValue method.
     */
    public function testRegisterValues()
    {
        $values = ['foobar' => 10, 'foo' => 20, 'bar' => 30];
        $this->generator->registerValues($values);

        foreach ($this->generator->getWeightedValues() as $weightedValue)
        {
            $this->assertArrayHasKey($weightedValue->getValue(), $values);
            $this->assertEquals($values[$weightedValue->getValue()], $weightedValue->getWeight());
        }
    }

    /**
     * Test registering a value with a weight of 0.
     */
    public function testRegisterValueWeightZero()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->generator->registerValue('test', 0);
    }

    /**
     * Test registering a value with a weight of 0, using the registerValues method.
     */
    public function testRegisterValuesWeightZero()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->generator->registerValues(['test' => 0]);
    }

    /**
     * Remove a registered value.
     */
    public function testRemoveValue()
    {
        $value = new \stdClass();
        $this->generator->registerValue($value);
        $this->generator->removeValue($value);

        $registeredValues = iterator_to_array($this->generator->getWeightedValues());
        $this->assertEquals(0, count($registeredValues));
    }

    /**
     * Try to remove a unregistered value.
     */
    public function testRemoveUnregisteredValue()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->generator->removeValue(new \stdClass());
    }

    /**
     * Try to remove a registered weighted value.
     */
    public function testRemoveWeightedValue()
    {
        $value = new \stdClass();
        $weightedValue = new WeightedValue($value, 2);
        $this->generator->registerWeightedValue($weightedValue);
        $this->generator->removeWeightedValue($weightedValue);

        $registeredValues = iterator_to_array($this->generator->getWeightedValues());
        $this->assertEquals(0, count($registeredValues));
    }

    /**
     * Test that generate multiple can and will return the same value multiple times.
     */
    public function testGenerateMultipleDuplicateValues()
    {
        $registeredValue = new \stdClass();
        $this->generator->registerValue($registeredValue);

        $values = iterator_to_array($this->generator->generateMultiple(10));

        $this->assertCount(10, $values);
        foreach ($values as $value)
        {
            $this->assertEquals($value, $registeredValue);
        }
    }

    /**
     * Test the generateMultipleWithoutDuplicates for removing duplicate items from the results.
     */
    public function testGenerateMultipleNoDuplicateValues(): void
    {
        $registeredValues = [
            '1' => 1,
            '2' => 1,
            '3' => 1,
        ];
        $this->generator->registerValues($registeredValues);

        // Mock RNG to force some duplicate draws
        $mockRandomNumberGenerator = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['__invoke'])
            ->getMock();

        $mockRandomNumberGenerator->method('__invoke')
            ->will($this->onConsecutiveCalls(1, 1, 2, 3));

        $this->generator->setRandomNumberGenerator($mockRandomNumberGenerator);

        $sample = iterator_to_array(
            $this->generator->generateMultipleWithoutDuplicates(count($registeredValues))
        );

        $this->assertEqualsCanonicalizing(
            $this->normalizeForComparison(array_keys($registeredValues)),
            $this->normalizeForComparison($sample)
        );
    }

    /**
     * Test getting a weighted value for a non-existing value,
     * which should result in an invalid argument exception
     */
    public function testGetNonExistingWeightedValue()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->generator->getWeightedValue(new \stdClass());
    }

    /**
     * @throws RandomException
     */
    public function testPickKeySinglePositiveAlwaysChosen(): void
    {
        $key = WeightedRandom::pickKey(['a' => 0, 'b' => 10, 'c' => 0]);
        $this->assertSame('b', $key);
    }

    /**
     * @throws RandomException
     */
    public function testPickKeyDoesNotMutateInput(): void
    {
        $weights = ['a' => 1, 'b' => 2];
        $copy    = $weights;
        WeightedRandom::pickKey($weights);
        $this->assertSame($copy, $weights, 'Input weight array must not be mutated');
    }

    /**
     * @return void
     * @throws RandomException
     */
    public function testPickKeyFallbackOnEmptyOrAllZero(): void
    {
        $this->assertSame('', WeightedRandom::pickKey([]));
        $this->assertSame('a', WeightedRandom::pickKey(['a' => 0, 'b' => 0]));
    }

    /**
     * @throws RandomException
     */
    public function testPickKeySanitizesNegativesAndNonNumerics(): void
    {
        // negatives => 0; non-numeric => 0; at least one positive ensures a valid pick
        $key = WeightedRandom::pickKey(['bad' => -5, 'weird' => 'x', 'ok' => 3]);
        $this->assertSame('ok', $key);
    }

    /**
     * @return void
     * @requires PHP >= 8.2
     */
    public function testSeededIsDeterministicPerSeedAndNamespace(): void
    {
        $w = ['a' => 1, 'b' => 2, 'c' => 3];

        $k1 = WeightedRandom::pickKeySeeded($w, 1234, 'ns.alpha');
        $k2 = WeightedRandom::pickKeySeeded($w, 1234, 'ns.alpha');
        $this->assertSame($k1, $k2, 'Same seed+namespace should repeat exactly');

        $k3 = WeightedRandom::pickKeySeeded($w, 1234, 'ns.beta');
        // Not asserting inequality (nonspecific), just making sure call works & returns a valid key
        $this->assertArrayHasKey($k3, $w);
    }

    /**
     * Seeded streams should remain independent of call order.
     *
     * @return void
     *
     * @requires PHP >= 8.2
     */
    public function testSeededStreamsAreIndependentOfCallOrder(): void
    {
        $weights = ['a' => 10, 'b' => 5, 'c' => 1];

        // First pass: alpha then beta
        $alpha1 = WeightedRandom::pickKeySeeded($weights, 999, 'stream.alpha');
        $beta1  = WeightedRandom::pickKeySeeded($weights, 999, 'stream.beta');

        // Second pass: beta then alpha (reversed order)
        $beta2  = WeightedRandom::pickKeySeeded($weights, 999, 'stream.beta');
        $alpha2 = WeightedRandom::pickKeySeeded($weights, 999, 'stream.alpha');

        $this->assertSame($alpha1, $alpha2, 'Alpha stream should be independent of Beta stream');
        $this->assertSame($beta1,  $beta2,  'Beta stream should be independent of Alpha stream');
    }

    /**
     * @return void
     * @requires PHP >= 8.2
     */
    public function testSeededHandlesLargeTotals(): void
    {
        $w = ['x' => 1_000_000, 'y' => 2_000_000, 'z' => 3_000_000];
        $k = WeightedRandom::pickKeySeeded($w, 42, 'large');
        $this->assertArrayHasKey($k, $w);
    }

    /**
     * @return void
     * @requires PHP >= 8.2
     */
    public function testSeededFallbackOnAllZeroOrNegative(): void
    {
        $this->assertSame('a', WeightedRandom::pickKeySeeded(['a'=>0, 'b'=>0], 7, 'z'));
        $this->assertSame('a', WeightedRandom::pickKeySeeded(['a'=>-1, 'b'=>-2], 7, 'z'));
    }

    /**
     * @return void
     */
    public function testNormalizeWeights(): void
    {
        $gen = new WeightedRandomGenerator();
        $gen->registerValues([
            'apple'  => 70,
            'banana' => 30,
        ]);

        $normalized = $gen->normalizeWeights();

        $this->assertEqualsWithDelta(1.0, array_sum($normalized), 0.0001,
            'Normalized weights should sum to 1.0');
        $this->assertEqualsWithDelta(0.7, $normalized[array_key_first($normalized)], 0.0001);
        $this->assertEqualsWithDelta(0.3, $normalized[array_key_last($normalized)], 0.0001);
    }

    /**
     * @return void
     * @throws RandomException
     * @throws AssertionFailedException
     */
    public function testRegisterGroupReturnsOnlyGroupMembers(): void
    {
        $groupMembers = ['wolf', 'bear', 'lion'];

        $this->generator->registerGroup($groupMembers, 5);

        // Draw multiple samples
        $results = iterator_to_array($this->generator->generateMultiple(50));

        foreach ($results as $result) {
            $this->assertContains(
                $result,
                $groupMembers,
                "Generated value {$result} was not in the registered group"
            );
        }
    }

    /**
     * @return void
     */
    public function testRegisterGroupEmptyThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->generator->registerGroup([], 5);
    }

    /**
     * @return void
     * @throws AssertionFailedException
     * @throws RandomException
     */
    public function testMultipleGroupsWorkIndependently(): void
    {
        $animals = ['wolf', 'bear', 'lion'];
        $dragons = ['dragon'];

        $this->generator
            ->registerGroup($animals, 5)
            ->registerGroup($dragons, 1);

        $results = iterator_to_array($this->generator->generateMultiple(100));

        foreach ($results as $result) {
            $this->assertTrue(
                in_array($result, $animals, true) || in_array($result, $dragons, true),
                "Generated value {$result} not in any group"
            );
        }
    }

    /**
     * @return void
     */
    public function testGetProbability(): void
    {
        $gen = new WeightedRandomGenerator();
        $gen->registerValues([
            'common' => 7,
            'rare'   => 3,
        ]);

        $this->assertEqualsWithDelta(0.7, $gen->getProbability('common'), 0.0001);
        $this->assertEqualsWithDelta(0.3, $gen->getProbability('rare'), 0.0001);

        $this->expectException(\InvalidArgumentException::class);
        $gen->getProbability('not-registered');
    }

    /**
     *  This is a helper class to compare objects and types of objects
     *  within an array.
     */
    private function normalizeForComparison(array $arr): array
    {
        return array_map(function ($item) {
            if (is_object($item)) {
                return 'object:' . spl_object_id($item);
            }
            if (is_array($item)) {
                return 'array:' . md5(serialize($item));
            }
            return var_export($item, true); // scalars/null
        }, $arr);
    }

    /**
     * @return void
     */
    public function testBagSystemRespectsWeights(): void
    {
        $this->generator->registerValues([
            'a' => 2,
            'b' => 1,
        ]);

        $results = $this->generator->generateMultipleFromBag(3);

        $this->assertCount(3, $results);

        $counts = array_count_values($results);
        $this->assertEquals(2, $counts['a'] ?? 0, 'Bag should produce exactly 2x "a"');
        $this->assertEquals(1, $counts['b'] ?? 0, 'Bag should produce exactly 1x "b"');
    }

    public function testBagRefillsAfterExhaustion(): void
    {
        $this->generator->registerValues([
            'x' => 2,
            'y' => 1,
        ]);

        // First full bag draw
        $results1 = $this->generator->generateMultipleFromBag(3);

        // Second full bag draw (should refill)
        $results2 = $this->generator->generateMultipleFromBag(3);

        $this->assertCount(3, $results1);
        $this->assertCount(3, $results2);

        $counts1 = array_count_values($results1);
        $counts2 = array_count_values($results2);

        $this->assertEquals(2, $counts1['x'] ?? 0);
        $this->assertEquals(1, $counts1['y'] ?? 0);

        $this->assertEquals(2, $counts2['x'] ?? 0);
        $this->assertEquals(1, $counts2['y'] ?? 0);
    }

    public function testBagWorksWithGroups(): void
    {
        $animals = ['wolf', 'bear', 'lion'];
        $this->generator->registerGroup($animals, 3);

        $results = $this->generator->generateMultipleFromBag(3);

        $this->assertCount(3, $results);

        foreach ($results as $r) {
            $this->assertContains($r, $animals, "Result {$r} was not a valid group member");
        }
    }

    public function testBagThrowsWhenNoValuesRegistered(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->generator->generateFromBag();
    }


}
