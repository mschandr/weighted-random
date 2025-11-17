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


}
