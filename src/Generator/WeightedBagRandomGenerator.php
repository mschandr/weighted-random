<?php
declare(strict_types=1);

namespace mschandr\WeightedRandom\Generator;

use mschandr\WeightedRandom\Contract\WeightedRandomInterface;
use mschandr\WeightedRandom\Value\WeightedGroup;
use mschandr\WeightedRandom\Value\WeightedValue;

/**
 * WeightedBagRandomGenerator
 *
 * Fair-distribution generator (bag/urn model).
 * Expands weights into a bag, shuffles, and draws without replacement
 * until exhausted, then refills.
 */
final class WeightedBagRandomGenerator implements WeightedRandomInterface
{
    private WeightedRandomGenerator $base;

    /** @var array<mixed> */
    private array $bag = [];

    private int $bagIndex = 0;

    public function __construct(?WeightedRandomGenerator $base = null)
    {
        $this->base = $base ?? new WeightedRandomGenerator();
    }

    public function registerValue(mixed $value, float $weight): self
    {
        $this->base->registerValue($value, $weight);
        return $this;
    }

    public function registerValues(array $valueCollection): self
    {
        $this->base->registerValues($valueCollection);
        return $this;
    }

    public function registerGroup(array $values, float $weight): self
    {
        $this->base->registerGroup($values, $weight);
        return $this;
    }

    public function generate(): mixed
    {
        return $this->generateFromBag();
    }

    public function generateMultiple(int $count): iterable
    {
        $results = [];
        for ($i = 0; $i < $count; $i++) {
            $results[] = $this->generateFromBag();
        }
        return $results;
    }

    public function generateMultipleWithoutDuplicates(int $count): iterable
    {
        // Bag mode always ensures no duplicates within a single cycle.
        return $this->generateMultiple($count);
    }

    /**
     * Draw one value from the bag.
     */
    private function generateFromBag(): mixed
    {
        if (empty($this->bag)) {
            $this->refillBag();
        }

        $value = $this->bag[$this->bagIndex++];
        if ($this->bagIndex >= count($this->bag)) {
            $this->bag      = [];
            $this->bagIndex = 0;
        }

        return $this->resolveValue($value);
    }

    /**
     * Resolve a value, handling special types (groups and composite generators).
     *
     * @param mixed $value
     * @return mixed
     */
    private function resolveValue(mixed $value): mixed
    {
        if ($value instanceof WeightedGroup) {
            return $value->pickOne();
        }

        // Support composite generators (nested generators)
        if ($value instanceof WeightedRandomInterface) {
            return $value->generate();
        }

        return $value;
    }

    /**
     * Build the bag from registered values and shuffle.
     */
    private function refillBag(): void
    {
        $this->bag = [];

        foreach ($this->base->getWeightedValues() as $weightedValue) {
            if (!$weightedValue instanceof WeightedValue) {
                continue;
            }
            $value  = $weightedValue->getValue();
            $weight = $weightedValue->getWeight();

            for ($i = 0; $i < (int)round($weight); $i++) {
                $this->bag[] = $value;
            }
        }

        if (empty($this->bag)) {
            throw new \RuntimeException('Cannot refill bag: no values registered.');
        }

        shuffle($this->bag);
        $this->bagIndex = 0;
    }
}
