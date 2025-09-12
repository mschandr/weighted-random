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
6. Distribution Introspection
7. ~~Bag System~~
8. Decay/Boost
9. Composite Generators
