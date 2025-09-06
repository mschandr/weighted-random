# Weighted Random

[![PHPUnit Tests](https://github.com/mschandr/weighted-random/actions/workflows/php.yml/badge.svg)](https://github.com/mschandr/weighted-random/actions/workflows/php.yml)
[![Latest Stable Version](https://img.shields.io/packagist/v/mschandr/weighted-random.svg)](https://packagist.org/packages/mschandr/weighted-random)
[![License](https://img.shields.io/github/license/mschandr/weighted-random.svg)](LICENSE)

This library is used to pick random values from a set of registered values, where values with a higher
weight have a larger probability to be picked.

---

## Installing

Install this library using Composer:

```bash
composer require mschandr/weighted-random
```

## Getting started
Using this library is simple: instantiate the `WeightedRandomGenerator`, register values, and start generating random values.

```php
<?php
use mschandr\WeightedRandom\WeightedRandomGenerator;

// Initiate the generator
$generator = new WeightedRandomGenerator();

// Register some value
$generator->registerValue('foobar', 5);

// And get a random value
echo $generator->generate() . PHP_EOL;
```

### Registering values
You can register values with associated weights.
Higher weights mean a higher probability of being picked.

```php
<?php
use mschandr\WeightedRandom\WeightedRandomGenerator;

$generator = new WeightedRandomGenerator();

// Register with weight 5
$generator->registerValue('foobar', 5);

// Override with weight 1
$generator->registerValue('foobar', 1);

// Throws \InvalidArgumentException (weight must be > 0)
$generator->registerValue('foobar', 0);
```
Register multiple values at once with `registerValues()`:

```php
<?php
$generator->registerValues([
    'foo' => 1,
    'bar' => 2,
]);

// Override bar, foo stays the same
$generator->registerValues([
    'bar' => 1,
]);

// Throws exception for weight 0
$generator->registerValues([
    'bar' => 0,
]);
```
Remove values with `removeValue()`:

```php
<?php
$generator->registerValue('foobar', 2);
$generator->removeValue('foobar');
```

You can also use the `WeightedValue` object with `registerWeightedValue()` and `removeWeightedValue()`:

```php
<?php
use mschandr\WeightedRandom\WeightedValue;

$weightedValue = new WeightedValue('foobar', 2);
$generator->registerWeightedValue($weightedValue);
$generator->removeWeightedValue($weightedValue);
```

## Retrieving registered values
Registered values are always returned as `WeightedValue` objects.

```php
<?php
$generator->registerValues([
    'foo' => 2,
    'bar' => 3,
]);

// Retrieve all values
foreach ($generator->getWeightedValues() as $weightedValue) {
    echo sprintf('%s => %s', $weightedValue->getValue(), $weightedValue->getWeight()) . PHP_EOL;
}

// Retrieve one by value
$weightedValue = $generator->getWeightedValue('foo');
echo $weightedValue->getWeight() . PHP_EOL;
```

## Generating a random sample
Single value

```php
<?php
$generator->registerValue('foo', 2);
echo $generator->generate() . PHP_EOL;
```
Multiple values (with duplicates allowed):

```php
<?php
foreach ($generator->generateMultiple(2) as $value) {
    echo $value . PHP_EOL;
}
```
Multiple values (no duplicates):
```php
<?php
foreach ($generator->generateMultipleWithoutDuplicates(2) as $value) {
    echo $value . PHP_EOL;
}
```

**Note**: Trying to generate more unique values than registered will throw an `\InvalidArgumentException`.

# Roadmap

1. Floats + Normalization
2. Validation Enhancements
3. Chaining API
4. Groups
5. Seeded RNG
6. Distribution Introspection
7. Bag System
8. Decay/Boost
9. Composite Generators
