# PHP Result/Option Library

![PHP Version](https://img.shields.io/badge/php-%5E8.4-blue.svg)
![PHPStan](https://img.shields.io/badge/PHPStan-Level%20MAX-brightgreen.svg)
![Tests](https://img.shields.io/badge/tests-passing-brightgreen.svg)
![Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen.svg)

🌐 **[日本語](README.ja.md)** | **English**

A PHP implementation of Rust's Result and Option types for robust error handling and null safety.

## 🚀 Why Use This Library?

- **Type-Safe Error Handling** - Express success/failure explicitly instead of exceptions or null
- **Functional Programming** - Declarative code with method chaining
- **Rust Compatible** - Leverage Rust ecosystem knowledge (97% specification compliance)
- **High Quality** - PHPStan Level MAX, 100% test coverage

## 📦 Installation

```bash
composer require mizumi/result
```

## ⚡ 5-Minute Quick Start

### Result Type - Error Handling

```php
<?php
use Mizumi\Result\{Ok, Err, Result};

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
use Mizumi\Result\{Some, None, Option};

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

## 📚 Documentation

### 📖 Learning Resources
- **[Getting Started Tutorial](docs/tutorial/getting_started.md)** - Understand core concepts in 30 minutes
- **[Basic Usage](docs/tutorial/basic_usage.md)** - Practical patterns and examples
- **[Advanced Patterns](docs/tutorial/advanced_patterns.md)** - Techniques for experienced developers

### 📋 Practical Guides
- **[Best Practices](docs/guide/best_practices.md)** - Practical guidelines for your projects
- **[Migration Guide](docs/guide/migration_guide.md)** - Step-by-step migration from existing code
- **[Performance Guide](docs/guide/performance_guide.md)** - Optimization and benchmarks

### 🔧 Reference
- **[Result API](docs/api/result_api_reference.md)** - Complete Result type method reference
- **[Option API](docs/api/option_api_reference.md)** - Complete Option type method reference
- **[Specification](docs/spec/specification.md)** - Complete technical specification

### 🆚 Comparison & Integration
- **[Rust Comparison](docs/comparison/rust_comparison.md)** - Mapping to Rust standard library
- **[Other Libraries Comparison](docs/comparison/other_libraries.md)** - Technical selection reference

## 🔥 Key Features

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

## 🎯 Real-World Examples

```php
// API response handling
function callApi(string $url): Result
{
    $response = file_get_contents($url);
    
    if ($response === false) {
        return new Err("API call failed");
    }
    
    return new Ok(json_decode($response, true));
}

$result = callApi('https://api.example.com/users')
    ->andThen(fn($data) => isset($data['users']) ? 
        new Ok($data['users']) : 
        new Err("Invalid response format"))
    ->map(fn($users) => array_filter($users, fn($user) => $user['active']))
    ->unwrapOr([]);

// Configuration file loading
function loadConfig(string $path): Option
{
    if (!file_exists($path)) {
        return None::instance();
    }
    
    $content = file_get_contents($path);
    return new Some(json_decode($content, true));
}

$config = loadConfig('config.json')
    ->map(fn($cfg) => array_merge($defaultConfig, $cfg))
    ->unwrapOr($defaultConfig);
```

## ✅ Requirements

- **PHP 8.4+**
- **Composer**

## 🛠️ Development

```bash
# Install dependencies
composer install

# Run tests
composer exec phpunit

# Static analysis
composer exec phpstan analyse
```

## 📈 Quality Metrics

- **Test Coverage**: 100%
- **PHPStan**: Level MAX (highest level)
- **Test Count**: 231+
- **Rust Specification Compliance**: 97%+

## 📄 License

MIT License

## 🤝 Contributing

Issues and Pull Requests are welcome! Please check the [coding guidelines](docs/spec/coding_guideline.md) if you'd like to contribute to development.

---

**Get started**: [Getting Started Tutorial](docs/tutorial/getting_started.md) | **Examples**: [Example Collection](examples/) | **API**: [Reference](docs/api/)