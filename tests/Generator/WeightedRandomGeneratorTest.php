<?php
declare(strict_types=1);

namespace Tests\Generator;

use mschandr\WeightedRandom\Generator\WeightedRandomGenerator;
use mschandr\WeightedRandom\Value\WeightedGroup;
use mschandr\WeightedRandom\Value\WeightedValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\mschandr\WeightedRandom\Generator\WeightedRandomGenerator::class)]
#[CoversClass(\mschandr\WeightedRandom\Generator\WeightedBagRandomGenerator::class)]
#[CoversClass(\mschandr\WeightedRandom\Value\WeightedValue::class)]
#[CoversClass(\mschandr\WeightedRandom\Value\WeightedGroup::class)]
final class WeightedRandomGeneratorTest extends TestCase
{

    /**
     * @return void
     */
    public function testRegisterValueStoresWeight(): void
    {
        $gen = new WeightedRandomGenerator();
        $gen->registerValue('apple', 2.5);

        $values = iterator_to_array($gen->getWeightedValues());
        $this->assertCount(1, $values);
        $this->assertInstanceOf(WeightedValue::class, $values[0]);
        $this->assertSame('apple', $values[0]->getValue());
        $this->assertSame(2.5, $values[0]->getWeight());
    }

    /**
     * @return void
     */
    public function testRegisterValueThrowsForNonPositiveWeight(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $gen = new WeightedRandomGenerator();
        $gen->registerValue('apple', 0);
    }

    /**
     * @return void
     */
    public function testRegisterValuesStoresMultiple(): void
    {
        $gen = new WeightedRandomGenerator();
        $gen->registerValues(['a' => 1, 'b' => 2]);
        $values = iterator_to_array($gen->getWeightedValues());
        $this->assertCount(2, $values);
    }

    /**
     * @return void
     */
    public function testRegisterValuesThrowsForNonNumericWeight(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $gen = new WeightedRandomGenerator();
        $gen->registerValues(['a' => 'foo']);
    }

    /**
     * @return void
     */
    public function testRegisterGroupNormal(): void
    {
        $gen = new WeightedRandomGenerator();
        $gen->registerGroup(['x', 'y'], 3.0);

        $values = iterator_to_array($gen->getWeightedValues());
        $this->assertInstanceOf(WeightedGroup::class, $values[0]->getValue());
        $this->assertSame(3.0, $values[0]->getWeight());
    }

    /**
     * @return void
     */
    public function testRegisterGroupThrowsForZeroWeight(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new WeightedRandomGenerator())->registerGroup(['x'], 0.0);
    }

    /**
     * @return void
     */
    public function testRegisterGroupThrowsForEmptyGroup(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new WeightedRandomGenerator())->registerGroup([], 1.0);
    }

    /**
     * @return void
     */
    public function testGenerateThrowsIfNoValuesRegistered(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new WeightedRandomGenerator())->generate();
    }

    /**
     * @return void
     * @throws \ReflectionException
     */
    public function testGenerateThrowsIfTotalWeightZero(): void
    {
        $this->expectException(\RuntimeException::class);
        $gen = new WeightedRandomGenerator();

        // Hack private weights directly to force zero sum
        $ref = new \ReflectionProperty($gen, 'weights');
        $ref->setAccessible(true);
        $ref->setValue($gen, [0.0]);

        $valRef = new \ReflectionProperty($gen, 'values');
        $valRef->setAccessible(true);
        $valRef->setValue($gen, ['dummy']);

        $gen->generate();
    }

    /**
     * @return void
     */
    public function testGenerateReturnsRegisteredValue(): void
    {
        $gen = new WeightedRandomGenerator();
        $gen->registerValue('apple', 1.0)->registerValue('banana', 1.0);
        $result = $gen->generate();
        $this->assertContains($result, ['apple', 'banana']);
    }

    /**
     * @return void
     */
    public function testGenerateReturnsFromGroup(): void
    {
        $gen = new WeightedRandomGenerator();
        $gen->registerGroup(['x', 'y', 'z'], 1.0);
        $result = $gen->generate();
        $this->assertContains($result, ['x', 'y', 'z']);
    }

    /**
     * @return void
     */
    public function testGenerateMultipleNormal(): void
    {
        $gen = new WeightedRandomGenerator();
        $gen->registerValue('apple', 1.0);
        $results = iterator_to_array($gen->generateMultiple(3));
        $this->assertCount(3, $results);
    }

    /**
     * @return void
     */
    public function testGenerateMultipleThrowsForZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $gen = new WeightedRandomGenerator();
        $gen->registerValue('apple', 1.0);
        iterator_to_array($gen->generateMultiple(0));
    }

    /**
     * @return void
     */
    public function testGenerateMultipleWithoutDuplicatesNormal(): void
    {
        $gen = new WeightedRandomGenerator();
        $gen->registerValues(['a' => 1.0, 'b' => 1.0]);
        $results = iterator_to_array($gen->generateMultipleWithoutDuplicates(2));
        $this->assertCount(2, $results);
        $this->assertNotSame($results[0], $results[1]);
    }

    /**
     * @return void
     */
    public function testGenerateMultipleWithoutDuplicatesThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $gen = new WeightedRandomGenerator();
        $gen->registerValue('apple', 1.0);
        iterator_to_array($gen->generateMultipleWithoutDuplicates(2));
    }

    /**
     * @return void
     * @throws \ReflectionException
     */
    public function testGetWeightedValuesSkipsUnweighted(): void
    {
        $gen = new WeightedRandomGenerator();

        // Inject value with no weight
        $valRef = new \ReflectionProperty($gen, 'values');
        $valRef->setAccessible(true);
        $valRef->setValue($gen, ['apple']);

        $weightsRef = new \ReflectionProperty($gen, 'weights');
        $weightsRef->setAccessible(true);
        $weightsRef->setValue($gen, []);

        $values = iterator_to_array($gen->getWeightedValues());
        $this->assertCount(0, $values);
    }

    /**
     * @return void
     */
    public function testNormalizeWeightsNormal(): void
    {
        $gen = new WeightedRandomGenerator();
        $gen->registerValues(['a' => 1, 'b' => 3]);
        $normalized = $gen->normalizeWeights();
        $this->assertEqualsWithDelta(0.25, array_values($normalized)[0], 0.001);
        $this->assertEqualsWithDelta(0.75, array_values($normalized)[1], 0.001);
    }

    /**
     * @return void
     * @throws \ReflectionException
     */
    public function testNormalizeWeightsThrowsOnZeroTotal(): void
    {
        $this->expectException(\RuntimeException::class);
        $gen = new WeightedRandomGenerator();

        // hack zero total
        $weightsRef = new \ReflectionProperty($gen, 'weights');
        $weightsRef->setAccessible(true);
        $weightsRef->setValue($gen, []);
        $gen->normalizeWeights();
    }

    /**
     * @return void
     */
    public function testGetProbabilityNormal(): void
    {
        $gen = new WeightedRandomGenerator();
        $gen->registerValues(['a' => 1, 'b' => 3]);
        $this->assertEqualsWithDelta(0.25, $gen->getProbability('a'), 0.001);
    }

    /**
     * @return void
     */
    public function testGetProbabilityThrowsIfNotRegistered(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $gen = new WeightedRandomGenerator();
        $gen->registerValue('apple', 1.0);
        $gen->getProbability('banana');
    }

    /**
     * @return void
     * @throws \ReflectionException
     */
    public function testGenerateFallsBackToLastValue(): void
    {
        $gen = new WeightedRandomGenerator();

        // Register two values with skewed weights
        $gen->registerValue('apple', 0.1);
        $gen->registerValue('banana', 0.1);

        // Force $randomValue large so it doesn't trigger inside foreach
        $ref = new \ReflectionProperty($gen, 'randomNumberGenerator');
        $ref->setAccessible(true);
        $ref->setValue($gen, function () {
            return PHP_INT_MAX; // absurdly high
        });

        $result = $gen->generate();
        $this->assertContains($result, ['apple', 'banana']);
    }

    /**
     * @return void
     * @throws \ReflectionException
     */
    public function testGenerateMultipleWithoutDuplicatesTriggersContinue(): void
    {
        $gen = new WeightedRandomGenerator();
        $gen->registerValues(['a' => 1.0, 'b' => 1.0]);

        // Force RNG always to pick index 0 → duplicates → triggers continue;
        $ref = new \ReflectionProperty($gen, 'randomNumberGenerator');
        $ref->setAccessible(true);
        $ref->setValue($gen, function() { return 0; });

        $iter = $gen->generateMultipleWithoutDuplicates(2);

        // Just check the first yield is one of the registered values
        $first = $iter->current();
        $this->assertContains($first, ['a', 'b']);
    }

    public function testGenerateMultipleWithoutDuplicatesContinueBranch(): void
    {
        $gen = new WeightedRandomGenerator();
        $gen->registerValues(['a' => 1.0, 'b' => 1.0]);

        // Force RNG always to pick index 0 ("a"), to bias toward duplicates
        $ref = new \ReflectionProperty($gen, 'randomNumberGenerator');
        $ref->setAccessible(true);
        $ref->setValue($gen, function() { return 0; });

        $iter = $gen->generateMultipleWithoutDuplicates(1);

        // We only care that the generator yields something valid.
        // Coverage will record that the `continue;` branch was hit.
        $first = $iter->current();
        $this->assertContains($first, ['a', 'b']);
    }

    public function testGenerateMultipleWithoutDuplicatesThrowsAfterMaxAttempts(): void
    {
        $gen = new WeightedRandomGenerator();
        $gen->registerValues(['a' => 1.0, 'b' => 1.0]);
        $gen->setMaxAttemptsFactor(1);

        // Force RNG to always return "a"
        $ref = new \ReflectionProperty($gen, 'randomNumberGenerator');
        $ref->setAccessible(true);
        $ref->setValue($gen, fn() => 0);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to generate enough unique values without duplicates.');

        // Force iteration until exception is triggered
        iterator_to_array($gen->generateMultipleWithoutDuplicates(2), false);
    }

    public function testRegisterValueTwiceUpdatesWeight(): void
    {
        $gen = new WeightedRandomGenerator();
        $gen->registerValue('apple', 1.0);
        $gen->registerValue('apple', 3.0); // Update weight

        $values = iterator_to_array($gen->getWeightedValues());
        $this->assertCount(1, $values);
        $this->assertSame('apple', $values[0]->getValue());
        $this->assertSame(3.0, $values[0]->getWeight());
    }

    // --- Distribution Introspection Tests ---

    public function testGetDistributionReturnsValueToProbabilityMapping(): void
    {
        $gen = new WeightedRandomGenerator();
        $gen->registerValues(['a' => 1.0, 'b' => 2.0, 'c' => 1.0]);

        $dist = $gen->getDistribution();

        $this->assertEqualsWithDelta(0.25, $dist['a'], 0.001);
        $this->assertEqualsWithDelta(0.50, $dist['b'], 0.001);
        $this->assertEqualsWithDelta(0.25, $dist['c'], 0.001);
    }

    public function testGetDistributionHandlesGroups(): void
    {
        $gen = new WeightedRandomGenerator();
        $gen->registerValue('a', 1.0);
        $gen->registerGroup(['x', 'y'], 2.0);

        $dist = $gen->getDistribution();

        // 'a' has weight 1.0 out of 3.0 total = 1/3
        $this->assertEqualsWithDelta(1/3, $dist['a'], 0.001);
        // Group has weight 2.0, split between x and y = 1/3 each
        $this->assertEqualsWithDelta(1/3, $dist['x'], 0.001);
        $this->assertEqualsWithDelta(1/3, $dist['y'], 0.001);
    }

    public function testGetEntropyReturnsZeroForSingleValue(): void
    {
        $gen = new WeightedRandomGenerator();
        $gen->registerValue('only', 1.0);

        $entropy = $gen->getEntropy();

        $this->assertSame(0.0, $entropy);
    }

    public function testGetEntropyReturnsMaxForUniformDistribution(): void
    {
        $gen = new WeightedRandomGenerator();
        $gen->registerValues(['a' => 1.0, 'b' => 1.0, 'c' => 1.0, 'd' => 1.0]);

        $entropy = $gen->getEntropy();

        // Max entropy for 4 values is log2(4) = 2.0
        $this->assertEqualsWithDelta(2.0, $entropy, 0.001);
    }

    public function testGetExpectedValueForNumericValues(): void
    {
        $gen = new WeightedRandomGenerator();
        $gen->registerValues([1 => 1.0, 2 => 1.0, 3 => 1.0]);

        $expected = $gen->getExpectedValue();

        // (1*1 + 2*1 + 3*1) / 3 = 6/3 = 2.0
        $this->assertEqualsWithDelta(2.0, $expected, 0.001);
    }

    public function testGetExpectedValueReturnsNullForNonNumericValues(): void
    {
        $gen = new WeightedRandomGenerator();
        $gen->registerValues(['a' => 1.0, 'b' => 2.0]);

        $expected = $gen->getExpectedValue();

        $this->assertNull($expected);
    }

    public function testGetVarianceForNumericValues(): void
    {
        $gen = new WeightedRandomGenerator();
        $gen->registerValues([1 => 1.0, 2 => 1.0, 3 => 1.0]);

        $variance = $gen->getVariance();

        // Variance of [1,2,3] with equal weights: ((1-2)^2 + (2-2)^2 + (3-2)^2) / 3 = 2/3
        $this->assertEqualsWithDelta(2/3, $variance, 0.001);
    }

    public function testGetStandardDeviationForNumericValues(): void
    {
        $gen = new WeightedRandomGenerator();
        $gen->registerValues([1 => 1.0, 2 => 1.0, 3 => 1.0]);

        $stdDev = $gen->getStandardDeviation();

        // StdDev = sqrt(2/3) ≈ 0.816
        $this->assertEqualsWithDelta(0.816, $stdDev, 0.01);
    }

    // --- Decay/Boost Tests ---

    public function testEnableSelectionTracking(): void
    {
        $gen = new WeightedRandomGenerator();
        $gen->registerValues(['a' => 1.0, 'b' => 1.0]);
        $gen->enableSelectionTracking();

        $gen->generate();

        $counts = $gen->getSelectionCounts();
        $this->assertNotEmpty($counts);
        $this->assertSame(1, array_sum($counts));
    }

    public function testResetSelectionCounts(): void
    {
        $gen = new WeightedRandomGenerator();
        $gen->registerValue('a', 1.0);
        $gen->enableSelectionTracking();

        $gen->generate();
        $gen->resetSelectionCounts();

        $this->assertEmpty($gen->getSelectionCounts());
    }

    public function testDecayWeightReducesWeight(): void
    {
        $gen = new WeightedRandomGenerator();
        $gen->registerValue('a', 10.0);

        $gen->decayWeight('a', 0.5); // Reduce to 50%

        $prob = $gen->getProbability('a');
        $this->assertEqualsWithDelta(1.0, $prob, 0.001); // Still only value, so 100%

        // Check actual weight
        $values = iterator_to_array($gen->getWeightedValues());
        $this->assertEqualsWithDelta(5.0, $values[0]->getWeight(), 0.001);
    }

    public function testDecayWeightThrowsForInvalidValue(): void
    {
        $gen = new WeightedRandomGenerator();
        $gen->registerValue('a', 1.0);

        $this->expectException(\InvalidArgumentException::class);
        $gen->decayWeight('nonexistent', 0.5);
    }

    public function testDecayWeightThrowsForInvalidFactor(): void
    {
        $gen = new WeightedRandomGenerator();
        $gen->registerValue('a', 1.0);

        $this->expectException(\InvalidArgumentException::class);
        $gen->decayWeight('a', 1.5); // Must be <= 1.0
    }

    public function testBoostWeightIncreasesWeight(): void
    {
        $gen = new WeightedRandomGenerator();
        $gen->registerValue('a', 10.0);

        $gen->boostWeight('a', 2.0); // Double the weight

        $values = iterator_to_array($gen->getWeightedValues());
        $this->assertEqualsWithDelta(20.0, $values[0]->getWeight(), 0.001);
    }

    public function testBoostWeightThrowsForInvalidFactor(): void
    {
        $gen = new WeightedRandomGenerator();
        $gen->registerValue('a', 1.0);

        $this->expectException(\InvalidArgumentException::class);
        $gen->boostWeight('a', 0.5); // Must be >= 1.0
    }

    public function testDecayAllWeights(): void
    {
        $gen = new WeightedRandomGenerator();
        $gen->registerValues(['a' => 10.0, 'b' => 20.0]);

        $gen->decayAllWeights(0.5);

        $values = iterator_to_array($gen->getWeightedValues());
        $this->assertEqualsWithDelta(5.0, $values[0]->getWeight(), 0.001);
        $this->assertEqualsWithDelta(10.0, $values[1]->getWeight(), 0.001);
    }

    public function testBoostAllWeights(): void
    {
        $gen = new WeightedRandomGenerator();
        $gen->registerValues(['a' => 10.0, 'b' => 20.0]);

        $gen->boostAllWeights(2.0);

        $values = iterator_to_array($gen->getWeightedValues());
        $this->assertEqualsWithDelta(20.0, $values[0]->getWeight(), 0.001);
        $this->assertEqualsWithDelta(40.0, $values[1]->getWeight(), 0.001);
    }

    public function testAutoAdjustWeightsBalancesDistribution(): void
    {
        $gen = new WeightedRandomGenerator();
        $gen->registerValues(['frequent' => 1.0, 'rare' => 1.0]);
        $gen->enableSelectionTracking();

        // Simulate 'frequent' being selected more
        $ref = new \ReflectionProperty($gen, 'selectionCounts');
        $ref->setAccessible(true);
        $ref->setValue($gen, [0 => 10, 1 => 2]); // frequent=10, rare=2

        $gen->autoAdjustWeights(0.5);

        // 'frequent' should be decayed, 'rare' should be boosted
        $values = iterator_to_array($gen->getWeightedValues());
        $this->assertLessThan(1.0, $values[0]->getWeight());
        $this->assertGreaterThan(1.0, $values[1]->getWeight());
    }

    public function testAutoAdjustWeightsWithNoSelections(): void
    {
        $gen = new WeightedRandomGenerator();
        $gen->registerValues(['a' => 1.0, 'b' => 2.0]);

        $gen->autoAdjustWeights(); // Should not throw

        // Weights should remain unchanged
        $values = iterator_to_array($gen->getWeightedValues());
        $this->assertEqualsWithDelta(1.0, $values[0]->getWeight(), 0.001);
        $this->assertEqualsWithDelta(2.0, $values[1]->getWeight(), 0.001);
    }

    // --- Composite Generator Tests ---

    public function testCompositeGeneratorNested(): void
    {
        $inner = new WeightedRandomGenerator();
        $inner->registerValues(['x' => 1.0, 'y' => 1.0]);

        $outer = new WeightedRandomGenerator();
        $outer->registerValue($inner, 1.0);
        $outer->registerValue('direct', 1.0);

        $result = $outer->generate();

        // Result should be either from inner generator ('x' or 'y') or 'direct'
        $this->assertContains($result, ['x', 'y', 'direct']);
    }

    public function testCompositeGeneratorMultiLevel(): void
    {
        $innermost = new WeightedRandomGenerator();
        $innermost->registerValues([1 => 1.0, 2 => 1.0]);

        $middle = new WeightedRandomGenerator();
        $middle->registerValue($innermost, 1.0);

        $outer = new WeightedRandomGenerator();
        $outer->registerValue($middle, 1.0);

        $result = $outer->generate();

        // Should drill down through multiple levels
        $this->assertContains($result, [1, 2]);
    }


}
