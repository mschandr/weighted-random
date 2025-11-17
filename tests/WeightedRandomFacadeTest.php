<?php
declare(strict_types=1);

namespace Tests;

use mschandr\WeightedRandom\WeightedRandom;
use mschandr\WeightedRandom\Generator\WeightedRandomGenerator;
use mschandr\WeightedRandom\Generator\WeightedBagRandomGenerator;
use mschandr\WeightedRandom\Contract\WeightedRandomInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WeightedRandom::class)]
#[UsesClass(WeightedRandomGenerator::class)]
#[UsesClass(WeightedBagRandomGenerator::class)]
final class WeightedRandomFacadeTest extends TestCase
{
    public function testCreateFloatReturnsWeightedRandomGenerator(): void
    {
        $generator = WeightedRandom::createFloat();

        $this->assertInstanceOf(WeightedRandomGenerator::class, $generator);
        $this->assertInstanceOf(WeightedRandomInterface::class, $generator);
    }

    public function testCreateFloatReturnsNewInstanceEachTime(): void
    {
        $generator1 = WeightedRandom::createFloat();
        $generator2 = WeightedRandom::createFloat();

        $this->assertNotSame($generator1, $generator2);
    }

    public function testCreateFloatGeneratorIsUsable(): void
    {
        $generator = WeightedRandom::createFloat();
        $generator->registerValue('apple', 1.0);
        $generator->registerValue('banana', 1.0);

        $result = $generator->generate();
        $this->assertContains($result, ['apple', 'banana']);
    }

    public function testCreateBagReturnsWeightedBagRandomGenerator(): void
    {
        $generator = WeightedRandom::createBag();

        $this->assertInstanceOf(WeightedBagRandomGenerator::class, $generator);
        $this->assertInstanceOf(WeightedRandomInterface::class, $generator);
    }

    public function testCreateBagReturnsNewInstanceEachTime(): void
    {
        $generator1 = WeightedRandom::createBag();
        $generator2 = WeightedRandom::createBag();

        $this->assertNotSame($generator1, $generator2);
    }

    public function testCreateBagGeneratorIsUsable(): void
    {
        $generator = WeightedRandom::createBag();
        $generator->registerValue('apple', 1.0);
        $generator->registerValue('banana', 1.0);

        $result = $generator->generate();
        $this->assertContains($result, ['apple', 'banana']);
    }

    public function testCreateFloatAndCreateBagReturnDifferentTypes(): void
    {
        $floatGen = WeightedRandom::createFloat();
        $bagGen = WeightedRandom::createBag();

        $this->assertInstanceOf(WeightedRandomGenerator::class, $floatGen);
        $this->assertInstanceOf(WeightedBagRandomGenerator::class, $bagGen);
        $this->assertNotSame(get_class($floatGen), get_class($bagGen));
    }
}
