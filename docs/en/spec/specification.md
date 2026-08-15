# PHP Result/Option Type Library Specification

## Overview

This library is a PHP implementation of Rust's Result and Option types. The Result type represents success (`Ok`) and failure (`Err`) type-safely, while the Option type represents the presence (`Some`) and absence (`None`) of values, implementing error handling and null safety through functional programming approaches.

## Architecture

### Class Structure

```
ba0918\Result\
├── Result.php                     # Result type base interface
├── Ok.php                        # Class representing success values
├── Err.php                       # Class representing error values
├── Option.php                     # Option type base interface
├── Some.php                      # Option implementation class holding values
├── None.php                      # Option implementation class holding no value (singleton)
└── Exception\
    └── UnwrapException.php       # Exception thrown by unwrap methods
```

### Type Parameters

**Result type:**
- `T`: Type of success value
- `E`: Type of error value

**Option type:**
- `T`: Type of value (in Some case)

## Interface Definitions

### Result&lt;T, E&gt;

```php
interface Result
{
    public function isOk(): bool;
    public function isErr(): bool;
    public function isOkAnd(callable $predicate): bool;
    public function isErrAnd(callable $predicate): bool;
    public function map(callable $fn): Result;
    public function mapErr(callable $fn): Result;
    public function mapOr(callable $fn, mixed $default): mixed;
    public function mapOrElse(callable $fn, callable $defaultFn): mixed;
    public function andThen(callable $fn): Result;
    public function unwrap(): mixed;
    public function unwrapErr(): mixed;
    public function unwrapOr(mixed $default): mixed;
    public function unwrapOrElse(callable $fn): mixed;
    public function expect(string $message): mixed;
    public function inspect(callable $fn): Result;
    public function inspectErr(callable $fn): Result;
    public function or(Result $res): Result;
    public function orElse(callable $fn): Result;
    public function and(Result $res): Result;
    public function contains(mixed $value): bool;
    public function containsErr(mixed $error): bool;
    public function flatten(): Result;
    public function transpose(): Option;
    public function ok(): Option;
    public function err(): Option;
    public function expectErr(string $message): mixed;
}
```

### Option&lt;T&gt;

```php
interface Option
{
    public function isSome(): bool;
    public function isNone(): bool;
    public function isSomeAnd(callable $predicate): bool;
    public function map(callable $fn): Option;
    public function mapOr(callable $fn, mixed $default): mixed;
    public function mapOrElse(callable $fn, callable $defaultFn): mixed;
    public function andThen(callable $fn): Option;
    public function filter(callable $predicate): Option;
    public function unwrap(): mixed;
    public function unwrapOr(mixed $default): mixed;
    public function unwrapOrElse(callable $fn): mixed;
    public function expect(string $message): mixed;
    public function inspect(callable $fn): Option;
    public function or(Option $opt): Option;
    public function orElse(callable $fn): Option;
    public function and(Option $opt): Option;
    public function contains(mixed $value): bool;
    public function transpose(): Result;
    public function okOr(mixed $err): Result;
    public function okOrElse(callable $fn): Result;
    public function flatten(): Option;
    public function xor(Option $opt): Option;
    public function zip(Option $opt): Option;
}
```

## Implementation Class Details

### Ok&lt;T&gt; Class

Immutable class that stores success values.

**Constructor:**
- `__construct(mixed $value)` - Accepts value and initializes
- `static of(mixed $value): self` - Static factory method

**Key Method Behaviors:**
- `isOk()`: Always returns `true`
- `isErr()`: Always returns `false`
- `map(callable $fn)`: Applies function to value and returns new `Ok`
- `mapErr(callable $fn)`: Returns self unchanged (does nothing)
- `andThen(callable $fn)`: Applies function to value and returns the result
- `unwrap()`: Returns the stored value
- `unwrapErr()`: Throws `UnwrapException`
- `unwrapOr(mixed $default)`: Returns the stored value (ignores default)
- `unwrapOrElse(callable $fn)`: Returns the stored value (function not executed)
- `expect(string $message)`: Returns the stored value
- `inspect(callable $fn)`: Applies function to value for side effects and returns self
- `inspectErr(callable $fn)`: Does nothing and returns self unchanged
- `or(Result $res)`: Returns self unchanged (ignores alternative Result)
- `orElse(callable $fn)`: Returns self unchanged (function not executed)
- `flatten()`: Returns inner Result if value is Result, otherwise returns self
- `transpose()`: Returns mutual conversion if value is Option, otherwise returns `Some(Ok(value))`
- `ok()`: Returns stored value wrapped in `Some`
- `err()`: Returns `None` (Ok never has errors)
- `expectErr(string $message)`: Throws `UnwrapException` with custom message

### Err&lt;E&gt; Class

Immutable class that stores error values.

**Constructor:**
- `__construct(mixed $error)` - Accepts error value and initializes
- `static of(mixed $error): self` - Static factory method

**Key Method Behaviors:**
- `isOk()`: Always returns `false`
- `isErr()`: Always returns `true`
- `map(callable $fn)`: Returns self unchanged (does nothing)
- `mapErr(callable $fn)`: Applies function to error and returns new `Err`
- `andThen(callable $fn)`: Returns self unchanged (does nothing)
- `unwrap()`: Throws `UnwrapException`
- `unwrapErr()`: Returns the stored error value
- `unwrapOr(mixed $default)`: Returns default value
- `unwrapOrElse(callable $fn)`: Executes function with error value as argument and returns result
- `expect(string $message)`: Throws `UnwrapException` with custom message
- `inspect(callable $fn)`: Does nothing and returns self unchanged
- `inspectErr(callable $fn)`: Applies function to error value for side effects and returns self
- `or(Result $res)`: Returns the alternative Result (eager evaluation)
- `orElse(callable $fn)`: Executes function with error value as argument and returns resulting Result (lazy evaluation)
- `flatten()`: Returns self unchanged (does nothing)
- `transpose()`: Returns `Some(Err(error))`
- `ok()`: Returns `None` (Err never has success values)
- `err()`: Returns error value wrapped in `Some`
- `expectErr(string $message)`: Returns the stored error value

### Some&lt;T&gt; Class

Immutable class representing an Option with a value.

**Constructor:**
- `__construct(mixed $value)` - Accepts value and initializes
- `static of(mixed $value): self` - Static factory method

**Key Method Behaviors:**
- `isSome()`: Always returns `true`
- `isNone()`: Always returns `false`
- `map(callable $fn)`: Applies function to value and returns new `Some`
- `mapOr(callable $fn, mixed $default)`: Applies function to value and returns result
- `mapOrElse(callable $fn, callable $defaultFn)`: Applies function to value and returns result
- `andThen(callable $fn)`: Applies function to value and returns the result
- `filter(callable $predicate)`: Returns self if predicate is satisfied, otherwise returns `None`
- `unwrap()`: Returns the stored value
- `unwrapOr(mixed $default)`: Returns the stored value (ignores default)
- `unwrapOrElse(callable $fn)`: Returns the stored value (function not executed)
- `expect(string $message)`: Returns the stored value
- `inspect(callable $fn)`: Applies function to value for side effects and returns self
- `or(Option $opt)`: Returns self unchanged (ignores alternative Option)
- `orElse(callable $fn)`: Returns self unchanged (function not executed)
- `and(Option $opt)`: Returns the argument Option
- `contains(mixed $value)`: Performs strict comparison (`===`) with stored value and returns result
- `transpose()`: Returns mutual conversion if value is Result, otherwise returns `Ok(Some(value))`
- `okOr(mixed $err)`: Returns `Ok(value)`
- `okOrElse(callable $fn)`: Returns `Ok(value)` (function not executed)
- `flatten()`: Returns inner Option if value is Option, otherwise returns self
- `xor(Option $opt)`: Returns self if argument is None, returns `None` if argument is Some (exclusive OR)
- `zip(Option $opt)`: Returns `Some` containing array `[self value, argument value]` if argument is Some, returns `None` if argument is None

### None Class

Immutable singleton class representing an Option without a value.

**Instance Retrieval:**
- `static instance(): self` - Gets singleton instance

**Key Method Behaviors:**
- `isSome()`: Always returns `false`
- `isNone()`: Always returns `true`
- `map(callable $fn)`: Returns self unchanged (does nothing)
- `mapOr(callable $fn, mixed $default)`: Returns default value
- `mapOrElse(callable $fn, callable $defaultFn)`: Returns result of default function
- `andThen(callable $fn)`: Returns self unchanged (does nothing)
- `filter(callable $predicate)`: Returns self unchanged (does nothing)
- `unwrap()`: Throws `UnwrapException`
- `unwrapOr(mixed $default)`: Returns default value
- `unwrapOrElse(callable $fn)`: Returns result of function
- `expect(string $message)`: Throws `UnwrapException` with custom message
- `inspect(callable $fn)`: Does nothing and returns self unchanged
- `or(Option $opt)`: Returns the alternative Option (eager evaluation)
- `orElse(callable $fn)`: Executes function and returns resulting Option (lazy evaluation)
- `and(Option $opt)`: Returns self unchanged (does nothing)
- `contains(mixed $value)`: Always returns `false`
- `transpose()`: Returns `Ok(None)`
- `okOr(mixed $err)`: Returns `Err(err)`
- `okOrElse(callable $fn)`: Executes function and returns `Err(result)`
- `flatten()`: Returns self unchanged (does nothing)
- `xor(Option $opt)`: Returns the argument Option (regardless of argument)
- `zip(Option $opt)`: Returns self unchanged (None is always None)

## Design Principles

### 1. Immutability

- All instance properties are `readonly` (the singleton static property in None is an exception, as PHP does not allow `readonly` on static properties)
- Method calls return new instances or return existing instances unchanged
- No state modification occurs

### 2. Type Safety

- Generics expressed through PHPDoc
- Appropriate type constraints set
- Runtime type checking kept to minimum

### 3. Inheritance Prohibition

- All classes are `final` and cannot be inherited
- Only interface implementation is allowed

### 4. Error Handling

The choice between `Result`, `Option`, and `Exception` follows one question:
does the caller want to handle this failure as a normal branch of its flow?

- Failures the caller should branch on (validation, business rules) → `Result` / `Option`
- Normal "no value" where the reason does not matter → `Option`
- Invariant violations and programming mistakes → `Exception`
- Infrastructure failures (file, DB, network) this layer cannot recover from → `Exception` by default
- Failures an upper layer wants to retry or fall back on → convert to `Result` at the boundary

The detailed decision table and the chain-design guidelines (one chain per
responsibility, ~4 `andThen` steps per method, no nested `andThen`) are in
the [Best Practices Guide](../guide/best_practices.md).
- `UnwrapException` occurs only during unexpected operations

## Supported PHP Versions

- **Supported version**: `^8.3`
- **Rationale**:
  - Security support for 8.3 continues until the end of December 2027
  - PHP 8.4-specific features (such as property hooks) are mutually exclusive with the readonly design and have no room for application in this library
  - The code uses only readonly from 8.1 and `#[Override]` from 8.3
  - The Pipe operator API (`ba0918\Result\Pipe`) works on 8.3+ without `|>`;
    using the `|>` syntax at call sites requires PHP 8.5 or later
- **Re-evaluation condition**: Review supported versions when 8.3 reaches EOL (December 2027)

## Versioning

- The canonical version is the git tag (e.g. `v1.1.0`); `composer.json` does not
  declare a `version` field, so Composer derives the version from the tag
- `CHANGELOG.md` headings must match the tag exactly, and each release is issued
  as one operation: the version heading, the comparison link, and the tag
- Changes that alter the meaning of an existing behavior are marked **Breaking**
  in the changelog and move the major version

## Usage Examples

### Basic Usage

**Result type:**
```php
use ba0918\Result\Ok;
use ba0918\Result\Err;

// Success case
$result = Ok::of(42);
echo $result->unwrap(); // 42

// Failure case
$result = Err::of("Error message");
echo $result->unwrapOr(0); // 0
```

**Option type:**
```php
use ba0918\Result\Some;
use ba0918\Result\None;

// With value case
$option = Some::of("Hello World");
echo $option->unwrap(); // "Hello World"

// Without value case
$option = None::instance();
echo $option->unwrapOr("Default value"); // "Default value"
```

### Chain Processing

**Result type:**
```php
$result = Ok::of(10)
    ->map(fn($x) => $x * 2)
    ->inspect(fn($value) => print("Intermediate value: $value\n")) // Debug output
    ->andThen(fn($x) => $x > 15 ? Ok::of($x) : Err::of("Value too small"))
    ->unwrapOr(0);
```

**Option type:**
```php
$result = Some::of("hello")
    ->map(fn($s) => strtoupper($s))
    ->filter(fn($s) => strlen($s) > 3)
    ->inspect(fn($value) => print("Processing: $value\n"))
    ->andThen(fn($s) => Some::of($s . " WORLD"))
    ->unwrapOr("Default");
echo $result; // "HELLO WORLD"
```

### Pipe Operator Usage (PHP 8.5+)

The `ba0918\Result\Pipe` namespace provides function adapters for the
PHP 8.5 pipe operator (`|>`). Each function takes the business callable and
returns a `Closure(Result): Result` that delegates to the corresponding
Result method:

```php
use ba0918\Result\Ok;
use function ba0918\Result\Pipe\andThen;
use function ba0918\Result\Pipe\map;
use function ba0918\Result\Pipe\orElse;

$response = $this->doSomething()
    |> andThen(fn ($value) => $this->transform($value))
    |> orElse(fn ($error) => $this->recover($error))
    |> andThen(fn ($value) => $this->respond($value));
```

Available functions: `map`, `mapErr`, `andThen`, `orElse`, `inspect`, `inspectErr`.
They are thin adapters over the same-named methods, so the semantics are identical
to the method chain API.

**The returned closure's input type is bound to the callable's parameter type.**
`map()`, `andThen()` and `inspect()` accept a `Result` whose success type matches
the callable's parameter (e.g. `map(fn (int $v) ...)` accepts `Result<int, E>`).
`mapErr()` and `orElse()` bind the error type the same way
(e.g. `orElse(fn (string $e) ...)` accepts `Result<T, string>`).
Piping a mismatched `Result` into a stage is a static error under PHPStan.

**The pipe operator does not short-circuit.** `|>` only evaluates each right-hand
side callable and applies it to the left-hand side value:

- Each operator factory (e.g. `andThen(fn ...)`) is always invoked
- Only the business callable passed to `andThen()`/`map()` is skipped when the
  result is `Err` (and conversely for `orElse()`/`mapErr()` when it is `Ok`)

When `orElse()` returns `Ok`, the recovery succeeds and subsequent `andThen()`
stages execute normally:

```php
$response = $this->doSomething()      // Err
    |> andThen(fn ($value) => $this->transform($value))   // skipped
    |> orElse(fn ($error) => $this->recover($error))      // returns Ok
    |> andThen(fn ($value) => $this->respond($value));    // executed
```

The adapters are usable without `|>` as well, by calling the returned Closure
directly (`andThen($op)($result)`), which works on PHP 8.3 and 8.4.
Call sites using the `|>` syntax require PHP 8.5 or later.


### inspect/inspectErr Method Usage Examples

```php
// Value inspection for debugging
$result = Ok::of("Important data")
    ->inspect(fn($value) => error_log("Processing data: $value"))
    ->map(fn($value) => strtoupper($value));

// Error logging
$result = Err::of("Network error")
    ->inspectErr(fn($error) => error_log("Error occurred: $error"))
    ->or(Ok::of("Default value"));

// Stepwise debugging in method chains
$result = Ok::of(100)
    ->map(fn($x) => $x / 2)
    ->inspect(fn($value) => print("Step 1: $value\n"))
    ->map(fn($x) => $x - 10)
    ->inspect(fn($value) => print("Step 2: $value\n"))
    ->andThen(fn($x) => $x > 0 ? Ok::of($x) : Err::of("Negative value"))
    ->inspectErr(fn($error) => print("Error: $error\n"));
```

### or/orElse Method Usage Examples

```php
// or(): Eager evaluation for providing alternative values
$primaryResult = Err::of("Database connection failed");
$fallbackResult = Ok::of("Data from cache");

$result = $primaryResult->or($fallbackResult);
echo $result->unwrap(); // "Data from cache"

// orElse(): Lazy evaluation for dynamic alternative value generation
function createFallback(string $error): Result {
    error_log("Executing fallback processing: $error");
    return Ok::of("Alternative data: " . date('Y-m-d H:i:s'));
}

$result = Err::of("API call failed")
    ->orElse(fn($error) => createFallback($error));

// Combining multiple alternative strategies
$result = Err::of("Primary processing failed")
    ->or(Err::of("Alternative processing 1 also failed"))
    ->orElse(fn($error) => Ok::of("Final alternative value"))
    ->unwrap(); // "Final alternative value"
```

### contains/containsErr Method Usage Examples

```php
// Value confirmation in Ok values
$ok = Ok::of("success");
var_dump($ok->contains("success")); // true
var_dump($ok->contains("failure")); // false
var_dump($ok->containsErr("error")); // false (Ok never contains errors)

// Error confirmation in Err values
$err = Err::of("network error");
var_dump($err->containsErr("network error")); // true
var_dump($err->containsErr("database error")); // false
var_dump($err->contains("success")); // false (Err never contains values)

// Strict comparison behavior
$intOk = Ok::of(42);
var_dump($intOk->contains(42)); // true
var_dump($intOk->contains("42")); // false (different types)
var_dump($intOk->contains(42.0)); // false (different types)

// Confirmation with complex data structures
$userData = ["id" => 123, "name" => "Alice"];
$ok = Ok::of($userData);
var_dump($ok->contains(["id" => 123, "name" => "Alice"])); // true
var_dump($ok->contains(["id" => 123, "name" => "Bob"])); // false

// Object reference confirmation
$obj = new stdClass();
$ok = Ok::of($obj);
var_dump($ok->contains($obj)); // true (same reference)
var_dump($ok->contains(new stdClass())); // false (different reference)
```

### and() Method Usage Examples

```php
// and(): Eager evaluation for continuous success checking
$validation = Ok::of("User authentication successful");
$authorization = Ok::of("Permission verification complete");

$result = $validation->and($authorization);
echo $result->unwrap(); // "Permission verification complete"

// If any fails, the first error is returned
$authOk = Ok::of("Authentication successful");
$authErr = Err::of("Insufficient permissions");

$result = $authOk->and($authErr);
echo $result->unwrapErr(); // "Insufficient permissions"

// If error comes first, subsequent ones are not evaluated
$firstErr = Err::of("First error");
$secondResult = Ok::of("Unreachable value");

$result = $firstErr->and($secondResult);
echo $result->unwrapErr(); // "First error"

// Multiple checkpoints
$userValidation = Ok::of("User valid");
$sessionValidation = Ok::of("Session valid");  
$permissionValidation = Ok::of("Permission valid");

$result = $userValidation
    ->and($sessionValidation)
    ->and($permissionValidation);
echo $result->unwrap(); // "Permission valid"

// Usage between Results of different types
$intResult = Ok::of(42);
$stringResult = Ok::of("Processing complete");

$final = $intResult->and($stringResult);
echo $final->unwrap(); // "Processing complete"
```

### flatten() Method Usage Examples

```php
// flatten(): One-level flattening of nested Results
$okOk = Ok::of(Ok::of(42));
$flattened = $okOk->flatten();
echo $flattened->unwrap(); // 42

// Flattening nested errors
$okErr = Ok::of(Err::of("Internal error"));
$flattened = $okErr->flatten();
echo $flattened->unwrapErr(); // "Internal error"

// Err returns self unchanged
$err = Err::of("External error");
$flattened = $err->flatten();
echo $flattened->unwrapErr(); // "External error"

// Non-Result values remain unchanged
$simple = Ok::of("Simple value");
$flattened = $simple->flatten();
echo $flattened->unwrap(); // "Simple value"

// Stepwise flattening of multiple nesting
$tripleNested = Ok::of(Ok::of(Ok::of("Deep value")));
$firstFlatten = $tripleNested->flatten();
$secondFlatten = $firstFlatten->flatten();
echo $secondFlatten->unwrap(); // "Deep value"

// Practical example: Flattening validation results
function validateAndParse(string $input): \ba0918\Result\Result {
    if (empty($input)) {
        return Ok::of(Err::of("Input is empty"));
    }
    
    $parsed = intval($input);
    if ($parsed === 0 && $input !== "0") {
        return Ok::of(Err::of("Number conversion failed"));
    }
    
    return Ok::of(Ok::of($parsed));
}

$result = validateAndParse("42")
    ->flatten()
    ->map(fn($x) => $x * 2);
echo $result->unwrap(); // 84

$errorResult = validateAndParse("")
    ->flatten()
    ->unwrapOr(0);
echo $errorResult; // 0
```

### Option Type Usage Examples

```php
// Conditional filtering with filter()
$age = Some::of(25)
    ->filter(fn($age) => $age >= 18)
    ->map(fn($age) => "Adult ({$age} years old)")
    ->unwrapOr("Minor");
echo $age; // "Adult (25 years old)"

// Alternative value provision with or() in None
$userName = None::instance()
    ->or(Some::of("guest"))
    ->unwrap(); // "guest"

// Dynamic alternative value generation with orElse()
$config = None::instance()
    ->orElse(fn() => Some::of(loadDefaultConfig()))
    ->unwrap();

// Value confirmation with contains()
$data = Some::of([1, 2, 3]);
if ($data->contains([1, 2, 3])) {
    echo "Expected array";
}
```

### transpose() and Option-Result Mutual Conversion

```php
use ba0918\Result\{Ok, Err, Some, None};

// Option<Result> → Result<Option> conversion
$optionResult = Some::of(Ok::of("Success data"));
$resultOption = $optionResult->transpose(); // Ok(Some("Success data"))

$errorCase = Some::of(Err::of("Error occurred"));
$errorResult = $errorCase->transpose(); // Err("Error occurred")

$noneCase = None::instance();
$noneResult = $noneCase->transpose(); // Ok(None)

// Result<Option> → Option<Result> conversion
$resultSome = Ok::of(Some::of("Value"));
$optionResult = $resultSome->transpose(); // Some(Ok("Value"))

$resultNone = Ok::of(None::instance());
$optionEmpty = $resultNone->transpose(); // None

$resultErr = Err::of("Error");
$optionErr = $resultErr->transpose(); // Some(Err("Error"))

// Option → Result conversion
$someValue = Some::of("Data");
$result = $someValue->okOr("Error message"); // Ok("Data")

$noneValue = None::instance();
$result = $noneValue->okOr("No value"); // Err("No value")

$result = $noneValue->okOrElse(fn() => "Dynamic error:" . time()); // Err("Dynamic error:...")

// Practical example: Database search and validation
function findUser(int $id): Option {
    // Database search simulation
    return $id > 0 ? Some::of(["id" => $id, "name" => "User$id"]) : None::instance();
}

function validateUser(array $user): Result {
    return empty($user['name']) ? Err::of("Name is empty") : Ok::of($user);
}

$result = findUser(123)                                    // Some(user) or None
    ->okOr("User not found")                               // Ok(user) or Err("...")
    ->andThen(fn($user) => validateUser($user))            // Ok(user) or Err("...")
    ->map(fn($user) => $user['name'])                      // Ok(name) or Err("...")
    ->unwrapOr("Guest");

echo $result; // "User123" or "Guest"
```

### Result Type New Conversion Method Usage Examples

```php
use ba0918\Result\{Ok, Err};

// ok() method: Get success value as Option
$success = Ok::of("Data");
$okOption = $success->ok(); // Some("Data")
echo $okOption->unwrap(); // "Data"

$failure = Err::of("Error");
$okOption = $failure->ok(); // None
echo $okOption->unwrapOr("Default"); // "Default"

// err() method: Get error value as Option
$success = Ok::of("Data");
$errOption = $success->err(); // None
echo $errOption->unwrapOr("No error"); // "No error"

$failure = Err::of("Network error");
$errOption = $failure->err(); // Some("Network error")
echo $errOption->unwrap(); // "Network error"

// expectErr() method: Extract error value with custom message
$error = Err::of("Authentication failed");
echo $error->expectErr("Error details required"); // "Authentication failed"

$success = Ok::of("Success data");
try {
    $success->expectErr("Expected error but got success");
} catch (UnwrapException $e) {
    echo $e->getMessage(); // "Expected error but got success: Success data"
}

// Practical example: Detailed analysis of API call results
function analyzeApiResult(Result $apiResult): array {
    return [
        'has_data' => $apiResult->ok()->isSome(),
        'data' => $apiResult->ok()->unwrapOr(null),
        'has_error' => $apiResult->err()->isSome(),
        'error_type' => $apiResult->err()
            ->map(fn($err) => $err instanceof \Exception ? get_class($err) : 'string')
            ->unwrapOr('none'),
        'error_message' => $apiResult->err()->unwrapOr('no error')
    ];
}

$successResult = Ok::of(['user_id' => 123, 'name' => 'Alice']);
$analysis = analyzeApiResult($successResult);
// ['has_data' => true, 'data' => [...], 'has_error' => false, ...]

$errorResult = Err::of(new \RuntimeException("Database connection failed"));
$analysis = analyzeApiResult($errorResult);
// ['has_data' => false, 'data' => null, 'has_error' => true, ...]
```

### Option Type New Combination Method Usage Examples

```php
use ba0918\Result\{Some, None};

// xor() method: Exclusive OR operation
$user = Some::of("Alice");
$guest = None::instance();
$both = Some::of("Bob");

// Some when only one is Some
$result1 = $user->xor($guest); // Some("Alice")
$result2 = $guest->xor($user); // Some("Alice")

// None when both Some/both None
$result3 = $user->xor($both); // None
$result4 = $guest->xor(None::instance()); // None

// zip() method: Combine two Options
$firstName = Some::of("Alice");
$lastName = Some::of("Smith");
$age = Some::of(30);

// Tuple when both Some
$fullName = $firstName->zip($lastName); // Some(["Alice", "Smith"])
$nameAndAge = $firstName->zip($age); // Some(["Alice", 30])

// None when either is None
$incomplete = $firstName->zip(None::instance()); // None

// Practical example: Form data combination
function combineFormData(Option $name, Option $email, Option $phone): Option {
    return $name
        ->zip($email)                    // Some([name, email]) or None
        ->andThen(fn($pair) => 
            $phone->map(fn($p) => [...$pair, $p])  // Some([name, email, phone]) or None
        );
}

$validForm = combineFormData(
    Some::of("Alice Smith"),
    Some::of("alice@example.com"),
    Some::of("123-456-7890")
); // Some(["Alice Smith", "alice@example.com", "123-456-7890"])

$incompleteForm = combineFormData(
    Some::of("Bob Jones"),
    None::instance(),
    Some::of("987-654-3210")
); // None

// Combined usage of xor and zip
function selectUserInput(Option $primaryInput, Option $fallbackInput): Option {
    // Expect only one of the two to be input
    return $primaryInput
        ->xor($fallbackInput)                           // Some(input) or None
        ->zip(Some::of("validated"))                    // Some([input, "validated"]) or None
        ->map(fn($pair) => ['input' => $pair[0], 'status' => $pair[1]]);
}

// Valid case (only one input)
$result1 = selectUserInput(Some::of("primary"), None::instance());
// Some(['input' => 'primary', 'status' => 'validated'])

// Invalid case (both input or both empty)
$result2 = selectUserInput(Some::of("primary"), Some::of("fallback")); // None
$result3 = selectUserInput(None::instance(), None::instance()); // None

// Complex option processing: Configuration priority management
function mergeConfigs(Option $userConfig, Option $defaultConfig): Option {
    return $userConfig
        ->or($defaultConfig)                            // Prioritize user config
        ->zip(Some::of(time()))                         // Add timestamp
        ->map(fn($pair) => [
            'config' => $pair[0],
            'loaded_at' => $pair[1],
            'source' => $userConfig->isSome() ? 'user' : 'default'
        ]);
}
```

## Type Annotation Details

### Generics Expression Method

Generics expression using PHPDoc:

```php
// Result type implementation
/**
 * @template T
 * @implements Result<T, never>
 */
final class Ok implements Result { }

/**
 * @template E
 * @implements Result<never, E>
 */
final class Err implements Result { }

// Option type implementation
/**
 * @template T
 * @implements Option<T>
 */
final class Some implements Option { }

/**
 * @implements Option<never>
 */
final class None implements Option { }
```

Both interfaces declare covariant type parameters:

```php
/**
 * @template-covariant T The type of the success value
 * @template-covariant E The type of the error value
 */
interface Result

/**
 * @template-covariant T The type of the value
 */
interface Option
```

Covariance lets a `Result` with a narrower value/error type be used where a
wider one is expected (e.g. `Result<never, string>` flows into an
`orElse(fn (string $e) ...)` stage), which is what makes the Pipe operator
adapters type-check in chained pipelines.

### never Type Usage

**Result type:**
- `Ok<T>` implements `Result<T, never>` (error type does not exist)
- `Err<E>` implements `Result<never, E>` (success type does not exist)

**Option type:**
- `Some<T>` implements `Option<T>`
- `None` implements `Option<never>` (value type does not exist)

## Performance Characteristics

### Memory Usage
- Minimal overhead
- Holds references instead of copying values

### Execution Speed
- Simple methods that can be inlined
- Exception handling only in exceptional cases

## Comparison with Rust Standard Library

### Result Type - Implemented Features
- ✅ `is_ok()` / `is_err()` - Success/failure determination
- ✅ `is_ok_and()` / `is_err_and()` - Success/failure determination + condition check
- ✅ `map()` / `map_err()` - Value/error transformation
- ✅ `map_or()` / `map_or_else()` - Success transformation + failure default value
- ✅ `and_then()` - Monadic chain processing
- ✅ `unwrap()` / `unwrap_err()` - Value/error extraction (with exceptions)
- ✅ `unwrap_or()` / `unwrap_or_else()` - Safe value extraction
- ✅ `expect()` / `expect_err()` - Custom message value/error extraction
- ✅ `inspect()` / `inspect_err()` - Debug side effect execution
- ✅ `or()` / `or_else()` - Alternative Result provision
- ✅ `and()` - Continuous success checking
- ✅ `contains()` / `contains_err()` - Value existence confirmation (PHP-specific implementation)
- ✅ `flatten()` - Nested Result flattening
- ✅ `transpose()` - Mutual conversion with Option type
- ✅ `ok()` / `err()` - Result → Option conversion

### Option Type - Implemented Features
- ✅ `is_some()` / `is_none()` - Value presence determination
- ✅ `is_some_and()` - Value presence determination + condition check
- ✅ `map()` / `map_or()` / `map_or_else()` - Value transformation
- ✅ `and_then()` - Monadic chain processing
- ✅ `filter()` - Conditional filtering
- ✅ `unwrap()` - Value extraction (with exceptions)
- ✅ `unwrap_or()` / `unwrap_or_else()` - Safe value extraction
- ✅ `expect()` - Custom message value extraction
- ✅ `inspect()` - Debug side effect execution
- ✅ `or()` / `or_else()` - Alternative Option provision
- ✅ `and()` - Continuous value checking
- ✅ `contains()` - Value existence confirmation
- ✅ `transpose()` - Mutual conversion with Result type
- ✅ `ok_or()` / `ok_or_else()` - Result type conversion
- ✅ `flatten()` - Nested Option flattening
- ✅ `xor()` - Exclusive OR operation
- ✅ `zip()` - Multiple Option combination

### Option Type - Non-supported Features
- ❌ `replace()` - Value replacement (intentionally not supported by design)
  - Rust's `replace()` requires destructive assignment via `&mut self`
  - Structurally impossible under this library's immutable (readonly) design
  - In PHP, variable reassignment (`$opt = Some::of($new)`) is the equivalent operation

### Differences and Constraints
- Due to PHP's type system constraints, compile-time type checking is limited
- `never` type is not fully supported
- Pattern matching is not available
- None is implemented with singleton pattern (Rust uses value type)

## Error Message Specifications

### UnwrapException Message Format

**Result type:**
- When unwrapErr() is called on Ok value:
  ```
  Called unwrapErr() on an Ok value: [print_r representation of value]
  ```
- When unwrap() is called on Err value:
  ```
  Called unwrap() on an Err value: [print_r representation of error]
  ```
- Custom message with expect():
  ```
  [Custom message]: [print_r representation of error]
  ```

**Option type:**
- When unwrap() is called on None value:
  ```
  Called unwrap() on a None value
  ```
- When expect() is called on None value:
  ```
  [Custom message]
  ```

## Extensibility

### Future Extension Plans
1. **Additional Option type methods**: New method additions (`replace()` intentionally not supported by design, see above)
2. **Better error representation**: Structured error information, detailed stack traces
3. **Enhanced debug support features**: More detailed inspect functionality
4. **Performance optimization**: Reduced memory usage, improved execution speed
5. **Interoperability**: Integration support with existing PHP libraries

### Recommended Custom Error Type Patterns

```php
interface ErrorType {
    public function getMessage(): string;
    public function getCode(): int;
}

class ValidationError implements ErrorType {
    public function __construct(
        private readonly string $field,
        private readonly string $message
    ) {}
    
    public function getMessage(): string {
        return "Validation failed for {$this->field}: {$this->message}";
    }
    
    public function getCode(): int {
        return 400;
    }
}
```

## Implementation History and Notes

### Complete Option Type Implementation (2025-07-13 Implemented)
- **Architecture**: Rust-compatible Option<T> type realized in PHP
- **Implementation Classes**: Option(interface), Some(final), None(final singleton)
- **Key Methods**: isSome/isNone, map series, unwrap series, andThen, filter, inspect, combination operations, contains
- **None Design**: Memory efficiency through singleton pattern
- **Testing**: Comprehensive verification with 59 test cases (basic 33 + transpose 15 + conversion 11)
- **Features**: Faithful to Rust specifications, complete compatibility with existing Result type

### transpose() Method (2025-07-13 Implemented)
- **Function**: Mutual conversion between Option/Result (Rust-compatible)
- **Conversion Rules**: 
  - Option side: Some(Ok(v))→Ok(Some(v)), Some(Err(e))→Err(e), None→Ok(None)
  - Result side: Ok(Some(v))→Some(Ok(v)), Ok(None)→None, Err(e)→Some(Err(e))
- **Type Safety**: Return types explicitly declared as `Result<mixed,mixed>`/`Option<mixed>` for PHPStan support
- **Implementation Location**: Result/Option both interfaces and all implementation classes
- **Test Strategy**: 15 tests verifying mutual conversion completeness, error propagation, and composite cases

### Option-Result Mutual Conversion (2025-07-13 Implemented)
- **okOr()**: Option→Result conversion (None becomes Err with specified error)
- **okOrElse()**: Option→Result conversion (None becomes Err with closure result, lazy evaluation)
- **Implementation Note**: Some values always become Ok, None always becomes Err
- **Type Safety**: Return type Result<mixed,mixed> for PHPStan support

### flatten() Method (2025-07-13 Implemented)

#### Result Type flatten()
- **Function**: One-level flattening of nested Results
- **Rust Support**: `Result<Result<T, E>, E>` → `Result<T, E>` conversion
- **Behavior**: Ok(Result) → Result, Ok(non-Result) → Ok, Err → Err
- **Features**: Only one level flattening, multiple nesting requires stepwise processing
- **Type Check**: Runtime determination using `instanceof Result`
- **Testing**: 25 test cases including edge cases and performance tests

#### Option Type flatten() (2025-07-13 Implemented)
- **Function**: One-level flattening of nested Options (Rust-compatible)
- **Rust Support**: `Option<Option<T>>` → `Option<T>` conversion
- **Behavior Rules**:
  - Some(Some(value)) → Some(value)
  - Some(None) → None
  - Some(non-Option value) → Some(non-Option value) (returns self)
  - None → None (returns self)
- **Type Check**: Runtime determination using `instanceof Option`
- **Implementation Location**: Option(interface), Some(final), None(final)
- **Testing**: 15 test cases (basic 4 + edge 7 + type safety 2 + performance 1 + practical 1), 1038 assertions

### contains() / containsErr() Methods (2025-07-13 Implemented)
- **Function**: Value existence confirmation (PHP-specific implementation, not in Rust)
- **Comparison Method**: Strict comparison (`===`) adopted
- **Behavior**: `containsErr()` on Ok values, `contains()` on Err values always return `false`
- **Testing**: Comprehensive edge case testing implemented (null, objects, arrays, type conversion)
- **Option Type**: Same behavior for Some/None, contains() on None always returns false

### and() Method (2025-07-13 Implemented)
- **Function**: Continuous success checking (eager evaluation)
- **Behavior**: Returns argument Result for Ok, returns self for Err
- **Chaining**: Multiple Results can be sequentially combined
- **Option Type**: Returns argument Option for Some, returns self for None

### Result Type Advanced Conversion Methods (2025-07-13 Implemented)
- **ok()**: Get success value as Option<T> (Rust-compatible)
  - Ok(value) → Some(value)
  - Err(error) → None
- **err()**: Get error value as Option<E> (Rust-compatible)
  - Ok(value) → None
  - Err(error) → Some(error)
- **expectErr()**: Extract error value with custom message (Rust-compatible)
  - Ok(value) → UnwrapException(message + value info)
  - Err(error) → error
- **Usage**: Diversification of error handling patterns, improved debugging experience
- **Type Safety**: PHPStan-compatible type annotations
- **Testing**: 15 test cases implemented in ResultConversionTest.php

### Option Type Advanced Combination Methods (2025-07-13 Implemented)
- **xor()**: Exclusive OR operation (Rust-compatible)
  - Some(a).xor(None) → Some(a)
  - None.xor(Some(b)) → Some(b)
  - Some(a).xor(Some(b)) → None
  - None.xor(None) → None
- **zip()**: Combination of two Options (Rust-compatible)
  - Some(a).zip(Some(b)) → Some([a, b])
  - Some(a).zip(None) → None
  - None.zip(Some(b)) → None
  - None.zip(None) → None
- **Usage**: Simplification of conditional branching logic, synchronized processing of multiple values
- **Type Safety**: PHPDoc Generics expressing array{T, U}
- **Testing**: 20 test cases implemented in OptionAdvancedTest.php

## Limitations

### PHP Language-Specific Limitations
- No true Generics support
- Pattern matching not available
- No complete `never` type support
- Compile-time type checking limitations

### Design Limitations
- Simple error display using `print_r()`
- Limited exception stack trace information
- Room for memory usage optimization
- Constraints from None's singleton pattern (thread safety)

### Option Type-Specific Limitations
- `replace()` method intentionally not supported (conflict between readonly design and destructive assignment)
- Native null checking is faster for performance-critical processing
- Value inspection more complex than Result type during debugging

## Shorthand Methods

### Overview
Shorthand method groups that improve code conciseness and readability. These methods realize combinations of existing methods in a single method, streamlining common patterns.

### Result Type Shorthand Methods

#### isOkAnd(callable $predicate): bool
Executes predicate function only on success and returns the result.

```php
// Traditional approach
if ($result->isOk() && $predicate($result->unwrap())) {
    // ...
}

// Shorthand method
if ($result->isOkAnd($predicate)) {
    // ...
}
```

**Behavior:**
- `Ok` case: Applies predicate function to stored value and returns result
- `Err` case: Always returns `false` (predicate function not executed)

#### isErrAnd(callable $predicate): bool
Executes predicate function only on failure and returns the result.

```php
// Traditional approach
if ($result->isErr() && $predicate($result->unwrapErr())) {
    // ...
}

// Shorthand method
if ($result->isErrAnd($predicate)) {
    // ...
}
```

**Behavior:**
- `Err` case: Applies predicate function to stored error and returns result
- `Ok` case: Always returns `false` (predicate function not executed)

#### mapOr(callable $fn, mixed $default): mixed
Transforms value on success, returns default value on failure.

```php
// Traditional approach
$value = $result->isOk() 
    ? $fn($result->unwrap()) 
    : $default;

// Shorthand method
$value = $result->mapOr($fn, $default);
```

**Behavior:**
- `Ok` case: Returns result of applying function to stored value
- `Err` case: Returns default value unchanged

#### mapOrElse(callable $fn, callable $defaultFn): mixed
Transforms value on success, executes default function with error value on failure.

```php
// Traditional approach
$value = $result->isOk() 
    ? $fn($result->unwrap()) 
    : $defaultFn($result->unwrapErr());

// Shorthand method
$value = $result->mapOrElse($fn, $defaultFn);
```

**Behavior:**
- `Ok` case: Returns result of applying transformation function to stored value
- `Err` case: Returns result of passing error value to default function (lazy evaluation)

### Option Type Shorthand Methods

#### isSomeAnd(callable $predicate): bool
Returns true only when value is present and satisfies predicate function.

```php
// Traditional approach
if ($option->isSome() && $predicate($option->unwrap())) {
    // ...
}

// Shorthand method
if ($option->isSomeAnd($predicate)) {
    // ...
}
```

**Behavior:**
- `Some` case: Applies predicate function to stored value and returns result
- `None` case: Always returns `false` (predicate function not executed)

### Usage Examples

#### Number range checking
```php
// Result type usage
$parseResult = parseInteger($input);
$isValidRange = $parseResult->isOkAnd(fn($n) => $n >= 1 && $n <= 100);

// Option type usage
$maybeAge = findUserAge($userId);
$isAdult = $maybeAge->isSomeAnd(fn($age) => $age >= 18);
```

#### Error type determination
```php
$apiResult = callExternalAPI();
$isNetworkError = $apiResult->isErrAnd(fn($err) => $err instanceof NetworkException);
```

#### Transformation and default values
```php
// Result type mapOr
$displayValue = $parseResult->mapOr(
    fn($num) => "Value: $num",
    "Invalid input"
);

// Result type mapOrElse (dynamic default value)
$result = $apiCall->mapOrElse(
    fn($data) => processData($data),
    fn($error) => "Error[{$error->getCode()}]: {$error->getMessage()}"
);
```

### Type Safety
All shorthand methods have appropriate PHPDoc type annotations and guarantee type safety at PHPStan Level MAX.

```php
/**
 * @template T
 * @param callable(T): bool $predicate
 * @return bool
 */
public function isOkAnd(callable $predicate): bool;

/**
 * @template U
 * @param callable(T): U $fn
 * @param U $default
 * @return U
 */
public function mapOr(callable $fn, mixed $default): mixed;
```

### Performance Characteristics
- Performance equivalent to existing methods
- Efficient with function execution skipped based on conditions
- No memory overhead

This specification provides complete technical specifications for the PHP Result/Option type library, including current implementation status and future extension plans.