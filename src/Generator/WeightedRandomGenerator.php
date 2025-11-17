<?php
declare(strict_types=1);

namespace mschandr\WeightedRandom\Generator;

use Webmozart\Assert\Assert;
use Generator as PhpGenerator;
use mschandr\WeightedRandom\Contract\WeightedRandomInterface;
use mschandr\WeightedRandom\Value\WeightedValue;
use mschandr\WeightedRandom\Value\WeightedGroup;

/**
 * WeightedRandomGenerator
 *
 * Classic probabilistic generator that supports int/float weights,
 * groups, normalization, and seeded RNG (via WeightedRandom facade).
 */
class WeightedRandomGenerator implements WeightedRandomInterface
{
    /** @var array<mixed> */
    private array $values = [];

    /** @var array<float> */
    private array $weights = [];

    private ?float $totalWeightCount = null;

    /** @var callable */
    private     $randomNumberGenerator;
    private int $maxAttemptsFactor = 10;

    public function __construct()
    {
        $this->randomNumberGenerator = 'random_int';
    }

    public function registerValues(array $valueCollection): self
    {
        foreach ($valueCollection as $value => $weight) {
            Assert::numeric($weight, 'Weight must be numeric.');
            $this->registerValue($value, (float)$weight);
        }
        return $this;
    }

    public function registerValue(mixed $value, float $weight): self
    {
        Assert::greaterThan($weight, 0, 'Weight must be greater than 0.');
        $key                 = $this->getValueKey($value);
        $this->weights[$key] = $weight;
        $this->resetTotalWeightCount();
        return $this;
    }

    private function getValueKey(mixed $value): int
    {
        if (!in_array($value, $this->values, true)) {
            $this->values[] = $value;
        }
        return $this->getExistingValueKey($value) ?? array_key_last($this->values);
    }

    private function getExistingValueKey(mixed $value): ?int
    {
        $key = array_search($value, $this->values, true);
        return $key === false ? null : $key;
    }

    private function resetTotalWeightCount(): void
    {
        $this->totalWeightCount = null;
    }

    public function registerGroup(array $values, float $weight): self
    {
        if ($weight <= 0) {
            throw new \InvalidArgumentException('Group weight must be greater than zero.');
        }
        if (empty($values)) {
            throw new \InvalidArgumentException('Group must contain at least one member.');
        }
        return $this->registerValue(new WeightedGroup($values), $weight);
    }

    public function generateMultiple(int $count): iterable
    {
        Assert::greaterThan($count, 0, 'Sample count must be greater than 0.');
        for ($i = 0; $i < $count; $i++) {
            yield $this->generate();
        }
    }

    /**
     * @throws RandomException
     */
    public function generate(): mixed
    {
        Assert::notEmpty($this->values, 'At least one value should be registered.');

        $totalWeightCount = $this->getTotalWeightCount();
        if ($totalWeightCount <= 0.0) {
            throw new \RuntimeException('Total weight must be greater than zero.');
        }

        // Crypto-safe float in [0.0, totalWeightCount)
        $precision   = 1_000_000;
        $rng         = $this->randomNumberGenerator;
        $randInt     = $rng(0, (int)($totalWeightCount * $precision) - 1);
        $randomValue = $randInt / $precision;

        foreach ($this->weights as $key => $weight) {
            $randomValue -= $weight;
            if ($randomValue < 0) {
                $value = $this->values[$key];
                return $value instanceof WeightedGroup ? $value->pickOne() : $value;
            }
        }

        $last = end($this->values);
        return $last instanceof WeightedGroup ? $last->pickOne() : $last;
    }

    private function getTotalWeightCount(): float
    {
        if ($this->totalWeightCount === null) {
            $this->totalWeightCount = array_sum($this->weights);
        }
        return $this->totalWeightCount;
    }

    /**
     * @param int $count
     * @return iterable
     */
    public function generateMultipleWithoutDuplicates(int $count): iterable
    {
        Assert::greaterThan($count, 0, 'Sample count must be greater than 0.');
        Assert::lessThanEq($count, count($this->values), 'Sample count exceeds registered value count.');

        $returned    = [];
        $attempts    = 0;
        $maxAttempts = $this->maxAttemptsFactor ?: ($count * 10); // arbitrary safety cap

        while (count($returned) < $count) {
            if ($attempts++ >= $maxAttempts) {
                throw new \RuntimeException('Unable to generate enough unique values without duplicates.');
            }

            $sample = $this->generate();
            if (in_array($sample, $returned, true)) {
                continue;
            }
            $returned[] = $sample;
            yield $sample;
        }
    }

    /**
     * Return all registered values as WeightedValue instances.
     *
     * @return PhpGenerator<WeightedValue>
     */
    public function getWeightedValues(): PhpGenerator
    {
        foreach ($this->values as $key => $value) {
            if (!array_key_exists($key, $this->weights)) {
                continue; // skip values with no weight
            }
            yield new WeightedValue($value, $this->weights[$key]);
        }
    }

    /**
     * Return the total normalized distribution.
     *
     * @return array<string|int,float>
     */
    public function normalizeWeights(): array
    {
        $sum = $this->getTotalWeightCount();
        if ($sum <= 0.0) {
            throw new \RuntimeException('Cannot normalize weights: total weight is zero.');
        }
        $normalized = [];
        foreach ($this->weights as $key => $weight) {
            $normalized[$key] = $weight / $sum;
        }
        return $normalized;
    }

    /**
     * Return probability of a given value.
     */
    public function getProbability(mixed $value): float
    {
        $key = $this->getExistingValueKey($value);
        if ($key === null) {
            throw new \InvalidArgumentException('Value not registered.');
        }
        $sum = $this->getTotalWeightCount();
        return $sum > 0.0 ? $this->weights[$key] / $sum : 0.0;
    }

    public function setMaxAttemptsFactor(int $factor): void
    {
        $this->maxAttemptsFactor = $factor;
    }
}
