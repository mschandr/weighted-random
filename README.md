# Weighted Random

[![PHPUnit Tests](https://github.com/mschandr/weighted-random/actions/workflows/php.yml/badge.svg)](https://github.com/mschandr/weighted-random/actions/workflows/php.yml)
[![codecov](https://codecov.io/github/mschandr/weighted-random/graph/badge.svg?token=4J70DHYFTK)](https://codecov.io/github/mschandr/weighted-random)
[![Latest Stable Version](https://img.shields.io/packagist/v/mschandr/weighted-random.svg)](https://packagist.org/packages/mschandr/weighted-random)
[![License](https://img.shields.io/github/license/mschandr/weighted-random.svg)](LICENSE)

This library is used to pick random values from a set of registered values, where values with a higher
weight have a larger probability to be picked.

---

## Installation

To install this library using Composer:

```bash
composer require mschandr/weighted-random
```

## 📚 Documentation

- **[API Reference](API.md)** - Complete API documentation for all classes and methods
- **[CHANGELOG](CHANGELOG.md)** - Version history and migration guides
- **[Contributing Guide](CONTRIBUTING.md)** - Guidelines for contributing to the project

## 🚀 Usage

### Basic Usage

```php
use mschandr\WeightedRandom\WeightedRandom;

// Create a generator
$gen = WeightedRandom::createFloat();

// Register values with weights
$gen->registerValue('common', 7.0)
    ->registerValue('uncommon', 2.5)
    ->registerValue('rare', 0.5);

// Generate a random value
$result = $gen->generate();

// Generate multiple values
$results = $gen->generateMultiple(10);

// Generate unique values (no duplicates)
$unique = $gen->generateMultipleWithoutDuplicates(3);
```

### Batch Registration

```php
$gen->registerValues([
    'apple'  => 3.0,
    'banana' => 2.0,
    'cherry' => 1.0,
]);
```

### Groups (Multiple Values, Single Weight)

```php
// Register a group of values that share a single weight
$gen->registerGroup(['bronze', 'silver', 'gold'], 5.0);
// When the group is selected, one member is chosen uniformly at random
```

### Fair Distribution (Bag System)

```php
$bag = WeightedRandom::createBag();
$bag->registerValues(['rare' => 1, 'common' => 9]);

// Over 10 draws: exactly 1 rare, 9 common (then bag reshuffles)
$results = $bag->generateMultiple(10);
```

### Distribution Introspection

```php
// Get probability distribution
$distribution = $gen->getDistribution();
// Returns: ['apple' => 0.5, 'banana' => 0.333, 'cherry' => 0.167]

// Get probability of specific value
$prob = $gen->getProbability('apple'); // 0.5

// Calculate Shannon entropy (distribution randomness)
$entropy = $gen->getEntropy();

// For numeric values - statistical analysis
$gen->registerValues([1 => 1.0, 2 => 2.0, 3 => 1.0]);
$mean = $gen->getExpectedValue();      // Weighted mean
$variance = $gen->getVariance();       // Weighted variance
$stdDev = $gen->getStandardDeviation(); // Standard deviation
```

### Decay/Boost (Dynamic Weight Adjustment)

```php
// Manual weight adjustment
$gen->decayWeight('common', 0.8);  // Reduce weight to 80%
$gen->boostWeight('rare', 1.5);    // Increase weight by 50%

// Adjust all weights
$gen->decayAllWeights(0.9);  // Reduce all weights to 90%
$gen->boostAllWeights(1.2);  // Increase all weights by 20%

// Automatic adjustment based on selection frequency
$gen->enableSelectionTracking();

// Generate some values...
$gen->generateMultiple(100);

// Auto-adjust: frequently selected values get decayed, rare ones get boosted
$gen->autoAdjustWeights(0.5); // 0.5 = adjustment strength

// View selection counts
$counts = $gen->getSelectionCounts();

// Reset tracking
$gen->resetSelectionCounts();
```

### Composite Generators (Nested/Hierarchical)

```php
// Create a hierarchy of generators
$rareLoot = WeightedRandom::createFloat();
$rareLoot->registerValues(['legendary_sword' => 1.0, 'magic_ring' => 1.0]);

$commonLoot = WeightedRandom::createFloat();
$commonLoot->registerValues(['wooden_sword' => 3.0, 'bread' => 2.0]);

$lootBox = WeightedRandom::createFloat();
$lootBox->registerValue($rareLoot, 0.1);    // 10% chance of rare loot table
$lootBox->registerValue($commonLoot, 0.9);  // 90% chance of common loot table

$item = $lootBox->generate(); // Draws from nested generator
```

## Requirements

- PHP 8.1 – 8.4
- Composer
  Seeded RNG requires PHP **8.2+**. On PHP 8.1, those tests are automatically skipped.

## 🛠 Development
```bash
vendor/bin/phpunit -c phpunit.xml --color
```
GitHub Actions CI runs tests against **PHP 8.1, 8.2, 8.3, 8.4.**

## License
MIT License.

---
## Migration Guide (2.x → 3.x)

WeightedRandom 2.x introduces new features and stricter validation. If you’re upgrading from 1.x, here’s what you need to know:

## What’s New
- **Float weight support** → Use 0.7 vs 0.3 without scaling up to integers.
- Seeded RNG with namespace isolation (PHP ≥ 8.2) → Deterministic, reproducible draws.
- Chaining API for cleaner code.
- Probability helpers:
  - `normalizeWeights()` → normalized distribution.
  - `getProbability($value)` → single-value probability.
- **Bag System** (v2.2+) → fairness via without-replacement draws.
- **Stricter validation** → safer, more predictable behavior.


# Roadmap
1. ~~Floats + Normalization~~
2. ~~Validation Enhancements~~
3. ~~Chaining API~~
4. ~~Groups~~
5. ~~Seeded RNG~~
6. ~~Distribution Introspection~~
7. ~~Bag System~~
8. ~~Decay/Boost~~
9. ~~Composite Generators~~
10. ~~Code coverage and proper testing~~ 
