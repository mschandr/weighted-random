<?php
declare(strict_types=1);

namespace mschandr\WeightedRandom;

use Assert\Assertion;
use Assert\AssertionFailedException;
use Generator;
use Random\RandomException;

/**
 * Class WeightedRandomGenerator
 *
 * Generate a random value out of a set of registered values, where the probability of generating the value is
 * determined by the given weight. The probability grows linearly with the set weight.
 */
final class WeightedRandomGenerator
{
    /**
     * @var array<int, mixed>
     */
    private array $values = [];

    /**
     * @var array<int, float>
     */
    private array $weights = [];

    /**
     * @var float|null
     */
    private ?float $totalWeightCount = null;

    /**
     * @var callable
     */
    private $randomNumberGenerator;

    /** @var array<mixed> */
    private array $bag = [];

    /**
     * @var int
     */
    private int $bagIndex = 0;

    public function __construct()
    {
        $this->randomNumberGenerator = 'random_int';
    }

    /**
     * Pick a weighted key.
     *
     * @return mixed
     */
    public function pickKey(): mixed
    {
        return $this->generate();
    }

    /**
     * Deterministic seeded pick (uses WeightedRandom::pickKeySeeded).
     *
     * @param int $seed
     * @param string $namespace
     * @return mixed
     */
    public function pickKeySeeded(int $seed, string $namespace = ''): mixed
    {
        return WeightedRandom::pickKeySeeded($this->weights, $seed, $namespace);
    }

    /**
     * Register (add or update) a possible return value.
     *
     * @param mixed $value
     * @param int|float $weight
     * @return WeightedRandomGenerator
     */
    public function registerValue(mixed $value, int|float $weight = 1): self
    {
        if ($weight <= 0) {
            throw new \InvalidArgumentException('Weight must be greater than zero.');
        }
        $key = $this->getValueKey($value);
        $this->setKeyWeight($key, (float)$weight);
        $this->resetTotalWeightCount();
        return $this;
    }

    /**
     * Register multiple values at once.
     *
     * @param array<mixed, int|float> $valueCollection
     */
    public function registerValues(array $valueCollection): self
    {
        foreach ($valueCollection as $value => $weight) {
            if (!is_int($weight) && !is_float($weight)) {
                throw new \InvalidArgumentException('Weight must be int or float.');
            }
            $this->registerValue($value, $weight);
        }
        return $this;
    }

    /**
     * Register via WeightedValue.
     */
    public function registerWeightedValue(WeightedValue $weightedValue): self
    {
        return $this->registerValue($weightedValue->getValue(), $weightedValue->getWeight());
    }

    /**
     * Remove a registered value.
     */
    public function removeValue(mixed $value): void
    {
        $key = $this->getExistingValueKey($value);
        if ($key === null) {
            throw new \InvalidArgumentException('Given value is not registered.');
        }
        unset($this->values[$key], $this->weights[$key]);
        $this->resetTotalWeightCount();
    }

    /**
     * Remove via WeightedValue.
     */
    public function removeWeightedValue(WeightedValue $weightedValue): void
    {
        $this->removeValue($weightedValue->getValue());
    }

    /**
     * Get all registered WeightedValues.
     *
     * @return Generator<WeightedValue>
     */
    public function getWeightedValues(): Generator
    {
        foreach ($this->values as $key => $value) {
            yield new WeightedValue($value, $this->weights[$key]);
        }
    }

    /**
     * Get one WeightedValue by value.
     */
    public function getWeightedValue(mixed $value): WeightedValue
    {
        $key = $this->getExistingValueKey($value);
        if ($key === null) {
            throw new \InvalidArgumentException('Given value is not registered.');
        }
        return new WeightedValue($this->values[$key], $this->weights[$key]);
    }

    /**
     * Generate a single random value.
     *
     * @return mixed
     * @throws RandomException|AssertionFailedException
     */
    public function generate(): mixed
    {
        Assertion::notEmpty($this->values, 'At least one value should be registered.');

        $totalWeightCount = $this->getTotalWeightCount();

        if ($totalWeightCount <= 0.0) {
            throw new \RuntimeException('Total weight must be greater than zero.');
        }

        // Crypto-safe float in [0.0, totalWeightCount)
        $precision = 1_000_000; // 6 decimal places
        $randInt   = random_int(0, (int)($totalWeightCount * $precision) - 1);
        $randomValue = $randInt / $precision;

        foreach ($this->weights as $key => $weight) {
            $randomValue -= $weight;
            if ($randomValue < 0) {
                $value = $this->values[$key];

                // 👇 Handle groups
                if ($value instanceof WeightedGroup) {
                    return $value->pickOne();
                }

                return $value;
            }
        }

        // Fallback: last value (also check if group)
        $last = end($this->values);
        return $last instanceof WeightedGroup ? $last->pickOne() : $last;
    }

    /**
     * Generate multiple values (duplicates allowed).
     *
     * @param int $sampleCount
     * @return Generator<mixed>
     * @throws AssertionFailedException
     * @throws RandomException
     */
    public function generateMultiple(int $sampleCount): Generator
    {
        if ($sampleCount <= 0) {
            throw new \InvalidArgumentException('Sample count must be greater than zero.');
        }

        for ($i = 0; $i < $sampleCount; $i++) {
            yield $this->generate();
        }
    }

    /**
     * Generate multiple values (no duplicates).
     *
     * @return Generator<mixed>
     */
    public function generateMultipleWithoutDuplicates(int $sampleCount): Generator
    {
        if ($sampleCount <= 0) {
            throw new \InvalidArgumentException('Sample count must be greater than zero.');
        }
        if ($sampleCount > count($this->values)) {
            throw new \InvalidArgumentException('Sample count exceeds registered value count.');
        }

        $returnedCollection = [];
        while (count($returnedCollection) < $sampleCount) {
            $sample = $this->generate();
            if (in_array($sample, $returnedCollection, true)) {
                continue;
            }
            $returnedCollection[] = $sample;
            yield $sample;
        }
    }

    /**
     * @return mixed
     */
    public function generateFromBag(): mixed
    {
        if (empty($this->bag)) {
            $this->refillBag();
        }

        $value = $this->bag[$this->bagIndex++];
        if ($this->bagIndex >= count($this->bag)) {
            $this->bag = [];
            $this->bagIndex = 0;
        }

        return $value instanceof WeightedGroup ? $value->pickOne() : $value;
    }

    /**
     * @param int $count
     * @return array
     */
    public function generateMultipleFromBag(int $count): array
    {
        $results = [];
        for ($i = 0; $i < $count; $i++) {
            $results[] = $this->generateFromBag();
        }
        return $results;
    }

    private function refillBag(): void
    {
        $this->bag = [];

        foreach ($this->getWeightedValues() as $weightedValue) {
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

    /**
     * Set a custom RNG (mainly for testing).
     *
     * @param callable(int,int):int $randomNumberGenerator
     */
    public function setRandomNumberGenerator(callable $randomNumberGenerator): void
    {
        $this->randomNumberGenerator = $randomNumberGenerator;
    }

    /**
     * @param mixed $value
     * @return int
     */
    private function getValueKey(mixed $value): int
    {
        if (!in_array($value, $this->values, true)) {
            $this->values[] = $value;
        }
        return $this->getExistingValueKey($value) ?? 0;
    }

    /**
     * @param int $key
     * @param float $weight
     * @return void
     */
    private function setKeyWeight(int $key, float $weight): void
    {
        $this->weights[$key] = $weight;
    }

    /**
     * @param mixed $value
     * @return int|null
     */
    private function getExistingValueKey(mixed $value): ?int
    {
        foreach ($this->values as $key => $stored) {
            if ($this->valuesAreEqual($stored, $value)) {
                return $key;
            }
        }
        return null;
    }

    /**
     * @param mixed $a
     * @param mixed $b
     * @return bool
     */
    private function valuesAreEqual(mixed $a, mixed $b): bool
    {
        // Scalars + null → strict compare
        if (is_scalar($a) || $a === null) {
            return $a === $b;
        }

        // Arrays → deep compare
        if (is_array($a) && is_array($b)) {
            return $a == $b; // loose compare is fine here for deep equality
        }

        // Objects → same instance
        if (is_object($a) && is_object($b)) {
            return $a === $b;
        }

        return false;
    }

    /**
     * @param array $values
     * @param int|float $weight
     * @return $this
     */
    public function registerGroup(array $values, int|float $weight): self
    {
        $this->registerValue(new WeightedGroup($values), $weight);
        return $this;
    }

    /**
     * @return void
     */
    private function resetTotalWeightCount(): void
    {
        $this->totalWeightCount = null;
    }

    /**
     * @return float
     */
    private function getTotalWeightCount(): float
    {
        if ($this->totalWeightCount === null) {
            $this->totalWeightCount = array_sum($this->weights);
        }
        return $this->totalWeightCount;
    }

    /**
     * Get normalized weights (sum = 1.0).
     *
     * @return array<int, float> keys aligned with $this->values
     */
    public function normalizeWeights(): array
    {
        $total = $this->getTotalWeightCount();
        if ($total <= 0.0) {
            throw new \RuntimeException('Cannot normalize: total weight is zero.');
        }

        $normalized = [];
        foreach ($this->weights as $key => $weight) {
            $normalized[$key] = $weight / $total;
        }
        return $normalized;
    }

    /**
     * Get probability of a given value (0.0–1.0).
     */
    public function getProbability(mixed $value): float
    {
        $key = $this->getExistingValueKey($value);
        if ($key === null) {
            throw new \InvalidArgumentException('Given value is not registered.');
        }

        $total = $this->getTotalWeightCount();
        if ($total <= 0.0) {
            throw new \RuntimeException('Cannot compute probability: total weight is zero.');
        }

        return $this->weights[$key] / $total;
    }


}
