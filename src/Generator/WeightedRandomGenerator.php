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

    /** @var array<int> */
    private array $selectionCounts = [];

    private bool $trackSelections = false;

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
                if ($this->trackSelections) {
                    $this->selectionCounts[$key] = ($this->selectionCounts[$key] ?? 0) + 1;
                }
                $value = $this->values[$key];
                return $this->resolveValue($value);
            }
        }

        $lastKey = array_key_last($this->values);
        if ($this->trackSelections && $lastKey !== null) {
            $this->selectionCounts[$lastKey] = ($this->selectionCounts[$lastKey] ?? 0) + 1;
        }
        $last = end($this->values);
        return $this->resolveValue($last);
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

    /**
     * Get distribution as a mapping of values to their probabilities.
     * Unlike normalizeWeights() which returns key=>probability, this returns value=>probability.
     *
     * @return array<mixed,float>
     */
    public function getDistribution(): array
    {
        $distribution = [];
        foreach ($this->getWeightedValues() as $weightedValue) {
            $value = $weightedValue->getValue();
            $prob  = $this->getProbability($value);

            // Handle WeightedGroup by distributing probability among members
            if ($value instanceof WeightedGroup) {
                $memberProb = $prob / $weightedValue->getValue()->countMembers();
                foreach ($value->getMembers() as $member) {
                    $distribution[$member] = ($distribution[$member] ?? 0.0) + $memberProb;
                }
            } else {
                $distribution[$value] = $prob;
            }
        }
        return $distribution;
    }

    /**
     * Calculate Shannon entropy of the distribution.
     * Higher entropy = more uniform distribution. Lower entropy = more skewed.
     * Range: 0 (all weight on one value) to log2(n) (perfectly uniform).
     *
     * @return float
     */
    public function getEntropy(): float
    {
        $entropy = 0.0;
        foreach ($this->normalizeWeights() as $probability) {
            if ($probability > 0.0) {
                $entropy -= $probability * log($probability, 2);
            }
        }
        return $entropy;
    }

    /**
     * Calculate expected value (weighted mean) for numeric values.
     * Only considers numeric values; ignores non-numeric values.
     *
     * @return float|null Returns null if no numeric values registered
     */
    public function getExpectedValue(): ?float
    {
        $sum = 0.0;
        $totalProb = 0.0;

        foreach ($this->getWeightedValues() as $weightedValue) {
            $value = $weightedValue->getValue();

            // Skip groups and non-numeric values
            if ($value instanceof WeightedGroup || !is_numeric($value)) {
                continue;
            }

            $prob = $this->getProbability($value);
            $sum += (float)$value * $prob;
            $totalProb += $prob;
        }

        return $totalProb > 0.0 ? $sum : null;
    }

    /**
     * Calculate variance for numeric values.
     * Only considers numeric values; ignores non-numeric values.
     *
     * @return float|null Returns null if no numeric values registered
     */
    public function getVariance(): ?float
    {
        $expectedValue = $this->getExpectedValue();
        if ($expectedValue === null) {
            return null;
        }

        $variance = 0.0;
        $totalProb = 0.0;

        foreach ($this->getWeightedValues() as $weightedValue) {
            $value = $weightedValue->getValue();

            if ($value instanceof WeightedGroup || !is_numeric($value)) {
                continue;
            }

            $prob = $this->getProbability($value);
            $variance += pow((float)$value - $expectedValue, 2) * $prob;
            $totalProb += $prob;
        }

        return $totalProb > 0.0 ? $variance : null;
    }

    /**
     * Calculate standard deviation for numeric values.
     * Only considers numeric values; ignores non-numeric values.
     *
     * @return float|null Returns null if no numeric values registered
     */
    public function getStandardDeviation(): ?float
    {
        $variance = $this->getVariance();
        return $variance !== null ? sqrt($variance) : null;
    }

    /**
     * Enable or disable selection tracking for automatic decay/boost.
     *
     * @param bool $enable
     * @return self
     */
    public function enableSelectionTracking(bool $enable = true): self
    {
        $this->trackSelections = $enable;
        return $this;
    }

    /**
     * Get selection counts for all values.
     *
     * @return array<int,int> Array mapping value keys to selection counts
     */
    public function getSelectionCounts(): array
    {
        return $this->selectionCounts;
    }

    /**
     * Reset selection counts to zero.
     *
     * @return self
     */
    public function resetSelectionCounts(): self
    {
        $this->selectionCounts = [];
        return $this;
    }

    /**
     * Manually decay (reduce) the weight of a specific value.
     *
     * @param mixed $value The value to decay
     * @param float $factor Decay factor (0.0-1.0). E.g., 0.9 reduces weight to 90%
     * @return self
     */
    public function decayWeight(mixed $value, float $factor): self
    {
        Assert::greaterThan($factor, 0.0, 'Decay factor must be greater than 0.');
        Assert::lessThanEq($factor, 1.0, 'Decay factor must be less than or equal to 1.');

        $key = $this->getExistingValueKey($value);
        if ($key === null) {
            throw new \InvalidArgumentException('Value not registered.');
        }

        $newWeight = $this->weights[$key] * $factor;
        if ($newWeight <= 0.0) {
            throw new \RuntimeException('Decayed weight would be <= 0. Use a larger decay factor.');
        }

        $this->weights[$key] = $newWeight;
        $this->resetTotalWeightCount();
        return $this;
    }

    /**
     * Manually boost (increase) the weight of a specific value.
     *
     * @param mixed $value The value to boost
     * @param float $factor Boost factor (>= 1.0). E.g., 1.5 increases weight by 50%
     * @return self
     */
    public function boostWeight(mixed $value, float $factor): self
    {
        Assert::greaterThanEq($factor, 1.0, 'Boost factor must be >= 1.0.');

        $key = $this->getExistingValueKey($value);
        if ($key === null) {
            throw new \InvalidArgumentException('Value not registered.');
        }

        $this->weights[$key] *= $factor;
        $this->resetTotalWeightCount();
        return $this;
    }

    /**
     * Apply decay factor to all registered weights.
     *
     * @param float $factor Decay factor (0.0-1.0)
     * @return self
     */
    public function decayAllWeights(float $factor): self
    {
        Assert::greaterThan($factor, 0.0, 'Decay factor must be greater than 0.');
        Assert::lessThanEq($factor, 1.0, 'Decay factor must be less than or equal to 1.');

        foreach ($this->weights as $key => $weight) {
            $this->weights[$key] = $weight * $factor;
        }
        $this->resetTotalWeightCount();
        return $this;
    }

    /**
     * Apply boost factor to all registered weights.
     *
     * @param float $factor Boost factor (>= 1.0)
     * @return self
     */
    public function boostAllWeights(float $factor): self
    {
        Assert::greaterThanEq($factor, 1.0, 'Boost factor must be >= 1.0.');

        foreach ($this->weights as $key => $weight) {
            $this->weights[$key] = $weight * $factor;
        }
        $this->resetTotalWeightCount();
        return $this;
    }

    /**
     * Automatically adjust weights based on selection frequency.
     * Values that were selected more often get decayed, less selected get boosted.
     *
     * @param float $strength Adjustment strength (0.0-1.0). Higher = more aggressive adjustment
     * @return self
     */
    public function autoAdjustWeights(float $strength = 0.5): self
    {
        Assert::greaterThan($strength, 0.0, 'Strength must be greater than 0.');
        Assert::lessThanEq($strength, 1.0, 'Strength must be less than or equal to 1.');

        if (empty($this->selectionCounts)) {
            return $this; // No selections to base adjustment on
        }

        $totalSelections = array_sum($this->selectionCounts);
        if ($totalSelections === 0) {
            return $this;
        }

        foreach ($this->weights as $key => $weight) {
            $selections = $this->selectionCounts[$key] ?? 0;
            $expectedSelections = $totalSelections / count($this->weights);

            if ($expectedSelections > 0) {
                $ratio = $selections / $expectedSelections;
                // If selected more than expected (ratio > 1), decay
                // If selected less than expected (ratio < 1), boost
                $factor = 1.0 + ($strength * (1.0 - $ratio));
                $this->weights[$key] = max(0.001, $weight * $factor); // Minimum weight to avoid zero
            }
        }

        $this->resetTotalWeightCount();
        return $this;
    }
}
