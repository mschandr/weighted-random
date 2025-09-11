# Weighted Random

[![PHPUnit Tests](https://github.com/mschandr/weighted-random/actions/workflows/php.yml/badge.svg)](https://github.com/mschandr/weighted-random/actions/workflows/php.yml)
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

## 🚀 Usage

### Registering values
```php
use mschandr\WeightedRandom\WeightedRandomGenerator;

$gen = new WeightedRandomGenerator();

// Register values with integer or float weights
$gen->registerValue('common', 0.7)
    ->registerValue('rare', 0.3);

// Draw a value
echo $gen->generate(); // "common" or "rare"
```
---

### Multiple values at once

```php
$gen = new WeightedRandomGenerator();
$gen->registerValues([
    'apple'  => 70,
    'banana' => 30,
]);

foreach ($gen->generateMultiple(5) as $value) {
    echo $value, PHP_EOL;
}
```
---

### No duplicates

```php
foreach ($gen->generateMultipleWithoutDuplicates(2) as $value) {
    echo $value, PHP_EOL;
}
```
---

### Probability helpers

```php
$gen = new WeightedRandomGenerator();
$gen->registerValues([
    'apple'  => 70,
    'banana' => 30,
]);

print_r($gen->normalizeWeights());
// ['apple' => 0.7, 'banana' => 0.3]

echo $gen->getProbability('banana'); // 0.3
```
### Seeded RNG (PHP ≥ 8.2)

```php
use mschandr\WeightedRandom\WeightedRandom;

$weights = ['a' => 10, 'b' => 5, 'c' => 1];

echo WeightedRandom::pickKeySeeded($weights, 1234, 'stream.alpha');
echo WeightedRandom::pickKeySeeded($weights, 1234, 'stream.beta');
```
---

- Same seed + namespace = reproducible results.
- Different namespaces = independent streams.

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
## Migration Guide (1.x → 2.x)

WeightedRandom 2.x introduces new features and stricter validation. If you’re upgrading from 1.x, here’s what you need to know:

### ⚠️ Breaking Changes
- **Zero or negative weights** are no longer allowed.
```php
// ❌ This will now throw an exception
$gen->registerValue('foo', 0);
```
- **Empty sets** are not permitted.
```php
// ❌ This will now throw
$gen->generate();
```
- **Tests that assumed specific ordering** may fail. Random draws are inherently order-independent — update assertions to check for membership, not sequence.
---
## What’s New
- **Float weight support** → Use 0.7 vs 0.3 without scaling up to integers.
- Seeded RNG with namespace isolation (PHP ≥ 8.2) → Deterministic, reproducible draws.
- Chaining API for cleaner code.
- Probability helpers:
  - `normalizeWeights()` → normalized distribution.
  - `getProbability($value)` → single-value probability.
- **Stricter validation** → safer, more predictable behavior.

## 🛠️ Upgrade Checklist

1. Review your code and remove any weights ≤ 0.
2. If you used floats before by scaling (e.g., 70 vs 30), you can now write them as 0.7 vs 0.3.
3. If you want reproducible randomness, upgrade to PHP 8.2+ and switch to pickKeySeeded().
4. Update tests to allow for order-independent results.


# Roadmap
1. ~~Floats + Normalization~~
2. ~~Validation Enhancements~~
3. ~~Chaining API~~
4. Groups
5. ~~Seeded RNG~~
6. Distribution Introspection
7. Bag System
8. Decay/Boost
9. Composite Generators
