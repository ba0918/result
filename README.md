# PHP Result/Option Library

![PHP Version](https://img.shields.io/badge/php-%5E8.3-blue.svg)
![PHPStan](https://img.shields.io/badge/PHPStan-Level%20MAX-brightgreen.svg)
![Tests](https://img.shields.io/badge/tests-passing-brightgreen.svg)
![Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen.svg)

[日本語](README.ja.md)

A PHP implementation of Rust's Result and Option types for robust error handling and null safety.

## Features

- **Type-safe error handling** - express success/failure explicitly instead of using exceptions or null
- **Functional programming** - declarative code through method chaining
- **Rust compatible** - 97% specification compliance with the Rust standard library
- **High quality** - PHPStan Level MAX, 100% test coverage

## Table of Contents

1. [Installation](#installation)
2. [Quick Start](#quick-start)
3. [Documentation](#documentation)
4. [Key Features](#key-features)
5. [Requirements](#requirements)
6. [Development](#development)
7. [License](#license)

## Installation

```bash
composer require ba0918/result
```

## Quick Start

### Result Type - Error Handling

```php
<?php
use ba0918\Result\{Ok, Err, Result};

// Division function (handles division by zero as error)
function safeDivide(float $a, float $b): Result
{
    if ($b === 0.0) {
        return new Err("Cannot divide by zero");
    }
    return new Ok($a / $b);
}

// Error handling
$result = safeDivide(10, 2);

if ($result->isOk()) {
    echo "Result: " . $result->unwrap(); // Result: 5
} else {
    echo "Error: " . $result->unwrapErr();
}

// More concise with method chaining
$output = safeDivide(10, 2)
    ->map(fn($value) => $value * 2)        // Double on success
    ->unwrapOr(0);                         // Default to 0 on failure

echo $output; // 10
```

### Option Type - Null Safety

```php
<?php
use ba0918\Result\{Some, None, Option};

// Safely retrieve value from array
function findUser(int $id): Option
{
    $users = [1 => 'Alice', 2 => 'Bob'];
    
    if (isset($users[$id])) {
        return new Some($users[$id]);
    }
    return None::instance();
}

// Null-safe processing
$user = findUser(1)
    ->map(fn($name) => strtoupper($name))  // Uppercase if found
    ->unwrapOr('Unknown');                 // Default value if not found

echo $user; // ALICE
```

## Documentation

### Tutorials

- **[Getting Started Tutorial](docs/en/tutorial/getting_started.md)** - understand core concepts in 30 minutes
- **[Basic Usage](docs/en/tutorial/basic_usage.md)** - practical patterns and examples
- **[Advanced Patterns](docs/en/tutorial/advanced_patterns.md)** - techniques for experienced developers

### Guides

- **[Best Practices](docs/en/guide/best_practices.md)** - practical guidelines for your projects
- **[Debugging Guide](docs/en/guide/debugging_guide.md)** - troubleshooting and debugging techniques
- **[Migration Guide](docs/en/guide/migration_guide.md)** - step-by-step migration from existing code
- **[Performance Guide](docs/en/guide/performance_guide.md)** - optimization and benchmarks
- **[IDE Integration](docs/en/guide/ide_integration.md)** - IDE configuration and tooling

### References

- **[Result API Reference](docs/en/api/result_api_reference.md)** - complete Result type method reference
- **[Option API Reference](docs/en/api/option_api_reference.md)** - complete Option type method reference
- **[Specification](docs/en/spec/specification.md)** - complete technical specification
- **[Coding Guidelines](docs/en/spec/coding_guideline.md)** - for contributors

### Comparisons

- **[Rust Comparison](docs/en/comparison/rust_comparison.md)** - mapping to the Rust standard library
- **[Other Libraries Comparison](docs/en/comparison/other_libraries.md)** - technical selection reference

### Examples

- **[Example Collection](examples/en/)** - copy and paste ready examples

## Key Features

### Result Type Methods

```php
// Core methods
$result->isOk() / $result->isErr()           // Success/failure checks
$result->map($fn) / $result->mapErr($fn)     // Value/error transformation
$result->andThen($fn)                        // Monadic chaining
$result->unwrap() / $result->unwrapOr($def)  // Value extraction

// Shorthand methods
$result->isOkAnd($predicate)                 // Conditional success check
$result->mapOr($fn, $default)                // Transformation with default
```

### Option Type Methods

```php
// Core methods
$option->isSome() / $option->isNone()        // Value presence checks
$option->map($fn)                            // Value transformation
$option->andThen($fn)                        // Monadic chaining
$option->filter($predicate)                  // Conditional filtering

// Combining operations
$option->zip($other)                         // Combine two Options
$option->xor($other)                         // Exclusive OR
```

## Requirements

- PHP 8.3+
- Composer

## Development

```bash
# Install dependencies
composer install

# Run tests
composer test

# Static analysis
composer phpstan

# Code style check
composer cs-check

# All quality checks
composer check
```

## License

MIT
