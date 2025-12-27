# API Reference

Complete API documentation for the Weighted Random library.

## Table of Contents

- [Factory (WeightedRandom)](#factory-weightedrandom)
- [WeightedRandomInterface](#weightedrandominterface)
- [WeightedRandomGenerator](#weightedrandomgenerator)
- [WeightedBagRandomGenerator](#weightedbagrandomgenerator)
- [Value Objects](#value-objects)
  - [WeightedValue](#weightedvalue)
  - [WeightedGroup](#weightedgroup)

---

## Factory (WeightedRandom)

**Namespace:** `mschandr\WeightedRandom`

Static factory class for creating generator instances.

### Methods

#### `createFloat(): WeightedRandomGenerator`

Creates a new float-based weighted random generator.

**Returns:** `WeightedRandomGenerator` instance

**Example:**
```php
$gen = WeightedRandom::createFloat();
```

---

#### `createBag(): WeightedBagRandomGenerator`

Creates a new bag-based weighted random generator (fair distribution).

**Returns:** `WeightedBagRandomGenerator` instance

**Example:**
```php
$bag = WeightedRandom::createBag();
```

---

## WeightedRandomInterface

**Namespace:** `mschandr\WeightedRandom\Contract`

Interface defining the contract for all weighted random generators.

### Registration Methods

#### `registerValue(mixed $value, float $weight): self`

Register a single value with its weight.

**Parameters:**
- `$value` (mixed) - The value to register
- `$weight` (float) - The weight (must be > 0)

**Returns:** `self` (for method chaining)

**Throws:**
- `InvalidArgumentException` - If weight ≤ 0

**Example:**
```php
$gen->registerValue('apple', 3.5);
```

---

#### `registerValues(array $valueCollection): self`

Register multiple values at once.

**Parameters:**
- `$valueCollection` (array) - Associative array of value => weight pairs

**Returns:** `self` (for method chaining)

**Throws:**
- `InvalidArgumentException` - If any weight is not numeric or ≤ 0

**Example:**
```php
$gen->registerValues([
    'apple'  => 3.0,
    'banana' => 2.0,
    'cherry' => 1.0,
]);
```

---

#### `registerGroup(array $values, float $weight): self`

Register a group of values that share a single weight.

**Parameters:**
- `$values` (array) - Array of values in the group
- `$weight` (float) - The shared weight (must be > 0)

**Returns:** `self` (for method chaining)

**Throws:**
- `InvalidArgumentException` - If weight ≤ 0 or values array is empty

**Example:**
```php
$gen->registerGroup(['bronze', 'silver', 'gold'], 5.0);
```

---

### Generation Methods

#### `generate(): mixed`

Generate a single random value based on weights.

**Returns:** (mixed) A randomly selected value

**Throws:**
- `RuntimeException` - If no values registered or total weight ≤ 0

**Example:**
```php
$result = $gen->generate();
```

---

#### `generateMultiple(int $count): iterable`

Generate multiple random values (may include duplicates).

**Parameters:**
- `$count` (int) - Number of values to generate (must be > 0)

**Returns:** (iterable) Generator yielding random values

**Throws:**
- `InvalidArgumentException` - If count ≤ 0

**Example:**
```php
$results = $gen->generateMultiple(10);
foreach ($results as $value) {
    echo $value;
}
```

---

#### `generateMultipleWithoutDuplicates(int $count): iterable`

Generate multiple unique random values (no duplicates).

**Parameters:**
- `$count` (int) - Number of unique values to generate

**Returns:** (iterable) Generator yielding unique random values

**Throws:**
- `InvalidArgumentException` - If count ≤ 0 or count > registered values
- `RuntimeException` - If unable to generate enough unique values

**Example:**
```php
$unique = $gen->generateMultipleWithoutDuplicates(5);
```

---

## WeightedRandomGenerator

**Namespace:** `mschandr\WeightedRandom\Generator`

**Implements:** `WeightedRandomInterface`

Classic probabilistic weighted random generator.

### Core Methods

All methods from `WeightedRandomInterface` plus:

#### `setMaxAttemptsFactor(int $factor): void`

Set the maximum attempts multiplier for duplicate-free generation.

**Parameters:**
- `$factor` (int) - Multiplier for max attempts

**Example:**
```php
$gen->setMaxAttemptsFactor(20);
```

---

### Distribution Introspection Methods

#### `getWeightedValues(): Generator`

Get all registered values as WeightedValue objects.

**Returns:** `Generator<WeightedValue>`

**Example:**
```php
foreach ($gen->getWeightedValues() as $weightedValue) {
    echo $weightedValue->getValue() . ': ' . $weightedValue->getWeight();
}
```

---

#### `normalizeWeights(): array`

Get normalized weights (sum = 1.0) indexed by internal keys.

**Returns:** `array<int,float>` - Array of normalized weights

**Throws:**
- `RuntimeException` - If total weight ≤ 0

**Example:**
```php
$normalized = $gen->normalizeWeights();
// [0 => 0.5, 1 => 0.3, 2 => 0.2]
```

---

#### `getProbability(mixed $value): float`

Get the probability of a specific value being selected.

**Parameters:**
- `$value` (mixed) - The value to check

**Returns:** (float) Probability between 0.0 and 1.0

**Throws:**
- `InvalidArgumentException` - If value not registered

**Example:**
```php
$prob = $gen->getProbability('apple'); // 0.5
```

---

#### `getDistribution(): array`

Get distribution as value => probability mapping.

**Returns:** `array<mixed,float>` - Map of values to probabilities

**Example:**
```php
$dist = $gen->getDistribution();
// ['apple' => 0.5, 'banana' => 0.3, 'cherry' => 0.2]
```

---

#### `getEntropy(): float`

Calculate Shannon entropy of the distribution.

Higher entropy = more uniform. Range: 0 (one value) to log₂(n) (uniform).

**Returns:** (float) Entropy value

**Example:**
```php
$entropy = $gen->getEntropy(); // 1.5
```

---

#### `getExpectedValue(): ?float`

Calculate expected value (weighted mean) for numeric values.

**Returns:** (float|null) Expected value, or null if no numeric values

**Example:**
```php
$gen->registerValues([1 => 1.0, 2 => 2.0, 3 => 1.0]);
$mean = $gen->getExpectedValue(); // 2.0
```

---

#### `getVariance(): ?float`

Calculate variance for numeric values.

**Returns:** (float|null) Variance, or null if no numeric values

**Example:**
```php
$variance = $gen->getVariance(); // 0.667
```

---

#### `getStandardDeviation(): ?float`

Calculate standard deviation for numeric values.

**Returns:** (float|null) Standard deviation, or null if no numeric values

**Example:**
```php
$stdDev = $gen->getStandardDeviation(); // 0.816
```

---

### Decay/Boost Methods

#### `enableSelectionTracking(bool $enable = true): self`

Enable or disable tracking of how many times each value is selected.

**Parameters:**
- `$enable` (bool) - True to enable, false to disable (default: true)

**Returns:** `self` (for method chaining)

**Example:**
```php
$gen->enableSelectionTracking();
```

---

#### `getSelectionCounts(): array`

Get selection counts for all values.

**Returns:** `array<int,int>` - Map of value keys to selection counts

**Example:**
```php
$counts = $gen->getSelectionCounts();
// [0 => 45, 1 => 32, 2 => 23]
```

---

#### `resetSelectionCounts(): self`

Reset all selection counts to zero.

**Returns:** `self` (for method chaining)

**Example:**
```php
$gen->resetSelectionCounts();
```

---

#### `decayWeight(mixed $value, float $factor): self`

Manually reduce the weight of a specific value.

**Parameters:**
- `$value` (mixed) - The value to decay
- `$factor` (float) - Decay factor (0.0 < factor ≤ 1.0). E.g., 0.9 = reduce to 90%

**Returns:** `self` (for method chaining)

**Throws:**
- `InvalidArgumentException` - If value not registered or factor invalid
- `RuntimeException` - If decayed weight would be ≤ 0

**Example:**
```php
$gen->decayWeight('common', 0.8); // Reduce to 80% of current weight
```

---

#### `boostWeight(mixed $value, float $factor): self`

Manually increase the weight of a specific value.

**Parameters:**
- `$value` (mixed) - The value to boost
- `$factor` (float) - Boost factor (≥ 1.0). E.g., 1.5 = increase by 50%

**Returns:** `self` (for method chaining)

**Throws:**
- `InvalidArgumentException` - If value not registered or factor invalid

**Example:**
```php
$gen->boostWeight('rare', 1.5); // Increase by 50%
```

---

#### `decayAllWeights(float $factor): self`

Apply decay factor to all registered weights.

**Parameters:**
- `$factor` (float) - Decay factor (0.0 < factor ≤ 1.0)

**Returns:** `self` (for method chaining)

**Example:**
```php
$gen->decayAllWeights(0.9); // Reduce all weights to 90%
```

---

#### `boostAllWeights(float $factor): self`

Apply boost factor to all registered weights.

**Parameters:**
- `$factor` (float) - Boost factor (≥ 1.0)

**Returns:** `self` (for method chaining)

**Example:**
```php
$gen->boostAllWeights(1.2); // Increase all weights by 20%
```

---

#### `autoAdjustWeights(float $strength = 0.5): self`

Automatically adjust weights based on selection frequency.

Values selected more often than average get decayed. Values selected less often get boosted.

**Parameters:**
- `$strength` (float) - Adjustment strength (0.0 < strength ≤ 1.0). Higher = more aggressive

**Returns:** `self` (for method chaining)

**Throws:**
- `InvalidArgumentException` - If strength invalid

**Example:**
```php
$gen->enableSelectionTracking();
// ... generate values ...
$gen->autoAdjustWeights(0.5); // Balance distribution
```

---

## WeightedBagRandomGenerator

**Namespace:** `mschandr\WeightedRandom\Generator`

**Implements:** `WeightedRandomInterface`

Bag/urn model generator for fair distribution. Expands weights into a bag, shuffles, and draws without replacement until exhausted.

### Methods

All methods from `WeightedRandomInterface`.

**Key Behavior Differences:**

1. **Fair distribution** - Guarantees exact weight ratios over complete cycles
2. **No duplicates within cycle** - `generateMultipleWithoutDuplicates()` simply calls `generateMultiple()`
3. **Bag refills** - When exhausted, bag is refilled and reshuffled

**Example:**
```php
$bag = WeightedRandom::createBag();
$bag->registerValues(['rare' => 1, 'common' => 9]);

// Over 10 draws: exactly 1 rare, 9 common
$results = $bag->generateMultiple(10);
```

**Note:** Decay/boost and introspection methods are available through the underlying base generator if needed, but the bag model itself doesn't expose them directly.

---

## Value Objects

### WeightedValue

**Namespace:** `mschandr\WeightedRandom\Value`

Immutable value object representing a value-weight pair.

#### Constructor

```php
public function __construct(mixed $value, float $weight)
```

**Parameters:**
- `$value` (mixed) - The value
- `$weight` (float) - The weight (must be > 0)

**Throws:**
- `InvalidArgumentException` - If weight ≤ 0

---

#### `getValue(): mixed`

Get the stored value.

**Returns:** (mixed) The value

---

#### `getWeight(): float`

Get the weight.

**Returns:** (float) The weight

---

#### `getArrayCopy(): array`

Export to array format.

**Returns:** `array{value: mixed, weight: float}`

**Example:**
```php
$wv = new WeightedValue('apple', 3.0);
$array = $wv->getArrayCopy();
// ['value' => 'apple', 'weight' => 3.0]
```

---

### WeightedGroup

**Namespace:** `mschandr\WeightedRandom\Value`

Represents multiple values sharing a single weight.

#### Constructor

```php
public function __construct(array $values)
```

**Parameters:**
- `$values` (array) - Array of group members

**Throws:**
- `InvalidArgumentException` - If array is empty

---

#### `pickOne(): mixed`

Randomly select one member from the group uniformly.

**Returns:** (mixed) A random group member

**Example:**
```php
$group = new WeightedGroup(['bronze', 'silver', 'gold']);
$item = $group->pickOne(); // Uniform random selection
```

---

#### `getMembers(): array`

Get all members of the group.

**Returns:** (array) Array of members

---

#### `countMembers(): int`

Get the number of members in the group.

**Returns:** (int) Member count

---

## Composite Generators

Both `WeightedRandomGenerator` and `WeightedBagRandomGenerator` support composite/nested generators.

**How it works:**
- Register a generator instance as a value
- When that generator is selected, it automatically calls `generate()` on the nested generator
- Supports multi-level hierarchies

**Example:**
```php
$rareLoot = WeightedRandom::createFloat();
$rareLoot->registerValues(['sword' => 1.0, 'ring' => 1.0]);

$commonLoot = WeightedRandom::createFloat();
$commonLoot->registerValues(['bread' => 3.0, 'stick' => 2.0]);

$lootBox = WeightedRandom::createFloat();
$lootBox->registerValue($rareLoot, 0.1);   // 10% rare
$lootBox->registerValue($commonLoot, 0.9); // 90% common

$item = $lootBox->generate(); // Gets item from nested generator
```

---

## Exceptions

### InvalidArgumentException

Thrown when invalid parameters are provided:
- Weight ≤ 0
- Empty value arrays
- Non-numeric weights
- Invalid decay/boost factors
- Value not registered

### RuntimeException

Thrown during generation errors:
- No values registered
- Total weight ≤ 0
- Unable to generate unique values
- Cannot refill bag

---

## Type Information

The library uses strict typing throughout:

- `mixed` - Any value type (strings, objects, arrays, etc.)
- `float` - Weights and probabilities (always floats, integers auto-converted)
- `int` - Counts and indices
- `bool` - Flags
- `iterable` / `Generator` - For lazy iteration
- `self` - For method chaining

All code uses `declare(strict_types=1)` for type safety.
