# Result Type API Reference

The Result type is a generic type that represents success/failure states, providing a type-safe interface for error handling.

## Table of Contents

1. [Type Definition](#type-definition)
2. [State Check Methods](#state-check-methods)
3. [Transformation Methods](#transformation-methods)
4. [Value Extraction Methods](#value-extraction-methods)
5. [Inspection Methods](#inspection-methods)
6. [Combination Methods](#combination-methods)
7. [Utility Methods](#utility-methods)
8. [Type Conversion Methods](#type-conversion-methods)
9. [Implementation Classes](#implementation-classes)

## Type Definition

```php
/**
 * Type for representing success/failure states
 *
 * @template T The type of the success value
 * @template E The type of the error value
 */
interface Result
```

## State Check Methods

### isOk(): bool

Checks if the result is in a success state.

**Signature:**
```php
public function isOk(): bool
```

**Return value:**
- `true`: If in Ok state
- `false`: If in Err state

**Usage example:**
```php
$result = Ok::of(42);
if ($result->isOk()) {
    echo "Success: " . $result->unwrap();
}

$errorResult = Err::of("error");
var_dump($errorResult->isOk()); // false
```

**Related methods:** [isErr()](#iserr-bool), [isOkAnd()](#isokand)

---

### isErr(): bool

Checks if the result is in a failure state.

**Signature:**
```php
public function isErr(): bool
```

**Return value:**
- `true`: If in Err state
- `false`: If in Ok state

**Usage example:**
```php
$result = Err::of("some error");
if ($result->isErr()) {
    echo "Error: " . $result->unwrapErr();
}
```

**Related methods:** [isOk()](#isok-bool), [isErrAnd()](#iserrand)

---

### isOkAnd(callable $predicate): bool

Checks if the result is in success state and satisfies the predicate function.

**Signature:**
```php
/**
 * @param callable(T): bool $predicate
 * @return bool
 */
public function isOkAnd(callable $predicate): bool
```

**Parameters:**
- `$predicate`: Validation function for the success value

**Return value:**
- If Ok state and satisfies predicate: `true`
- If Err state or doesn't satisfy predicate: `false`

**Usage example:**
```php
$result = Ok::of(10);
$isPositive = $result->isOkAnd(fn($x) => $x > 0); // true
$isNegative = $result->isOkAnd(fn($x) => $x < 0); // false

$errorResult = Err::of("error");
$anyCheck = $errorResult->isOkAnd(fn($x) => true); // false (because it's Err)
```

**Shorthand method:** Enables more concise conditional checks

**Related methods:** [isOk()](#isok-bool), [contains()](#contains)

---

### isErrAnd(callable $predicate): bool

Checks if the result is in failure state and satisfies the predicate function.

**Signature:**
```php
/**
 * @param callable(E): bool $predicate
 * @return bool
 */
public function isErrAnd(callable $predicate): bool
```

**Parameters:**
- `$predicate`: Validation function for the error value

**Return value:**
- If Err state and satisfies predicate: `true`
- If Ok state or doesn't satisfy predicate: `false`

**Usage example:**
```php
$result = Err::of("not found");
$isNotFoundError = $result->isErrAnd(fn($err) => str_contains($err, "not found")); // true

$successResult = Ok::of(42);
$anyErrorCheck = $successResult->isErrAnd(fn($err) => true); // false (because it's Ok)
```

**Related methods:** [isErr()](#iserr-bool), [containsErr()](#containserr)

## Transformation Methods

### map(callable $fn): Result

Applies a function to the success value and creates a new Result.

**Signature:**
```php
/**
 * @template U
 * @param callable(T): U $fn
 * @return Result<U, E>
 */
public function map(callable $fn): Result
```

**Parameters:**
- `$fn`: Transformation function to apply to the success value

**Return value:**
- Ok state: Ok with transformed value
- Err state: Original Err (transformation not executed)

**Usage example:**
```php
$result = Ok::of(5);
$doubled = $result->map(fn($x) => $x * 2); // Ok(10)

$errorResult = Err::of("error");
$notExecuted = $errorResult->map(fn($x) => $x * 2); // Err("error") - function not executed
```

**Error handling:**
```php
// If an exception occurs in the transformation function, it propagates as-is
$result = Ok::of("invalid");
try {
    $converted = $result->map(fn($x) => intval($x) / 0); // ZeroDivisionError
} catch (DivisionByZeroError $e) {
    // Exception handling
}
```

**Related methods:** [mapErr()](#maperr), [mapOr()](#mapor), [mapOrElse()](#maporelse), [andThen()](#andthen)

---

### mapErr(callable $fn): Result

Applies a function to the error value and creates a new Result.

**Signature:**
```php
/**
 * @template F
 * @param callable(E): F $fn
 * @return Result<T, F>
 */
public function mapErr(callable $fn): Result
```

**Parameters:**
- `$fn`: Transformation function to apply to the error value

**Return value:**
- Err state: Err with transformed error value
- Ok state: Original Ok (transformation not executed)

**Usage example:**
```php
$result = Err::of("user not found");
$localized = $result->mapErr(fn($err) => "User not found"); // Err("User not found")

$successResult = Ok::of(42);
$unchanged = $successResult->mapErr(fn($err) => "not transformed"); // Ok(42)
```

**Practical example:**
```php
// Structuring error logs
$result = $this->databaseOperation()
    ->mapErr(fn($err) => [
        'type' => 'database_error',
        'message' => $err,
        'timestamp' => time()
    ]);
```

**Related methods:** [map()](#map), [inspectErr()](#inspecterr)

---

### mapOr(callable $fn, mixed $default): mixed

Applies a function to the success value, or returns a default value on failure.

**Signature:**
```php
/**
 * @template U
 * @param callable(T): U $fn
 * @param U $default
 * @return U
 */
public function mapOr(callable $fn, mixed $default): mixed
```

**Parameters:**
- `$fn`: Transformation function to apply to the success value
- `$default`: Default value for failure cases

**Return value:**
- Ok state: Result of applying `$fn`
- Err state: `$default`

**Usage example:**
```php
$result = Ok::of(5);
$doubled = $result->mapOr(fn($x) => $x * 2, 0); // 10

$errorResult = Err::of("error");
$defaultUsed = $errorResult->mapOr(fn($x) => $x * 2, 0); // 0
```

**Shorthand benefit:**
```php
// Traditional approach
$value = $result->map(fn($x) => $x * 2)->unwrapOr(0);

// Using mapOr()
$value = $result->mapOr(fn($x) => $x * 2, 0); // More efficient
```

**Related methods:** [map()](#map), [mapOrElse()](#maporelse), [unwrapOr()](#unwrapor)

---

### mapOrElse(callable $fn, callable $defaultFn): mixed

Applies a function to the success value, or executes a closure on failure.

**Signature:**
```php
/**
 * @template U
 * @param callable(T): U $fn
 * @param callable(E): U $defaultFn
 * @return U
 */
public function mapOrElse(callable $fn, callable $defaultFn): mixed
```

**Parameters:**
- `$fn`: Transformation function to apply to the success value
- `$defaultFn`: Closure to execute on failure (receives error value)

**Return value:**
- Ok state: Result of executing `$fn`
- Err state: Result of executing `$defaultFn`

**Usage example:**
```php
$result = Ok::of(5);
$value = $result->mapOrElse(
    fn($x) => $x * 2,
    fn($err) => strlen($err)
); // 10

$errorResult = Err::of("error message");
$errorLength = $errorResult->mapOrElse(
    fn($x) => $x * 2,
    fn($err) => strlen($err)
); // 13
```

**Lazy evaluation benefit:**
```php
// Heavy processing only executed on failure
$result = $someOperation->mapOrElse(
    fn($data) => $data->process(),
    fn($err) => $this->generateExpensiveDefault($err) // Not executed on Ok
);
```

**Related methods:** [mapOr()](#mapor), [unwrapOrElse()](#unwraporelse)

---

### andThen(callable $fn): Result

Applies a function that returns a Result to the success value (monadic chaining).

**Signature:**
```php
/**
 * @template U
 * @template F
 * @param callable(T): Result<U, F> $fn
 * @return Result<U, E|F>
 */
public function andThen(callable $fn): Result
```

**Parameters:**
- `$fn`: Function that takes success value and returns a Result

**Return value:**
- Ok state: Result returned by `$fn`
- Err state: Original Err

**Usage example:**
```php
function divide(int $a, int $b): Result {
    return $b === 0 ? Err::of("division by zero") : Ok::of($a / $b);
}

$result = Ok::of(10)
    ->andThen(fn($x) => divide($x, 2))  // Ok(5)
    ->andThen(fn($x) => divide($x, 0)); // Err("division by zero")
```

**Complex chaining:**
```php
$result = $this->getUser($id)
    ->andThen(fn($user) => $this->validateUser($user))
    ->andThen(fn($user) => $this->authorizeUser($user))
    ->andThen(fn($user) => $this->processUser($user));
```

**Relationship to flatMap:** `andThen` corresponds to `flatMap` in other languages

**Related methods:** [map()](#map), [flatten()](#flatten)

## Value Extraction Methods

### unwrap(): mixed

Gets the success value. Throws an exception on failure.

**Signature:**
```php
/**
 * @return T
 * @throws UnwrapException
 */
public function unwrap(): mixed
```

**Return value:**
- Ok state: Success value
- Err state: Throws UnwrapException

**Usage example:**
```php
$result = Ok::of(42);
$value = $result->unwrap(); // 42

$errorResult = Err::of("error");
try {
    $value = $errorResult->unwrap(); // UnwrapException
} catch (UnwrapException $e) {
    echo $e->getMessage(); // "Called unwrap() on an Err value: error"
}
```

**⚠️ Caution:**
- To avoid unexpected exceptions, it's recommended to check with `isOk()` first or use `unwrapOr()`
- Use carefully in production code

**Related methods:** [unwrapOr()](#unwrapor), [unwrapOrElse()](#unwraporelse), [expect()](#expect)

---

### unwrapErr(): mixed

Gets the error value. Throws an exception on success.

**Signature:**
```php
/**
 * @return E
 * @throws UnwrapException
 */
public function unwrapErr(): mixed
```

**Return value:**
- Err state: Error value
- Ok state: Throws UnwrapException

**Usage example:**
```php
$result = Err::of("file not found");
$error = $result->unwrapErr(); // "file not found"

$successResult = Ok::of(42);
try {
    $error = $successResult->unwrapErr(); // UnwrapException
} catch (UnwrapException $e) {
    echo $e->getMessage(); // "Called unwrapErr() on an Ok value: 42"
}
```

**Related methods:** [unwrap()](#unwrap), [expectErr()](#expecterr)

---

### unwrapOr(mixed $default): mixed

Gets the success value, or returns a default value on failure.

**Signature:**
```php
/**
 * @template U
 * @param U $default
 * @return T|U
 */
public function unwrapOr(mixed $default): mixed
```

**Parameters:**
- `$default`: Default value for failure cases

**Return value:**
- Ok state: Success value
- Err state: `$default`

**Usage example:**
```php
$result = Ok::of(42);
$value = $result->unwrapOr(0); // 42

$errorResult = Err::of("error");
$value = $errorResult->unwrapOr(0); // 0
```

**Practical examples:**
```php
// Getting configuration values
$port = $config->getPort()->unwrapOr(8080);

// Getting username
$username = $session->getUser()
    ->map(fn($user) => $user->getName())
    ->unwrapOr('Guest');
```

**Related methods:** [unwrap()](#unwrap), [unwrapOrElse()](#unwraporelse), [mapOr()](#mapor)

---

### unwrapOrElse(callable $fn): mixed

Gets the success value, or returns the result of a closure on failure.

**Signature:**
```php
/**
 * @template U
 * @param callable(E): U $fn
 * @return T|U
 */
public function unwrapOrElse(callable $fn): mixed
```

**Parameters:**
- `$fn`: Function that receives error value and generates alternative value

**Return value:**
- Ok state: Success value
- Err state: Result of executing `$fn`

**Usage example:**
```php
$result = Ok::of(42);
$value = $result->unwrapOrElse(fn($err) => strlen($err)); // 42

$errorResult = Err::of("error");
$value = $errorResult->unwrapOrElse(fn($err) => strlen($err)); // 5
```

**Lazy evaluation benefit:**
```php
// Heavy processing only executed on failure
$value = $result->unwrapOrElse(fn($err) => $this->generateExpensiveDefault($err));
```

**Related methods:** [unwrapOr()](#unwrapor), [mapOrElse()](#maporelse)

---

### expect(string $message): mixed

Gets the success value. Throws an exception with custom message on failure.

**Signature:**
```php
/**
 * @param string $message
 * @return T
 * @throws UnwrapException
 */
public function expect(string $message): mixed
```

**Parameters:**
- `$message`: Exception message for failure cases

**Return value:**
- Ok state: Success value
- Err state: Throws UnwrapException with custom message

**Usage example:**
```php
$result = Ok::of(42);
$value = $result->expect("Value is required"); // 42

$errorResult = Err::of("file error");
try {
    $value = $errorResult->expect("Failed to load configuration file");
} catch (UnwrapException $e) {
    echo $e->getMessage(); // "Failed to load configuration file: file error"
}
```

**Usage in debugging:**
```php
// Clarify expected situations
$user = $session->getUser()
    ->expect("Authenticated user required");
```

**Related methods:** [unwrap()](#unwrap), [expectErr()](#expecterr)

---

### expectErr(string $message): mixed

Gets the error value. Throws an exception with custom message on success.

**Signature:**
```php
/**
 * @param string $message
 * @return E
 * @throws UnwrapException
 */
public function expectErr(string $message): mixed
```

**Parameters:**
- `$message`: Exception message for success cases

**Return value:**
- Err state: Error value
- Ok state: Throws UnwrapException with custom message

**Usage example:**
```php
$result = Err::of("authentication error");
$error = $result->expectErr("Error should have occurred"); // "authentication error"

$successResult = Ok::of(42);
try {
    $error = $successResult->expectErr("Testing error case");
} catch (UnwrapException $e) {
    echo $e->getMessage(); // "Testing error case: 42"
}
```

**Usage in testing:**
```php
// Testing error cases
$result = $service->invalidOperation();
$error = $result->expectErr("Invalid operation should result in error");
$this->assertEquals("invalid operation", $error);
```

**Related methods:** [expect()](#expect), [unwrapErr()](#unwraperr)

## Inspection Methods

### inspect(callable $fn): Result

Inspects the success value and executes side effects (value remains unchanged).

**Signature:**
```php
/**
 * @param callable(T): void $fn
 * @return Result<T, E>
 */
public function inspect(callable $fn): Result
```

**Parameters:**
- `$fn`: Inspection function that receives success value (side effects only)

**Return value:** Original Result (unchanged)

**Usage example:**
```php
$result = Ok::of(42)
    ->inspect(fn($x) => error_log("Success value: $x"))
    ->map(fn($x) => $x * 2);
// Log output: "Success value: 42"
// Result: Ok(84)

$errorResult = Err::of("error")
    ->inspect(fn($x) => error_log("Success value: $x")); // Not executed
```

**Usage in debugging:**
```php
$result = $this->complexOperation()
    ->inspect(fn($data) => $this->logProcessingStep('step1', $data))
    ->map(fn($data) => $this->transformData($data))
    ->inspect(fn($data) => $this->logProcessingStep('step2', $data));
```

**Related methods:** [inspectErr()](#inspecterr), [map()](#map)

---

### inspectErr(callable $fn): Result

Inspects the error value and executes side effects (error remains unchanged).

**Signature:**
```php
/**
 * @param callable(E): void $fn
 * @return Result<T, E>
 */
public function inspectErr(callable $fn): Result
```

**Parameters:**
- `$fn`: Inspection function that receives error value (side effects only)

**Return value:** Original Result (unchanged)

**Usage example:**
```php
$result = Err::of("database error")
    ->inspectErr(fn($err) => error_log("Error occurred: $err"))
    ->mapErr(fn($err) => "internal error");
// Log output: "Error occurred: database error"
// Result: Err("internal error")

$successResult = Ok::of(42)
    ->inspectErr(fn($err) => error_log("Error: $err")); // Not executed
```

**Usage in error monitoring:**
```php
$result = $this->criticalOperation()
    ->inspectErr(fn($err) => $this->alertSystem->notify($err))
    ->inspectErr(fn($err) => $this->logger->error('Critical failure', ['error' => $err]));
```

**Related methods:** [inspect()](#inspect), [mapErr()](#maperr)

## Combination Methods

### or(Result $res): Result

Returns alternative Result if Err (eager evaluation).

**Signature:**
```php
/**
 * @template U
 * @template F
 * @param Result<U, F> $res
 * @return Result<T|U, F>
 */
public function or(Result $res): Result
```

**Parameters:**
- `$res`: Alternative Result

**Return value:**
- Ok state: Original Ok
- Err state: `$res`

**Usage example:**
```php
$primary = Ok::of(42);
$fallback = Ok::of(100);
$result = $primary->or($fallback); // Ok(42)

$primaryFailed = Err::of("primary error");
$fallbackSuccess = Ok::of(100);
$result = $primaryFailed->or($fallbackSuccess); // Ok(100)
```

**Multiple fallbacks:**
```php
$result = $this->primarySource()
    ->or($this->secondarySource())
    ->or($this->tertiarySource())
    ->or(Ok::of($this->defaultValue()));
```

**Related methods:** [orElse()](#orelse), [and()](#and)

---

### orElse(callable $fn): Result

Returns alternative Result if Err (lazy evaluation).

**Signature:**
```php
/**
 * @template U
 * @template F
 * @param callable(E): Result<U, F> $fn
 * @return Result<T|U, F>
 */
public function orElse(callable $fn): Result
```

**Parameters:**
- `$fn`: Function that receives error value and returns Result

**Return value:**
- Ok state: Original Ok
- Err state: Result of executing `$fn`

**Usage example:**
```php
$result = Err::of("not found")
    ->orElse(fn($err) => str_contains($err, "not found") 
        ? Ok::of("default value") 
        : Err::of("unexpected error"));
// Ok("default value")
```

**Error type branching:**
```php
$result = $this->fetchData()
    ->orElse(fn($err) => match($err['type']) {
        'timeout' => $this->fetchFromCache(),
        'network' => $this->fetchFromBackup(),
        default => Err::of('fatal error')
    });
```

**Related methods:** [or()](#or), [unwrapOrElse()](#unwraporelse)

---

### and(Result $res): Result

Returns another Result if Ok, returns self if Err (eager evaluation).

**Signature:**
```php
/**
 * @template U
 * @template F
 * @param Result<U, F> $res
 * @return Result<U, E|F>
 */
public function and(Result $res): Result
```

**Parameters:**
- `$res`: Result to evaluate sequentially

**Return value:**
- Ok state: `$res`
- Err state: Original Err

**Usage example:**
```php
$step1 = Ok::of("step1 complete");
$step2 = Ok::of("step2 complete");
$result = $step1->and($step2); // Ok("step2 complete")

$step1Failed = Err::of("step1 error");
$step2 = Ok::of("step2 complete");
$result = $step1Failed->and($step2); // Err("step1 error")
```

**Sequential processing:**
```php
$result = $this->validateInput($data)
    ->and($this->checkPermissions($user))
    ->and($this->processRequest($data));
```

**Difference from andThen:** `and` continues without using the value, `andThen` transforms using the value

**Related methods:** [andThen()](#andthen), [or()](#or)

## Utility Methods

### contains(mixed $value): bool

Checks if Ok value contains the specified value.

**Signature:**
```php
/**
 * @param mixed $value The value to check
 * @return bool true if Ok value strictly equals the specified value, false otherwise
 */
public function contains(mixed $value): bool
```

**Parameters:**
- `$value`: Value to check

**Return value:**
- If Ok state and value matches: `true`
- If Ok state and value doesn't match or Err state: `false`

**Usage example:**
```php
$result = Ok::of(42);
var_dump($result->contains(42));   // true
var_dump($result->contains("42")); // false (strict comparison)
var_dump($result->contains(100));  // false

$errorResult = Err::of("error");
var_dump($errorResult->contains(42)); // false (because it's Err)
```

**Importance of strict comparison:**
```php
$result = Ok::of([1, 2, 3]);
var_dump($result->contains([1, 2, 3])); // true
var_dump($result->contains([1, 2]));    // false

$objectResult = Ok::of(new stdClass());
var_dump($objectResult->contains(new stdClass())); // false (different instances)
```

**PHP-specific feature:** Not available in Rust's standard Result type, convenient method for PHP

**Related methods:** [containsErr()](#containserr), [isOkAnd()](#isokand)

---

### containsErr(mixed $error): bool

Checks if Err value contains the specified error.

**Signature:**
```php
/**
 * @param mixed $error The error value to check
 * @return bool true if Err value strictly equals the specified error, false otherwise
 */
public function containsErr(mixed $error): bool
```

**Parameters:**
- `$error`: Error value to check

**Return value:**
- If Err state and value matches: `true`
- If Err state and value doesn't match or Ok state: `false`

**Usage example:**
```php
$result = Err::of("not found");
var_dump($result->containsErr("not found")); // true
var_dump($result->containsErr("timeout"));   // false

$successResult = Ok::of(42);
var_dump($successResult->containsErr("not found")); // false (because it's Ok)
```

**Error type checking:**
```php
$result = Err::of(['type' => 'validation', 'field' => 'email']);
var_dump($result->containsErr(['type' => 'validation', 'field' => 'email'])); // true
```

**Related methods:** [contains()](#contains), [isErrAnd()](#iserrand)

---

### flatten(): Result

Flattens a nested Result by one level.

**Signature:**
```php
/**
 * @return Result<T, E>
 */
public function flatten(): Result
```

**Return value:**
- Ok(Result): Inner Result
- Ok(non-Result): Original Ok
- Err: Original Err

**Usage example:**
```php
// Nested Result
$nested = Ok::of(Ok::of(42));
$flattened = $nested->flatten(); // Ok(42)

// Non-Result value
$notNested = Ok::of(42);
$unchanged = $notNested->flatten(); // Ok(42)

// Error case
$error = Err::of("error");
$stillError = $error->flatten(); // Err("error")
```

**Complex nesting:**
```php
// Ok(Ok(Err("inner error")))
$deepNested = Ok::of(Ok::of(Err::of("inner error")));
$oneLevel = $deepNested->flatten(); // Ok(Err("inner error"))
$fullyFlat = $oneLevel->flatten();  // Err("inner error")
```

**Runtime type checking:** Requires runtime determination with instanceof

**Related methods:** [andThen()](#andthen), [transpose()](#transpose)

## Type Conversion Methods

### transpose(): Option

Converts Result<Option<T>, E> → Option<Result<T, E>>.

**Signature:**
```php
/**
 * @return Option<mixed>
 */
public function transpose(): Option
```

**Conversion rules:**
- `Ok(Some(value))` → `Some(Ok(value))`
- `Ok(None)` → `None`
- `Err(error)` → `Some(Err(error))`
- `Ok(non-Option)` → `Some(Ok(value))`

**Usage example:**
```php
// Ok(Some(value)) → Some(Ok(value))
$result = Ok::of(Some::of(42));
$transposed = $result->transpose(); // Some(Ok(42))

// Ok(None) → None
$result = Ok::of(None::instance());
$transposed = $result->transpose(); // None

// Err(error) → Some(Err(error))
$result = Err::of("error");
$transposed = $result->transpose(); // Some(Err("error"))
```

**Practical usage example:**
```php
// Database query result processing
function findUser(int $id): Result {
    $userData = $this->database->find($id); // null | array
    return Ok::of(Option::of($userData));
}

$users = [1, 2, 3];
$userResults = array_map(fn($id) => $this->findUser($id), $users);
$transposed = array_map(fn($result) => $result->transpose(), $userResults);
// [Some(Ok(user1)), None, Some(Ok(user3))]
```

**Rust compatibility:** Same conversion rules as Rust's standard library

**Related methods:** [ok()](#ok), [err()](#err), [Option::transpose()](/docs/api/option_api_reference.md#transpose)

---

### ok(): Option

Gets the success value as Option.

**Signature:**
```php
/**
 * @return Option<T>
 */
public function ok(): Option
```

**Return value:**
- Ok state: `Some(value)`
- Err state: `None`

**Usage example:**
```php
$result = Ok::of(42);
$option = $result->ok(); // Some(42)

$errorResult = Err::of("error");
$option = $errorResult->ok(); // None
```

**Practical example:**
```php
// Extract only success values from a list of Results
$results = [$ok1, $err1, $ok2, $err2];
$values = array_filter(
    array_map(fn($r) => $r->ok(), $results),
    fn($opt) => $opt->isSome()
);
```

**Related methods:** [err()](#err), [transpose()](#transpose)

---

### err(): Option

Gets the error value as Option.

**Signature:**
```php
/**
 * @return Option<E>
 */
public function err(): Option
```

**Return value:**
- Err state: `Some(error)`
- Ok state: `None`

**Usage example:**
```php
$result = Err::of("file error");
$errorOption = $result->err(); // Some("file error")

$successResult = Ok::of(42);
$errorOption = $successResult->err(); // None
```

**Usage in error analysis:**
```php
// Collect only errors from a list of Results
$results = [$ok1, $err1, $ok2, $err2];
$errors = array_filter(
    array_map(fn($r) => $r->err(), $results),
    fn($opt) => $opt->isSome()
);
```

**Related methods:** [ok()](#ok), [unwrapErr()](#unwraperr)

## Implementation Classes

### Ok<T>

Class representing success.

```php
final class Ok implements Result
{
    public function __construct(private readonly mixed $value) {}
    public static function of(mixed $value): self {}
}
```

**Usage example:**
```php
$success = Ok::of(42);
$success = Ok::of("success message");
$success = Ok::of(['data' => 'value']);
```

### Err<E>

Class representing failure.

```php
final class Err implements Result
{
    public function __construct(private readonly mixed $error) {}
    public static function of(mixed $error): self {}
}
```

**Usage example:**
```php
$error = Err::of("error message");
$error = Err::of(['type' => 'validation', 'message' => 'Invalid input']);
$error = Err::of(new Exception("exception object"));
```

## Usage Considerations

### Performance Considerations
- Overhead from object wrapping
- Consider usage in high-frequency processing
- See [Performance Guide](/docs/guide/performance_guide.md) for details

### Type Safety
- PHPStan Level MAX compatible
- Generic type annotations recommended
- See [Type Error Troubleshooting](/docs/guide/debugging_guide.md#type-error-troubleshooting) for details

### Error Handling
- Use `unwrap()` methods carefully
- `unwrapOr()` methods recommended
- See [Best Practices](/docs/guide/best_practices.md) for details