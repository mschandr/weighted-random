# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **Distribution Introspection** - Advanced statistical analysis methods:
  - `getDistribution()` - Get value-to-probability mapping
  - `getEntropy()` - Calculate Shannon entropy of distribution
  - `getExpectedValue()` - Calculate weighted mean for numeric values
  - `getVariance()` - Calculate variance for numeric values
  - `getStandardDeviation()` - Calculate standard deviation for numeric values

- **Decay/Boost** - Dynamic weight adjustment system:
  - Manual adjustment: `decayWeight()`, `boostWeight()`, `decayAllWeights()`, `boostAllWeights()`
  - Automatic adjustment: `enableSelectionTracking()`, `autoAdjustWeights()`
  - Selection tracking: `getSelectionCounts()`, `resetSelectionCounts()`

- **Composite Generators** - Hierarchical generator support:
  - Register generators as values for nested/composite structures
  - Supports multi-level hierarchies
  - Works with both float and bag generators

- Comprehensive usage documentation in README
- 28 new test cases for new features (100% code coverage maintained)

## [3.0.0-alpha] - 2024-10-01

### Changed
- **BREAKING**: Complete package restructure
- Namespace reorganization for better code organization
- Enhanced test suite with comprehensive coverage
- Updated license

### Added
- 100% code coverage achieved
- Improved validation and error handling
- Enhanced documentation

## [2.2.0] - Previous Release

### Added
- **Bag System** - Fair distribution using bag/urn model
  - `WeightedBagRandomGenerator` for draw-without-replacement
  - Guarantees exact weight distribution over complete cycles
  - `WeightedRandom::createBag()` factory method

### Changed
- Improved test coverage for bag functionality

## [2.1.0] - Previous Release

### Added
- **Weighted Groups** - Multiple values sharing a single weight
  - `registerGroup(array $values, float $weight)` method
  - `WeightedGroup` value object
  - Uniform random selection within groups

## [2.0.1] - Previous Release

### Fixed
- PHP 8.2 compatibility improvements
- Test annotations for version-specific features
- CI/CD workflow enhancements

## [2.0.0] - Previous Release

### Added
- **Float Weight Support** - Use decimal weights (e.g., 0.7 vs 0.3)
- **Seeded RNG** - Deterministic, reproducible random generation (PHP 8.2+)
- **Chaining API** - Fluent interface for method chaining
- **Probability Introspection**:
  - `normalizeWeights()` - Get normalized probability distribution
  - `getProbability($value)` - Get probability of specific value
  - `getWeightedValues()` - Iterator of WeightedValue objects

### Changed
- **BREAKING**: Stricter validation
  - Weights must be > 0
  - Empty value sets throw exceptions
  - Total weight must be > 0
- Improved error messages
- Enhanced type safety

## [1.3] - Previous Release

### Changed
- Basic weighted random functionality
- Integer weights only

## [1.2] - Previous Release

### Changed
- Improvements and bug fixes

## [1.1] - Initial Release

### Added
- Basic weighted random value selection
- Simple API for registering values with weights
- `generate()` method for random selection

---

## Migration Guides

### Migrating from 2.x to 3.x

The 3.x release maintains backward compatibility with 2.x API while adding new features:

**What's New:**
- Distribution introspection methods for statistical analysis
- Decay/boost system for dynamic weight adjustment
- Composite generators for hierarchical structures

**No Breaking Changes:**
- All existing 2.x code continues to work
- New features are opt-in additions

### Migrating from 1.x to 2.x

**Breaking Changes:**
1. Stricter validation - weights must be > 0
2. Total weight must be > 0 before generation
3. Empty value sets throw exceptions instead of returning null

**New Features:**
- Float weights supported
- Method chaining: `$gen->registerValue('a', 1)->registerValue('b', 2)`
- Probability helpers: `normalizeWeights()`, `getProbability()`
- Groups: `registerGroup(['x', 'y'], 5.0)`
- Bag system: `WeightedRandom::createBag()`

**Migration Steps:**
1. Ensure all weights are > 0
2. Check for empty value set handling
3. Update error handling for stricter exceptions
4. Optional: Switch to chaining API for cleaner code
5. Optional: Use float weights instead of scaling to integers
