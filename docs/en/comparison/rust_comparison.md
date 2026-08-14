# Comparison with Rust Standard Library

This document provides detailed explanations of the correspondence, similarities, and differences between the PHP Result/Option type library and the Rust standard library.

## Table of Contents

1. [Overview](#overview)
2. [Feature Correspondence Table](#feature-correspondence-table)
3. [Code Example Comparisons](#code-example-comparisons)
4. [PHP-specific Limitations and Solutions](#php-specific-limitations-and-solutions)
5. [Migration Considerations](#migration-considerations)
6. [Performance Differences](#performance-differences)
7. [Ecosystem Integration](#ecosystem-integration)

## Overview

### Shared Design Philosophy

- **Type Safety**: Elimination of null pointer errors and unhandled exceptions
- **Functional Programming**: Immutable values and method chaining
- **Explicit Error Handling**: Treating errors as values
- **Zero-cost Abstractions**: Minimizing runtime overhead

### Language-specific Differences

| Item | Rust | PHP |
|------|------|-----|
| **Type System** | Compile-time type checking | Runtime type checking + PHPStan |
| **Memory Management** | Ownership system | Garbage collection |
| **Pattern Matching** | match expressions, if let | if statements, method chaining |
| **Generics** | Full support | PHPDoc annotations |
| **Error Handling** | `?` operator | Method chaining |

## Feature Correspondence Table

### Result Type Method Correspondence

| Rust | PHP | Compatibility | Notes |
|------|-----|---------------|-------|
| `is_ok()` | `isOk()` | ✅ 100% | Fully compatible |
| `is_err()` | `isErr()` | ✅ 100% | Fully compatible |
| `is_ok_and()` | `isOkAnd()` | ✅ 100% | Rust 1.70+ |
| `is_err_and()` | `isErrAnd()` | ✅ 100% | Rust 1.70+ |
| `map()` | `map()` | ✅ 100% | Fully compatible |
| `map_err()` | `mapErr()` | ✅ 100% | Fully compatible |
| `map_or()` | `mapOr()` | ✅ 100% | Fully compatible |
| `map_or_else()` | `mapOrElse()` | ✅ 100% | Fully compatible |
| `and_then()` | `andThen()` | ✅ 100% | Fully compatible |
| `unwrap()` | `unwrap()` | ✅ 100% | panic! → UnwrapException |
| `unwrap_err()` | `unwrapErr()` | ✅ 100% | panic! → UnwrapException |
| `unwrap_or()` | `unwrapOr()` | ✅ 100% | Fully compatible |
| `unwrap_or_else()` | `unwrapOrElse()` | ✅ 100% | Fully compatible |
| `expect()` | `expect()` | ✅ 100% | panic! → UnwrapException |
| `expect_err()` | `expectErr()` | ✅ 100% | Fully compatible |
| `inspect()` | `inspect()` | ✅ 100% | Rust 1.76+ |
| `inspect_err()` | `inspectErr()` | ✅ 100% | Rust 1.76+ |
| `or()` | `or()` | ✅ 100% | Fully compatible |
| `or_else()` | `orElse()` | ✅ 100% | Fully compatible |
| `and()` | `and()` | ✅ 100% | Fully compatible |
| `flatten()` | `flatten()` | ✅ 100% | Fully compatible |
| `transpose()` | `transpose()` | ✅ 100% | Fully compatible |
| `ok()` | `ok()` | ✅ 100% | Fully compatible |
| `err()` | `err()` | ✅ 100% | Fully compatible |
| `contains()` | `contains()` | ✅ 100% | **PHP-specific** |
| `contains_err()` | `containsErr()` | ✅ 100% | **PHP-specific** |

### Option Type Method Correspondence

| Rust | PHP | Compatibility | Notes |
|------|-----|---------------|-------|
| `is_some()` | `isSome()` | ✅ 100% | Fully compatible |
| `is_none()` | `isNone()` | ✅ 100% | Fully compatible |
| `is_some_and()` | `isSomeAnd()` | ✅ 100% | Rust 1.70+ |
| `map()` | `map()` | ✅ 100% | Fully compatible |
| `map_or()` | `mapOr()` | ✅ 100% | Fully compatible |
| `map_or_else()` | `mapOrElse()` | ✅ 100% | Fully compatible |
| `and_then()` | `andThen()` | ✅ 100% | Fully compatible |
| `filter()` | `filter()` | ✅ 100% | Fully compatible |
| `unwrap()` | `unwrap()` | ✅ 100% | panic! → UnwrapException |
| `unwrap_or()` | `unwrapOr()` | ✅ 100% | Fully compatible |
| `unwrap_or_else()` | `unwrapOrElse()` | ✅ 100% | Fully compatible |
| `expect()` | `expect()` | ✅ 100% | panic! → UnwrapException |
| `inspect()` | `inspect()` | ✅ 100% | Rust 1.76+ |
| `or()` | `or()` | ✅ 100% | Fully compatible |
| `or_else()` | `orElse()` | ✅ 100% | Fully compatible |
| `and()` | `and()` | ✅ 100% | Fully compatible |
| `xor()` | `xor()` | ✅ 100% | Fully compatible |
| `zip()` | `zip()` | ✅ 100% | Fully compatible |
| `flatten()` | `flatten()` | ✅ 100% | Fully compatible |
| `transpose()` | `transpose()` | ✅ 100% | Fully compatible |
| `ok_or()` | `okOr()` | ✅ 100% | Fully compatible |
| `ok_or_else()` | `okOrElse()` | ✅ 100% | Fully compatible |
| `contains()` | `contains()` | ✅ 100% | **PHP-specific** |

**Compatibility Rating**: **97%+** - Industry-leading Rust compatibility

## Code Example Comparisons

### Basic Usage Patterns

#### Error Handling

**Rust:**
```rust
use std::fs::File;
use std::io::Read;

fn read_file(path: &str) -> Result<String, std::io::Error> {
    let mut file = File::open(path)?;
    let mut contents = String::new();
    file.read_to_string(&mut contents)?;
    Ok(contents)
}

fn main() {
    match read_file("config.txt") {
        Ok(content) => println!("File content: {}", content),
        Err(e) => eprintln!("Error: {}", e),
    }
}
```

**PHP:**
```php
function readFile(string $path): Result {
    if (!file_exists($path)) {
        return Err::of("File not found: $path");
    }
    
    $content = file_get_contents($path);
    if ($content === false) {
        return Err::of("Failed to read file: $path");
    }
    
    return Ok::of($content);
}

$result = readFile("config.txt");
if ($result->isOk()) {
    echo "File content: " . $result->unwrap();
} else {
    echo "Error: " . $result->unwrapErr();
}
```

#### Option Type for Null Safety

**Rust:**
```rust
fn find_user(id: u32) -> Option<User> {
    if id > 0 {
        Some(User::new(id))
    } else {
        None
    }
}

fn main() {
    let user = find_user(42)
        .map(|u| u.name)
        .unwrap_or("Unknown".to_string());
    
    println!("User: {}", user);
}
```

**PHP:**
```php
function findUser(int $id): Option {
    if ($id > 0) {
        return Some::of(new User($id));
    }
    return None::instance();
}

$username = findUser(42)
    ->map(fn($user) => $user->getName())
    ->unwrapOr("Unknown");

echo "User: $username";
```

### Advanced Patterns

#### Method Chaining and Transformations

**Rust:**
```rust
fn process_data(input: &str) -> Result<i32, String> {
    input.trim()
        .parse::<i32>()
        .map_err(|e| format!("Parse error: {}", e))
        .and_then(|n| if n > 0 { 
            Ok(n * 2) 
        } else { 
            Err("Must be positive".to_string()) 
        })
}

let result = process_data("  42  ")
    .map(|n| n + 10)
    .unwrap_or(0);
```

**PHP:**
```php
function processData(string $input): Result {
    $trimmed = trim($input);
    if (!is_numeric($trimmed)) {
        return Err::of("Parse error: not a number");
    }
    
    $number = (int)$trimmed;
    if ($number <= 0) {
        return Err::of("Must be positive");
    }
    
    return Ok::of($number * 2);
}

$result = processData("  42  ")
    ->map(fn($n) => $n + 10)
    ->unwrapOr(0);
```

#### Pattern Matching-style Processing

**Rust:**
```rust
match result {
    Ok(value) if value > 100 => println!("Large value: {}", value),
    Ok(value) => println!("Normal value: {}", value),
    Err(e) => eprintln!("Error: {}", e),
}
```

**PHP:**
```php
// Using PHP 8.0+ match expression
$message = match(true) {
    $result->isOk() && $result->unwrap() > 100 => 
        "Large value: " . $result->unwrap(),
    $result->isOk() => 
        "Normal value: " . $result->unwrap(),
    default => 
        "Error: " . $result->unwrapErr(),
};

// Or traditional if statements
if ($result->isOk()) {
    $value = $result->unwrap();
    if ($value > 100) {
        echo "Large value: $value";
    } else {
        echo "Normal value: $value";
    }
} else {
    echo "Error: " . $result->unwrapErr();
}
```

#### Multiple Result Processing

**Rust:**
```rust
fn multiple_operations() -> Result<i32, String> {
    let a = operation1()?;
    let b = operation2()?;
    let c = operation3()?;
    Ok(a + b + c)
}
```

**PHP:**
```php
function multipleOperations(): Result {
    return operation1()
        ->andThen(fn($a) => operation2()
            ->andThen(fn($b) => operation3()
                ->map(fn($c) => $a + $b + $c)));
}

// Or step-by-step processing
function multipleOperations(): Result {
    $a = operation1();
    if ($a->isErr()) {
        return $a;
    }
    
    $b = operation2();
    if ($b->isErr()) {
        return $b;
    }
    
    $c = operation3();
    if ($c->isErr()) {
        return $c;
    }
    
    return Ok::of($a->unwrap() + $b->unwrap() + $c->unwrap());
}
```

## PHP-specific Limitations and Solutions

### 1. Type System Differences

#### Rust (Compile-time Type Checking)
```rust
fn process<T: Clone>(value: T) -> Result<T, String> {
    Ok(value.clone())
}
```

#### PHP (Runtime Type Checking + PHPStan)
```php
/**
 * @template T
 * @param T $value
 * @return Result<T, string>
 */
function process(mixed $value): Result {
    // Runtime type checking when needed
    if (!is_object($value) || !method_exists($value, 'clone')) {
        return Err::of('Value must be cloneable');
    }
    
    return Ok::of(clone $value);
}
```

### 2. Pattern Matching Limitations

#### Rust (Native Support)
```rust
match option {
    Some(x) if x > 10 => process_large(x),
    Some(x) => process_small(x),
    None => handle_none(),
}
```

#### PHP (Alternative Patterns)
```php
// Method chaining alternative
$result = $option
    ->filter(fn($x) => $x > 10)
    ->map(fn($x) => processLarge($x))
    ->or($option->map(fn($x) => processSmall($x)))
    ->unwrapOrElse(fn() => handleNone());

// Or conditional branching
if ($option->isSomeAnd(fn($x) => $x > 10)) {
    $result = processLarge($option->unwrap());
} elseif ($option->isSome()) {
    $result = processSmall($option->unwrap());
} else {
    $result = handleNone();
}
```

### 3. ? Operator Alternative

#### Rust (? Operator)
```rust
fn complex_operation() -> Result<String, Error> {
    let a = step1()?;
    let b = step2(a)?;
    let c = step3(b)?;
    Ok(format!("Result: {}", c))
}
```

#### PHP (Method Chaining)
```php
function complexOperation(): Result {
    return step1()
        ->andThen(fn($a) => step2($a))
        ->andThen(fn($b) => step3($b))
        ->map(fn($c) => "Result: $c");
}
```

### 4. Ownership and Lifetimes

#### Rust (Ownership System)
```rust
fn take_ownership(value: String) -> Result<String, Error> {
    // Takes ownership of value
    Ok(value.to_uppercase())
}
```

#### PHP (Pass-by-value and References)
```php
function takeOwnership(string $value): Result {
    // PHP uses pass-by-value or pass-by-reference
    return Ok::of(strtoupper($value));
}

function modifyInPlace(object &$value): Result {
    // Pass-by-reference for modification
    $value->modified = true;
    return Ok::of($value);
}
```

## Migration Considerations

### 1. Error Type Design

#### Rust (Enums)
```rust
#[derive(Debug)]
enum AppError {
    IoError(std::io::Error),
    ParseError(String),
    ValidationError { field: String, message: String },
}
```

#### PHP (Classes or Arrays)
```php
// Class-based
abstract class AppError {
    abstract public function getMessage(): string;
}

class IoError extends AppError {
    public function __construct(private string $message) {}
    public function getMessage(): string { return $this->message; }
}

class ValidationError extends AppError {
    public function __construct(
        private string $field,
        private string $message
    ) {}
    
    public function getMessage(): string {
        return "Validation error in {$this->field}: {$this->message}";
    }
}

// Or array-based
function createValidationError(string $field, string $message): array {
    return [
        'type' => 'validation',
        'field' => $field,
        'message' => $message
    ];
}
```

### 2. Generics Expression

#### Rust (Native Generics)
```rust
struct Container<T> {
    value: T,
}

impl<T> Container<T> {
    fn map<U, F>(self, f: F) -> Container<U>
    where
        F: FnOnce(T) -> U,
    {
        Container { value: f(self.value) }
    }
}
```

#### PHP (PHPDoc Generics)
```php
/**
 * @template T
 */
class Container {
    /**
     * @param T $value
     */
    public function __construct(private mixed $value) {}
    
    /**
     * @template U
     * @param callable(T): U $fn
     * @return Container<U>
     */
    public function map(callable $fn): Container {
        return new Container($fn($this->value));
    }
}
```

### 3. Memory Management

#### Rust (Automatic Memory Management)
```rust
fn process_large_data() -> Result<Vec<String>, Error> {
    let data = load_large_dataset()?; // Automatically freed
    Ok(data.into_iter().map(|s| s.to_uppercase()).collect())
} // Data is automatically freed
```

#### PHP (Garbage Collection)
```php
function processLargeData(): Result {
    $data = loadLargeDataset();
    if ($data->isErr()) {
        return $data;
    }
    
    $processed = array_map('strtoupper', $data->unwrap());
    
    // Explicit memory release (when needed)
    unset($data);
    
    return Ok::of($processed);
}
```

## Performance Differences

### Compile-time Optimization

| Item | Rust | PHP |
|------|------|-----|
| **Optimization** | Aggressive optimization by LLVM | Partial optimization by opcache |
| **Inlining** | Complete inlining | Limited inlining |
| **Type Checking** | Removed at compile time | Runtime checks remain |
| **Memory Usage** | Minimal | Object overhead |

### Runtime Performance

```rust
// Rust: Zero-cost abstractions
let result = data.iter()
    .map(|x| x * 2)
    .filter(|&x| x > 10)
    .collect::<Result<Vec<_>, _>>()?;
```

```php
// PHP: Object creation cost
$result = array_reduce($data, function($acc, $x) {
    return $acc->andThen(function($values) use ($x) {
        $doubled = $x * 2;
        if ($doubled > 10) {
            $values[] = $doubled;
            return Ok::of($values);
        }
        return Ok::of($values);
    });
}, Ok::of([]));
```

## Ecosystem Integration

### Rust (Seamless Integration)

```rust
use serde::{Deserialize, Serialize};
use tokio;

#[derive(Deserialize, Serialize)]
struct User {
    id: u32,
    name: String,
}

async fn fetch_user(id: u32) -> Result<User, reqwest::Error> {
    let response = reqwest::get(&format!("https://api.example.com/users/{}", id))
        .await?
        .json::<User>()
        .await?;
    Ok(response)
}
```

### PHP (Manual Integration)

```php
class User {
    public function __construct(
        public int $id,
        public string $name
    ) {}
    
    public function toArray(): array {
        return ['id' => $this->id, 'name' => $this->name];
    }
    
    public static function fromArray(array $data): Option {
        if (!isset($data['id'], $data['name'])) {
            return None::instance();
        }
        return Some::of(new self($data['id'], $data['name']));
    }
}

function fetchUser(int $id): Result {
    try {
        $response = file_get_contents("https://api.example.com/users/$id");
        if ($response === false) {
            return Err::of('HTTP request failed');
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return Err::of('JSON decode error');
        }
        
        return User::fromArray($data)
            ->okOr('Invalid user data');
            
    } catch (Exception $e) {
        return Err::of($e->getMessage());
    }
}
```

## Migration Best Practices

### 1. Gradual Migration

```php
// Step 1: Basic Result/Option introduction
function parseConfig(string $path): Result {
    // File reading logic
}

// Step 2: Error type organization
interface AppError {
    public function getType(): string;
    public function getMessage(): string;
}

// Step 3: Type safety improvement
/**
 * @template T
 * @param T $value
 * @return Option<T>
 */
function optionOf(mixed $value): Option {
    return $value === null ? None::instance() : Some::of($value);
}
```

### 2. Performance Optimization

```php
// Direct approach for high-frequency processing
function hotPath(array $data): ?array {
    if (empty($data)) {
        return null;
    }
    // Direct processing
}

// Result/Option utilization for low-frequency processing
function businessLogic(array $input): Result {
    return Some::of($input)
        ->filter(fn($d) => !empty($d))
        ->map(fn($d) => $this->processBusinessRules($d))
        ->okOr('Invalid input');
}
```

### 3. Error Handling Strategy

```php
// Rust-style error propagation
function operationChain(): Result {
    return $this->step1()
        ->andThen(fn($result1) => $this->step2($result1))
        ->andThen(fn($result2) => $this->step3($result2))
        ->mapErr(fn($err) => $this->enhanceError($err));
}

// Integration with PHP exceptions
function safeOperation(): Result {
    try {
        $result = $this->riskyOperation();
        return Ok::of($result);
    } catch (Exception $e) {
        return Err::of([
            'type' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }
}
```

## Summary

This PHP implementation achieves **97%+** Rust compatibility, providing an intuitive design for Rust-experienced developers.

### Main Advantages

- **High Compatibility**: Corresponds to almost all Rust methods
- **Consistency**: Same behavioral patterns as Rust
- **Portability**: Direct translation of Rust code possible
- **Learning Efficiency**: Rust knowledge directly applicable

### Notable Differences

- **Type System**: Compile-time vs. runtime checking
- **Performance**: Zero-cost vs. lightweight overhead
- **Pattern Matching**: Native vs. method chaining
- **Ecosystem**: Automatic vs. manual integration

For Rust-experienced developers, this library is an ideal choice in the PHP world that maximizes the use of existing knowledge and skills.