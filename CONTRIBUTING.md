# Contributing to Weighted Random

Thank you for considering contributing to Weighted Random! This document provides guidelines and instructions for contributing to the project.

## Code of Conduct

Please be respectful and constructive in all interactions. We're all here to make this library better.

## How Can I Contribute?

### Reporting Bugs

Before creating a bug report, please check existing issues to avoid duplicates.

**When submitting a bug report, include:**
- PHP version
- Package version
- Minimal code example to reproduce the issue
- Expected vs actual behavior
- Any error messages or stack traces

### Suggesting Features

We welcome feature suggestions! Please:
- Check if the feature already exists or is planned (see README roadmap)
- Explain the use case and why it would benefit users
- Consider backward compatibility

### Pull Requests

1. **Fork the repository** and create your branch from `master`
2. **Make your changes** following the coding standards below
3. **Add tests** - we maintain 100% code coverage
4. **Update documentation** if you're adding/changing features
5. **Run the test suite** to ensure everything passes
6. **Submit a pull request** with a clear description

## Development Setup

### Prerequisites

- PHP 8.2+ (8.1 supported but some features require 8.2+)
- Composer
- Git

### Installation

```bash
# Clone your fork
git clone https://github.com/YOUR_USERNAME/weighted-random.git
cd weighted-random

# Install dependencies
composer install
```

### Running Tests

```bash
# Run all tests
vendor/bin/phpunit

# Run tests with testdox output
vendor/bin/phpunit --testdox

# Run tests with coverage
vendor/bin/phpunit --coverage-html build/coverage
```

**Important:** All tests must pass and coverage must remain at 100%.

## Coding Standards

### PHP Standards

- **PHP 8.2+ features** are allowed (8.1 compatibility where possible)
- **Strict types**: Always use `declare(strict_types=1);`
- **Type hints**: Use strict type hints for all parameters and return values
- **Visibility**: Always declare property and method visibility
- **Final classes**: Use `final` for classes not designed for inheritance

### Code Style

```php
<?php
declare(strict_types=1);

namespace mschandr\WeightedRandom\Example;

final class Example
{
    private string $property;

    public function __construct(string $value)
    {
        $this->property = $value;
    }

    public function doSomething(int $param): bool
    {
        // Implementation
        return true;
    }
}
```

### Documentation

- **PHPDoc blocks** for all public methods
- **Inline comments** for complex logic
- **README updates** for new features
- **CHANGELOG entries** for all changes

### Testing Standards

#### Test Structure

```php
#[CoversClass(YourClass::class)]
final class YourClassTest extends TestCase
{
    public function testMethodDoesExpectedThing(): void
    {
        // Arrange
        $object = new YourClass();

        // Act
        $result = $object->method();

        // Assert
        $this->assertSame('expected', $result);
    }
}
```

#### Testing Guidelines

1. **One concept per test** - Test one thing at a time
2. **Clear test names** - `testMethodNameScenarioExpectedResult`
3. **Arrange-Act-Assert** - Structure tests clearly
4. **Test edge cases** - Not just happy paths
5. **Use proper assertions**:
   - `assertSame()` for strict equality
   - `assertEqualsWithDelta()` for floats
   - `assertContains()` for probabilistic results
6. **Test exceptions** with `expectException()` and `expectExceptionMessage()`
7. **Use reflection sparingly** - Only for testing private state when necessary

#### Testing Probabilistic Behavior

For probabilistic code, use one of these approaches:

**Approach 1: Inject controlled RNG** (preferred)
```php
$gen = new WeightedRandomGenerator();
$gen->registerValues(['a' => 1.0, 'b' => 1.0]);

$ref = new \ReflectionProperty($gen, 'randomNumberGenerator');
$ref->setAccessible(true);
$ref->setValue($gen, fn() => 0); // Always return 0

$result = $gen->generate();
$this->assertSame('a', $result); // Deterministic
```

**Approach 2: Statistical validation**
```php
$results = [];
for ($i = 0; $i < 100; $i++) {
    $results[] = $gen->generate();
}

// Assert properties of the distribution
$this->assertGreaterThan(1, count(array_unique($results)));
```

### Commit Messages

Write clear, descriptive commit messages:

```
Short summary (50 chars or less)

More detailed explanation if needed. Wrap at 72 characters.

- Bullet points are fine
- Use present tense: "Add feature" not "Added feature"
- Reference issues: "Fixes #123"
```

## Architecture Patterns

### Existing Patterns to Follow

1. **Interface-based design** - `WeightedRandomInterface` defines the contract
2. **Value objects** - Immutable objects like `WeightedValue`, `WeightedGroup`
3. **Composition over inheritance** - `WeightedBagRandomGenerator` composes `WeightedRandomGenerator`
4. **Fluent interface** - Method chaining with `return $this`
5. **Generator pattern** - Use PHP generators for iterables

### Example: Adding a New Feature

Let's say you want to add a `getMedian()` method:

**1. Add to the implementation:**
```php
// src/Generator/WeightedRandomGenerator.php

/**
 * Calculate median for numeric values.
 * Only considers numeric values; ignores non-numeric values.
 *
 * @return float|null Returns null if no numeric values registered
 */
public function getMedian(): ?float
{
    $numericValues = [];

    foreach ($this->getWeightedValues() as $weightedValue) {
        $value = $weightedValue->getValue();

        if ($value instanceof WeightedGroup || !is_numeric($value)) {
            continue;
        }

        $numericValues[] = (float)$value;
    }

    if (empty($numericValues)) {
        return null;
    }

    sort($numericValues);
    $count = count($numericValues);
    $mid = (int)floor($count / 2);

    if ($count % 2 === 0) {
        return ($numericValues[$mid - 1] + $numericValues[$mid]) / 2;
    }

    return $numericValues[$mid];
}
```

**2. Add tests:**
```php
// tests/Generator/WeightedRandomGeneratorTest.php

public function testGetMedianForOddCount(): void
{
    $gen = new WeightedRandomGenerator();
    $gen->registerValues([1 => 1.0, 2 => 1.0, 3 => 1.0]);

    $median = $gen->getMedian();

    $this->assertEqualsWithDelta(2.0, $median, 0.001);
}

public function testGetMedianForEvenCount(): void
{
    $gen = new WeightedRandomGenerator();
    $gen->registerValues([1 => 1.0, 2 => 1.0, 3 => 1.0, 4 => 1.0]);

    $median = $gen->getMedian();

    $this->assertEqualsWithDelta(2.5, $median, 0.001);
}

public function testGetMedianReturnsNullForNonNumeric(): void
{
    $gen = new WeightedRandomGenerator();
    $gen->registerValues(['a' => 1.0, 'b' => 1.0]);

    $median = $gen->getMedian();

    $this->assertNull($median);
}
```

**3. Update documentation:**
```markdown
// README.md

### Distribution Introspection

\`\`\`php
// ... existing examples ...

$median = $gen->getMedian(); // Median value
\`\`\`
```

**4. Update CHANGELOG:**
```markdown
## [Unreleased]

### Added
- `getMedian()` - Calculate median for numeric values
```

## Pull Request Process

1. **Update CHANGELOG.md** under `[Unreleased]`
2. **Ensure tests pass** and coverage is 100%
3. **Update README** if adding features
4. **Keep commits focused** - one feature/fix per PR when possible
5. **Respond to feedback** - be open to suggestions

### PR Checklist

Before submitting:

- [ ] Code follows project style guidelines
- [ ] All tests pass (`vendor/bin/phpunit`)
- [ ] 100% code coverage maintained
- [ ] New features have tests
- [ ] Documentation updated (README, CHANGELOG, PHPDoc)
- [ ] Commit messages are clear
- [ ] No breaking changes (or clearly documented if necessary)

## Questions?

If you have questions:
- Check existing issues and pull requests
- Review the README and this guide
- Open an issue for discussion before starting large features

Thank you for contributing! 🎉
