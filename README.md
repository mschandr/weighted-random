# Weighted Random

[![PHPUnit Tests](https://github.com/mschandr/weighted-random/actions/workflows/php.yml/badge.svg)](https://github.com/mschandr/weighted-random/actions/workflows/php.yml)
[![codecov](https://codecov.io/github/mschandr/weighted-random/graph/badge.svg?token=4J70DHYFTK)](https://codecov.io/github/mschandr/weighted-random)
[![Latest Stable Version](https://img.shields.io/packagist/v/mschandr/weighted-random.svg)](https://packagist.org/packages/mschandr/weighted-random)
[![License](https://img.shields.io/github/license/mschandr/weighted-random.svg)](LICENSE)

A PHP library for picking random values from a weighted set. Values with a higher weight have a greater probability of being selected. Ships with two generator models, distribution introspection, dynamic weight adjustment, composite generators, and a containerised JSON HTTP API.

---

## Installation

```bash
composer require mschandr/weighted-random
```

**Requirements:** PHP 8.2+. CI runs against PHP 8.3, 8.4, and 8.5.

---

## Documentation

- **[API Reference](API.md)** — Complete class and method reference
- **[HTTP API & Docker](HTTP_API.md)** — Run the library as a containerised JSON HTTP service
- **[Roadmap](ROADMAP.md)** — Planned features and priority order
- **[CHANGELOG](CHANGELOG.md)** — Version history and migration guides
- **[Contributing Guide](CONTRIBUTING.md)** — Guidelines for contributing

---

## Quick Start

```php
use mschandr\WeightedRandom\WeightedRandom;

$gen = WeightedRandom::createFloat();

$gen->registerValue('common',   7.0)
    ->registerValue('uncommon', 2.5)
    ->registerValue('rare',     0.5);

$result  = $gen->generate();           // single draw
$results = $gen->generateMultiple(10); // 10 draws
```

---

## Generator Models

### Float Generator (probabilistic)

The default model. Each call draws independently according to the registered weights.

```php
$gen = WeightedRandom::createFloat();
```

### Bag Generator (exact ratios)

An urn/bag model. Values are drawn without replacement until the bag is exhausted, then it refills. Over a complete cycle the ratio of results exactly matches the weights — useful when you need guaranteed distribution rather than statistical approximation.

```php
$bag = WeightedRandom::createBag();
$bag->registerValues(['rare' => 1, 'common' => 9]);

$results = $bag->generateMultiple(10);
// exactly 1 "rare" and 9 "common", then the bag resets
```

---

## Registering Values

### Single value

```php
$gen->registerValue('legendary', 0.5);
```

Registering the same value a second time replaces its weight.

### Batch registration

```php
$gen->registerValues([
    'apple'  => 3.0,
    'banana' => 2.0,
    'cherry' => 1.0,
]);
```

### Groups

Assign one weight to a set of values. When the group is selected, one member is chosen uniformly at random.

```php
$gen->registerGroup(['bronze', 'silver', 'gold'], 5.0);
```

---

## Generating Values

```php
// Single draw
$value = $gen->generate();

// Multiple draws (may repeat)
$values = $gen->generateMultiple(10);

// Multiple draws with no duplicates
$unique = $gen->generateMultipleWithoutDuplicates(3);
```

`generateMultipleWithoutDuplicates` throws `RuntimeException` if it cannot satisfy the uniqueness constraint within the attempt budget. The default budget is `count × 10` attempts; raise it with:

```php
$gen->setMaxAttemptsFactor(50); // count × 50 attempts
```

---

## Distribution Introspection

```php
// Full normalized distribution
$distribution = $gen->getDistribution();
// ['apple' => 0.5, 'banana' => 0.333, 'cherry' => 0.167]

// Probability of a single value
$prob = $gen->getProbability('apple'); // 0.5

// Shannon entropy — higher = more evenly spread
$entropy = $gen->getEntropy();
```

For generators whose values are all numeric, statistical helpers are available:

```php
$gen->registerValues([1 => 1.0, 2 => 2.0, 3 => 1.0]);

$mean   = $gen->getExpectedValue();     // weighted mean
$var    = $gen->getVariance();          // weighted variance
$stdDev = $gen->getStandardDeviation(); // standard deviation
```

These return `null` when any registered value is non-numeric.

---

## Dynamic Weight Adjustment

### Manual decay and boost

```php
$gen->decayWeight('common', 0.8);  // multiply weight by 0.8
$gen->boostWeight('rare',   1.5);  // multiply weight by 1.5

$gen->decayAllWeights(0.9);        // apply to every value
$gen->boostAllWeights(1.2);
```

Decay throws `RuntimeException` if the resulting weight reaches zero.

### Selection tracking and auto-adjustment

Enable tracking to record how often each value is drawn, then let the generator balance itself automatically.

```php
$gen->enableSelectionTracking();

$gen->generateMultiple(100);

// Values picked more often get decayed; under-picked values get boosted.
// strength: 0.0 = no change, 1.0 = full correction
$gen->autoAdjustWeights(0.5);

// Inspect counts
$counts = $gen->getSelectionCounts(); // [0 => 73, 1 => 15, 2 => 12]

// Reset without disabling tracking
$gen->resetSelectionCounts();
```

---

## Composite Generators

Register a generator instance as a value. When that entry is drawn, the nested generator produces the final result. Nesting can go as deep as needed.

```php
$rareLoot = WeightedRandom::createFloat();
$rareLoot->registerValues(['legendary_sword' => 1.0, 'magic_ring' => 1.0]);

$commonLoot = WeightedRandom::createFloat();
$commonLoot->registerValues(['wooden_sword' => 3.0, 'bread' => 2.0]);

$lootBox = WeightedRandom::createFloat();
$lootBox->registerValue($rareLoot,   0.1); // 10% → rare table
$lootBox->registerValue($commonLoot, 0.9); // 90% → common table

$item = $lootBox->generate();
```

---

## HTTP API

The library ships with a stateless JSON HTTP API and a production-ready `Dockerfile`. Every request fully describes the weighted set — no server-side state is kept between calls.

### Running with Docker

```bash
# Docker Compose (recommended)
docker compose up --build
# serves on http://localhost:8080

# Or build and run directly
docker build -t weighted-random-api .
docker run --rm -p 8080:8080 weighted-random-api

# Override the port
docker run --rm -p 9090:9090 -e PORT=9090 weighted-random-api
```

The container:
- runs as an unprivileged user
- exposes port `8080` by default (override with `PORT` env var)
- defines a `/health` `HEALTHCHECK`
- ships only production dependencies (no dev packages)

### Running without Docker

```bash
composer install
php -S 0.0.0.0:8080 -t public public/index.php
```

### Endpoints

| Method | Path               | Description                               |
|--------|--------------------|-------------------------------------------|
| GET    | `/health`          | Liveness/readiness probe                  |
| POST   | `/v1/generate`     | Draw one or more weighted-random samples  |
| POST   | `/v1/distribution` | Inspect probabilities and statistics      |
| GET    | `/v1/openapi.json` | OpenAPI 3.1 description of the API        |

### `POST /v1/generate`

```bash
curl -s localhost:8080/v1/generate \
  -H 'Content-Type: application/json' \
  -d '{
    "generator": "float",
    "values": { "common": 7, "uncommon": 2.5, "rare": 0.5 },
    "count": 5
  }'
```

```json
{
  "generator": "float",
  "unique": false,
  "count": 5,
  "results": ["common", "rare", "common", "uncommon", "common"]
}
```

Supply values via `values` (string-keyed map), `items` (typed list), or `groups` — or mix all three:

```json
{
  "values":  { "common": 7 },
  "items":   [{ "value": 42, "weight": 2 }],
  "groups":  [{ "members": ["bronze", "silver"], "weight": 1 }],
  "count":   10,
  "unique":  false,
  "generator": "bag"
}
```

| Field       | Type    | Default | Notes                                           |
|-------------|---------|---------|-------------------------------------------------|
| `generator` | string  | `float` | `float` or `bag`                                |
| `count`     | integer | `1`     | Number of samples (1 – 100 000)                 |
| `unique`    | boolean | `false` | No duplicates in the result                     |

### `POST /v1/distribution`

```bash
curl -s localhost:8080/v1/distribution \
  -H 'Content-Type: application/json' \
  -d '{ "values": { "1": 1, "2": 2, "3": 1 } }'
```

```json
{
  "totalValues": 3,
  "distribution": [
    { "value": 1, "probability": 0.25 },
    { "value": 2, "probability": 0.5 },
    { "value": 3, "probability": 0.25 }
  ],
  "entropy": 1.5,
  "expectedValue": 2.0,
  "variance": 0.5,
  "standardDeviation": 0.7071067811865476
}
```

### Error responses

All errors return JSON with an `error` key.

| Status | Meaning                                              |
|--------|------------------------------------------------------|
| `400`  | Malformed JSON body                                  |
| `404`  | Unknown route                                        |
| `422`  | Validation error (bad weight, unknown generator, …)  |
| `500`  | Unexpected server error                              |

---

## Development

```bash
composer install

# Unit and integration tests (excludes Docker tests)
composer test

# Full suite including Docker container tests
composer test:docker
```

Tests run against PHP 8.3, 8.4, and 8.5 in CI via GitHub Actions.

---

## Migration Guide (2.x → 3.x)

### What changed

- **Float weights** — weights can now be any positive float; no need to scale to integers.
- **Chaining API** — `registerValue` and `registerValues` return `$this`.
- **Stricter validation** — invalid weights throw immediately rather than silently doing nothing.
- **Bag generator** — `WeightedRandom::createBag()` for exact-ratio draws.
- **Distribution introspection** — `getDistribution()`, `getProbability()`, `getEntropy()`, `getExpectedValue()`, `getVariance()`, `getStandardDeviation()`.
- **Decay / boost** — `decayWeight()`, `boostWeight()`, `decayAllWeights()`, `boostAllWeights()`.
- **Selection tracking** — `enableSelectionTracking()`, `getSelectionCounts()`, `autoAdjustWeights()`.
- **Composite generators** — register a generator instance as a value for hierarchical draws.
- **HTTP API** — run the library as a containerised JSON service.

### Quick diff

```php
// 2.x
$gen->add('rare', 1);
$gen->add('common', 9);
$result = $gen->pick();

// 3.x
$gen = WeightedRandom::createFloat();
$gen->registerValues(['rare' => 1.0, 'common' => 9.0]);
$result = $gen->generate();
```

---

## License

MIT — see [LICENSE](LICENSE).
