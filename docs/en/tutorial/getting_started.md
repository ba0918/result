# Getting Started Tutorial

A tutorial designed for developers with no functional programming experience to understand the basic concepts of Result and Option types in 30 minutes.

## 🎯 What You'll Learn

- Why Result and Option types are necessary
- How they differ from traditional null/exception handling
- How to use basic methods
- Real-world code examples

## 📚 Prerequisites

- Basic PHP 8.3+ syntax
- Understanding of classes and interfaces
- Familiarity with anonymous functions (closures)

## 🤔 Why Do We Need Result and Option Types?

### Problems with Traditional PHP

#### Problem 1: Runtime errors from null

```php
// Traditional PHP code - Dangerous!
function findUser(int $id): ?array
{
    $users = [1 => ['name' => 'Alice'], 2 => ['name' => 'Bob']];
    return $users[$id] ?? null;
}

$user = findUser(999); // null is returned
echo $user['name'];    // Fatal Error: Array access on null
```

#### Problem 2: Overlooked exceptions

```php
// Traditional PHP code - Easy to forget exception handling
function divide(float $a, float $b): float
{
    if ($b === 0.0) {
        throw new InvalidArgumentException("Cannot divide by zero");
    }
    return $a / $b;
}

// Crashes if exception handling is forgotten
$result = divide(10, 0); // Exception: Cannot divide by zero
```

### Solution with Result and Option Types

```php
use ba0918\Result\{Ok, Err, Some, None, Result, Option};

// Result type - explicitly handle success/failure
function safeDivide(float $a, float $b): Result
{
    if ($b === 0.0) {
        return Err::of("Cannot divide by zero");
    }
    return Ok::of($a / $b);
}

// Option type - explicitly handle presence/absence of values
function findUser(int $id): Option
{
    $users = [1 => ['name' => 'Alice'], 2 => ['name' => 'Bob']];
    
    if (isset($users[$id])) {
        return Some::of($users[$id]);
    }
    return None::instance();
}

// Type system guarantees safety
$result = safeDivide(10, 0);  // Always Result type
$user = findUser(999);        // Always Option type

// Runtime-enforced type safety (Result/Option always requires explicit handling)
if ($result->isOk()) {
    echo "Result: " . $result->unwrap();
} else {
    echo "Error: " . $result->unwrapErr();
}
```

## 📖 Result Type Basics

### What is Result Type

Result type has two states representing "success" or "failure":

- **Ok(value)** - Represents success and holds the success value
- **Err(error)** - Represents failure and holds error information

### Basic Usage

```php
<?php
use ba0918\Result\{Ok, Err, Result};

// 1. Create a function that returns Result type
function validateAge(int $age): Result
{
    if ($age < 0) {
        return Err::of("Age must be 0 or greater");
    }
    if ($age > 150) {
        return Err::of("Age must be 150 or less");
    }
    return Ok::of($age);
}

// 2. Check the result
$result = validateAge(25);

// Check if successful
if ($result->isOk()) {
    echo "Valid age: " . $result->unwrap();
} else {
    echo "Error: " . $result->unwrapErr();
}
```

### Basic Methods

#### isOk() / isErr() - State checking

```php
$success = Ok::of(42);
$failure = Err::of("error");

var_dump($success->isOk());  // true
var_dump($success->isErr()); // false
var_dump($failure->isOk());  // false
var_dump($failure->isErr()); // true
```

#### unwrap() / unwrapErr() - Value extraction

```php
$success = Ok::of(42);
$failure = Err::of("error");

// Extract success value (throws exception on failure)
echo $success->unwrap();     // 42
// $failure->unwrap();       // UnwrapException is thrown

// Extract error value (throws exception on success)
echo $failure->unwrapErr();  // "error"
// $success->unwrapErr();    // UnwrapException is thrown
```

#### unwrapOr() - Safe value extraction

```php
$success = Ok::of(42);
$failure = Err::of("error");

// Safe extraction with default value
echo $success->unwrapOr(0);  // 42 (success value)
echo $failure->unwrapOr(0);  // 0 (default value)
```

## 📖 Option Type Basics

### What is Option Type

Option type has two states representing "has value" or "no value":

- **Some(value)** - Represents presence of a value and holds that value
- **None** - Represents absence of a value (singleton)

### Basic Usage

```php
<?php
use ba0918\Result\{Some, None, Option};

// 1. Create a function that returns Option type
function getConfig(string $key): Option
{
    $config = [
        'app_name' => 'MyApp',
        'debug' => true
    ];
    
    if (isset($config[$key])) {
        return Some::of($config[$key]);
    }
    return None::instance();
}

// 2. Check the result
$value = getConfig('app_name');

if ($value->isSome()) {
    echo "Config value: " . $value->unwrap();
} else {
    echo "Configuration not found";
}
```

### Basic Methods

#### isSome() / isNone() - State checking

```php
$some = Some::of("value");
$none = None::instance();

var_dump($some->isSome());  // true
var_dump($some->isNone());  // false
var_dump($none->isSome());  // false
var_dump($none->isNone());  // true
```

#### unwrap() / unwrapOr() - Value extraction

```php
$some = Some::of("value");
$none = None::instance();

// Extract value (throws exception on None)
echo $some->unwrap();       // "value"
// $none->unwrap();         // UnwrapException is thrown

// Safe extraction with default value
echo $some->unwrapOr("default");  // "value"
echo $none->unwrapOr("default");  // "default"
```

## 🔄 Basic Transformation Operations

### map() - Value transformation

A method to transform the contents of Result or Option types.

```php
// Result type map
$result = Ok::of(10);
$doubled = $result->map(fn($x) => $x * 2);
echo $doubled->unwrap(); // 20

// Error case remains unchanged
$error = Err::of("error");
$mapped = $error->map(fn($x) => $x * 2);
echo $mapped->unwrapErr(); // "error" (not transformed)

// Option type map
$some = Some::of("hello");
$upper = $some->map(fn($s) => strtoupper($s));
echo $upper->unwrap(); // "HELLO"

// None case remains unchanged
$none = None::instance();
$mapped = $none->map(fn($s) => strtoupper($s));
var_dump($mapped->isNone()); // true (not transformed)
```

### andThen() - Sequential processing

A method to sequentially apply functions that return Result or Option types.

```php
function validatePositive(int $n): Result
{
    return $n > 0 ? Ok::of($n) : Err::of("Must be a positive number");
}

function validateEven(int $n): Result
{
    return $n % 2 === 0 ? Ok::of($n) : Err::of("Must be an even number");
}

// Sequential validation
$result = Ok::of(4)
    ->andThen('validatePositive')
    ->andThen('validateEven');

if ($result->isOk()) {
    echo "Valid value: " . $result->unwrap(); // "Valid value: 4"
}
```

## 🔄 Practical Examples

### File reading with a clear failure boundary

Only the failures the caller can branch on go into `Result`. A missing file
or a read failure is an infrastructure problem — it stays an exception.
Invalid JSON is a format problem you may want to handle, so it becomes `Err`.

```php
use ba0918\Result\{Ok, Err, Result};

/**
 * @throws RuntimeException  File does not exist / read failure
 * @return Result<array, string>  Invalid JSON becomes Err
 */
function readConfigFile(string $path): Result
{
    if (!file_exists($path)) {
        throw new RuntimeException("File does not exist: $path");
    }
    
    $content = file_get_contents($path);
    if ($content === false) {
        throw new RuntimeException("Failed to read file: $path");
    }
    
    $data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return Err::of("JSON parse error: " . json_last_error_msg());
    }
    
    return Ok::of($data);
}

// Usage example - the exception is infrastructure, the Err is data
try {
    $config = readConfigFile('config.json');
    $settings = $config
        ->map(fn($data) => array_merge(['debug' => false], $data))
        ->unwrap(); // Ok is guaranteed after Err was handled
    echo "App name: " . $settings['app_name'];
} catch (RuntimeException $e) {
    echo "Failed to load config: " . $e->getMessage();
}
```

### Database search example

```php
use ba0918\Result\{Some, None, Option};

function findUserById(int $id): Option
{
    // Simulate actual DB access
    $users = [
        1 => ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'],
        2 => ['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com'],
    ];
    
    if (isset($users[$id])) {
        return Some::of($users[$id]);
    }
    return None::instance();
}

// Usage example
$userEmail = findUserById(1)
    ->map(fn($user) => $user['email'])
    ->unwrapOr('unknown@example.com');

echo "User email: " . $userEmail; // alice@example.com
```

## ❓ Frequently Asked Questions

### Q1: When should I use Result type?

**A**: For operations that can fail, where you want to handle that failure appropriately.

- File operations (reading, writing)
- Network communication (API calls)
- Data validation & transformation
- Integration with external services

### Q2: When should I use Option type?

**A**: When a value might not exist, as an alternative to null.

- Database search results
- Array/map value retrieval
- Configuration value retrieval
- Optional parameters

### Q3: How do I choose between exceptions and Result type?

**A**: Use them as follows:

- **Result type**: Predictable failures, part of business logic
- **Exceptions**: Programming errors, unrecoverable problems

```php
// Result type is appropriate
function divide(float $a, float $b): Result
{
    if ($b === 0.0) {
        return Err::of("Division by zero is mathematically undefined");
    }
    return Ok::of($a / $b);
}

// Exception is appropriate
function openFile(string $path): FileHandle
{
    $handle = fopen($path, 'r');
    if ($handle === false) {
        throw new RuntimeException("System error: Failed to create file handle");
    }
    return new FileHandle($handle);
}
```

### Q4: Don't method chains become complex?

**A**: They become readable with proper line breaks and indentation:

```php
$result = readFile('data.json')
    ->andThen(fn($content) => parseJson($content))
    ->andThen(fn($data) => validateData($data))
    ->map(fn($data) => processData($data))
    ->unwrapOr(getDefaultData());
```

## 🚀 Next Steps

### Learning Path

1. **✅ Complete**: Understanding basic concepts (this tutorial)
2. **➡️ Next**: [Basic Usage](basic_usage.md) - Learn practical patterns
3. **After that**: [Advanced Patterns](advanced_patterns.md) - More complex usage examples

### Learn More

- **[Best Practices](../guide/best_practices.md)** - Practical guidelines for projects
- **[API Reference](../api/result_api_reference.md)** - Detailed specifications for all methods
- **[Example Collection](../../../examples/)** - Copy & paste ready examples

---

💡 **Understanding Check**: Try running the examples from this tutorial yourself. Executable samples are also available in [examples/](../../../examples/).