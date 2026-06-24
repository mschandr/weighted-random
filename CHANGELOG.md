# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

---

## [4.0.0] - 2026-06-23

### Added
- **JSON HTTP API** — stateless HTTP layer over the library (`app/` namespace):
  - `POST /v1/generate` — draw one or more weighted-random samples
  - `POST /v1/distribution` — inspect probabilities and statistics
  - `GET /v1/openapi.json` — OpenAPI 3.1 description of the API
  - `GET /health` — liveness/readiness probe
- **Docker support** — production-ready `Dockerfile` and `docker-compose.yml`:
  - Multi-stage build with optimised autoloader
  - Runs as an unprivileged user
  - Configurable port via `PORT` env var (default `8080`)
  - `/health` `HEALTHCHECK` built in
  - No dev dependencies in the production image
- **Docker test suite** — `ApiDockerTest` and `ApiIntegrationTest` covering the full HTTP stack
- **`composer test:docker`** — separate command to run Docker-dependent tests
- **PHP 8.5 support** — CI matrix extended to include 8.5; removed deprecated `setAccessible()` calls throughout the test suite

### Fixed
- `generateMultipleWithoutDuplicates` — `maxAttempts` now scales with `count` (`count × factor`) instead of using the raw factor as a flat cap, preventing flaky failures under unfavourable RNG sequences

### Changed
- **Minimum PHP version raised to 8.3** — PHP 8.1 and 8.2 are no longer supported

### Breaking Changes
- PHP 8.1 and 8.2 are no longer supported

---

## [3.0.0] - 2025-12-26

### Added
- **Distribution introspection**:
  - `getDistribution()` — normalized value-to-probability mapping
  - `getProbability(mixed $value)` — probability of a single value
  - `getEntropy()` — Shannon entropy of the distribution
  - `getExpectedValue()` — weighted mean for numeric values
  - `getVariance()` — weighted variance for numeric values
  - `getStandardDeviation()` — standard deviation for numeric values
- **Decay / boost** — dynamic weight adjustment:
  - `decayWeight(mixed $value, float $factor)` — reduce a single weight
  - `boostWeight(mixed $value, float $factor)` — increase a single weight
  - `decayAllWeights(float $factor)` — apply decay to every value
  - `boostAllWeights(float $factor)` — apply boost to every value
- **Selection tracking and auto-adjustment**:
  - `enableSelectionTracking()` — record how often each value is drawn
  - `getSelectionCounts()` — inspect per-value draw counts
  - `resetSelectionCounts()` — reset counts without disabling tracking
  - `autoAdjustWeights(float $strength)` — decay over-selected values and boost under-selected ones automatically
- **Composite generators** — register a generator instance as a value; when drawn it produces the final result, enabling hierarchical loot tables and multi-tier probability trees
- **100% code coverage** — full PHPUnit suite with coverage enforcement
- `API.md`, `CHANGELOG.md`, `CONTRIBUTING.md` added to the repository

### Changed
- Complete package restructure — `lib/` replaced by `src/` with PSR-4 namespaces (`mschandr\WeightedRandom\`)
- `WeightedRandomInterface` introduced as the common contract for all generators
- `WeightedRandom` facade with `createFloat()` and `createBag()` factory methods
- Minimum PHP version raised to 8.3 (8.1 and 8.2 dropped in 4.0.0; 8.3 enforced from here)
- Travis CI replaced with GitHub Actions

### Breaking Changes
- Old `lib/` namespace (`WeightedRandom`, `WeightedRandomGenerator`, etc.) removed
- All classes now live under `mschandr\WeightedRandom\`

---

## [2.2.0] - 2025-09-11

### Added
- **Bag generator** — draw-without-replacement urn model via `WeightedBagRandomGenerator`
  - Guarantees exact weight ratios over a complete cycle
  - `WeightedRandom::createBag()` factory method

---

## [2.1.0] - 2025-09-10

### Added
- **Weighted groups** — assign one weight to a set of values via `registerGroup(array $values, float $weight)`
  - `WeightedGroup` value object
  - When selected, one member is chosen uniformly at random

---

## [2.0.1] - 2025-09-10

### Fixed
- PHP 8.2 compatibility improvements
- CI workflow enhancements

---

## [2.0.0] - 2025-09-10

### Added
- Float weight support — weights can be any positive float
- Chaining API — `registerValue` and `registerValues` return `$this`
- `normalizeWeights()` — returns the normalized probability distribution
- `getProbability(mixed $value)` — probability of a specific value
- `getWeightedValues()` — iterator of `WeightedValue` instances
- GitHub Actions CI

### Changed
- **BREAKING**: Weights must be `> 0`; zero or negative weights throw immediately
- **BREAKING**: Total weight must be `> 0` before calling `generate()`
- **BREAKING**: Empty value sets throw an exception

---

## [1.3] - 2025-09-06

### Changed
- README and documentation updates
- Composer and CI improvements

---

## [1.2] - 2024-06-14

### Changed
- Composer configuration fixes
- Minor improvements

---

## [1.1] - 2024-06-14

### Added
- Initial release
- Weighted random value selection with integer weights
- `generate()` for single draws
- Basic PHPUnit test suite

---

## Migration Guides

### 3.x → 4.0

- Raise your PHP version to **8.3 or higher**
- No API changes — all existing library code continues to work
- Docker and HTTP API are opt-in additions

### 2.x → 3.0

- Update namespace from `lib/` to `mschandr\WeightedRandom\`:

```php
// 2.x
use WeightedRandom;

// 3.x
use mschandr\WeightedRandom\WeightedRandom;
```

- Replace direct instantiation with the facade:

```php
// 2.x
$gen = new WeightedRandomGenerator();

// 3.x
$gen = WeightedRandom::createFloat();
```

### 1.x → 2.0

- Ensure all weights are `> 0` — zero weights now throw immediately
- Add error handling for empty value sets
- Optionally switch to float weights and the chaining API:

```php
// 1.x
$gen->add('rare', 1);
$gen->add('common', 9);
$result = $gen->pick();

// 2.x
$gen->registerValues(['rare' => 1.0, 'common' => 9.0]);
$result = $gen->generate();
```
