<?php
declare(strict_types=1);

namespace Tests\Generator;

use Generator\FakeBaseGenerator;
use mschandr\WeightedRandom\Generator\WeightedBagRandomGenerator;
use mschandr\WeightedRandom\Generator\WeightedRandomGenerator;
use mschandr\WeightedRandom\Value\WeightedGroup;
use mschandr\WeightedRandom\Value\WeightedValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(\mschandr\WeightedRandom\Generator\WeightedBagRandomGenerator::class)]
#[CoversClass(\mschandr\WeightedRandom\Generator\WeightedRandomGenerator::class)]
#[CoversClass(\mschandr\WeightedRandom\Value\WeightedValue::class)]
#[CoversClass(\mschandr\WeightedRandom\Value\WeightedGroup::class)]
final class WeightedBagRandomGeneratorTest extends TestCase
{
    public function testGenerateThrowsIfNoValuesRegistered(): void
    {
        $generator = new WeightedBagRandomGenerator();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot refill bag: no values registered.');
        $generator->generate();
    }

    public function testRegisterValueAndGenerate(): void
    {
        $generator = new WeightedBagRandomGenerator();
        $generator->registerValue('apple', 2.0);

        $results = $generator->generateMultiple(4);

        foreach ($results as $result) {
            $this->assertSame('apple', $result);
        }
    }

    public function testRegisterValuesWithArray(): void
    {
        $generator = new WeightedBagRandomGenerator();
        $generator->registerValues([
            'banana' => 1.0,
            'orange' => 1.0,
        ]);

        $results = (array)$generator->generateMultiple(2);
        sort($results);

        $this->assertSame(['banana', 'orange'], $results);
    }

    public function testBagResetsAfterExhaustion(): void
    {
        $generator = new WeightedBagRandomGenerator();
        $generator->registerValues(['x' => 1.0]);

        $first = $generator->generate();
        $this->assertSame('x', $first);

        $second = $generator->generate();
        $this->assertSame('x', $second);
    }

    public function testGenerateUsesWeightedGroupPickOne(): void
    {
        $group = new WeightedGroup(['foo', 'bar']);
        $generator = new WeightedBagRandomGenerator();
        $generator->registerValue($group, 1.0);

        $result = $generator->generate();
        $this->assertContains($result, ['foo', 'bar']);
    }

    public function testGenerateMultipleWithoutDuplicates(): void
    {
        $generator = new WeightedBagRandomGenerator();
        $generator->registerValues([
            'a' => 1.0,
            'b' => 1.0,
            'c' => 1.0,
        ]);

        $results = (array)$generator->generateMultipleWithoutDuplicates(3);

        $this->assertCount(3, $results);
        $this->assertSame(3, count(array_unique($results)));

        sort($results);
        $this->assertSame(['a', 'b', 'c'], $results);
    }

    public function testRegisterGroupReturnsFromGroup(): void
    {
        $generator = new WeightedBagRandomGenerator();

        $groupMembers = ['apple', 'banana', 'orange'];
        $generator->registerGroup($groupMembers, 1.0);

        $result = $generator->generate();
        $this->assertContains($result, $groupMembers);
    }

    public function testConstructorWithCustomBaseGenerator(): void
    {
        $base = new WeightedRandomGenerator();
        $generator = new WeightedBagRandomGenerator($base);

        $generator->registerValue('z', 1.0);
        $this->assertSame('z', $generator->generate());
    }

    /**
     * @return void
     * @throws \ReflectionException
     */
    public function testRefillBagSkipsNonWeightedValue(): void
    {
        $generator = new WeightedBagRandomGenerator();

        // Get access to the internal base generator
        $baseProperty = new \ReflectionProperty($generator, 'base');
        $baseProperty->setAccessible(true);
        $base = $baseProperty->getValue($generator);

        // Overwrite the internal state so getWeightedValues() returns garbage
        $valuesProperty = new \ReflectionProperty($base, 'values');
        $valuesProperty->setAccessible(true);
        $valuesProperty->setValue($base, ['not-a-weighted-value']);

        // Force a refill
        $refillMethod = new \ReflectionMethod($generator, 'refillBag');
        $refillMethod->setAccessible(true);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot refill bag: no values registered.');
        $refillMethod->invoke($generator);

        // Verify the bag remained empty
        $bagProp = new \ReflectionProperty($generator, 'bag');
        $bagProp->setAccessible(true);
        $this->assertSame([], $bagProp->getValue($generator));
    }

    public function testRefillBagSkipsNonWeightedGarbage(): void
    {
        $fake = new class extends \mschandr\WeightedRandom\Generator\WeightedRandomGenerator {
            public function getWeightedValues(): \Generator {
                yield "garbage"; // not a WeightedValue
            }
        };

        $generator = new \mschandr\WeightedRandom\Generator\WeightedBagRandomGenerator($fake);

        $refill = new \ReflectionMethod($generator, 'refillBag');
        $refill->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot refill bag: no values registered.');

        $refill->invoke($generator);
    }

    public function testCompositeGeneratorInBag(): void
    {
        $inner = new \mschandr\WeightedRandom\Generator\WeightedRandomGenerator();
        $inner->registerValues(['nested1' => 1.0, 'nested2' => 1.0]);

        $bag = new \mschandr\WeightedRandom\Generator\WeightedBagRandomGenerator();
        $bag->registerValue($inner, 2.0);
        $bag->registerValue('direct', 1.0);

        $result = $bag->generate();

        // Result should be either from nested generator or 'direct'
        $this->assertContains($result, ['nested1', 'nested2', 'direct']);
    }
}

