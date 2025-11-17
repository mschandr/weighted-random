<?php
declare(strict_types=1);

namespace Tests\Value;

use mschandr\WeightedRandom\Value\WeightedValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WeightedValue::class)]
final class WeightedValueTest extends TestCase
{
    public function testConstructorStoresValueAndWeight(): void
    {
        $value = new WeightedValue('apple', 2.5);

        $this->assertSame('apple', $value->getValue());
        $this->assertSame(2.5, $value->getWeight());
    }

    public function testConstructorAcceptsIntegerWeight(): void
    {
        $value = new WeightedValue('banana', 5.0);

        $this->assertSame('banana', $value->getValue());
        $this->assertSame(5.0, $value->getWeight());
    }

    public function testConstructorAcceptsMixedValueTypes(): void
    {
        $objectValue = new \stdClass();
        $value = new WeightedValue($objectValue, 1.0);

        $this->assertSame($objectValue, $value->getValue());
    }

    public function testConstructorThrowsForZeroWeight(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Weight must be greater than zero.');

        new WeightedValue('test', 0);
    }

    public function testConstructorThrowsForNegativeWeight(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Weight must be greater than zero.');

        new WeightedValue('test', -1.5);
    }

    public function testGetArrayCopyReturnsCorrectStructure(): void
    {
        $value = new WeightedValue('orange', 3.7);
        $array = $value->getArrayCopy();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('value', $array);
        $this->assertArrayHasKey('weight', $array);
        $this->assertSame('orange', $array['value']);
        $this->assertSame(3.7, $array['weight']);
    }

    public function testGetArrayCopyWithComplexValue(): void
    {
        $complexValue = ['key' => 'value', 'nested' => ['data' => 123]];
        $value = new WeightedValue($complexValue, 1.0);
        $array = $value->getArrayCopy();

        $this->assertSame($complexValue, $array['value']);
        $this->assertSame(1.0, $array['weight']);
    }
}