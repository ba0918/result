# Option Type API Reference

The Option type is a generic type that represents presence/absence of a value, providing an interface for null safety.

## Table of Contents

1. [Type Definition](#type-definition)
2. [State Check Methods](#state-check-methods)
3. [Transformation Methods](#transformation-methods)
4. [Value Extraction Methods](#value-extraction-methods)
5. [Inspection Methods](#inspection-methods)
6. [Filtering Methods](#filtering-methods)
7. [Combination Methods](#combination-methods)
8. [Utility Methods](#utility-methods)
9. [Type Conversion Methods](#type-conversion-methods)
10. [Advanced Operation Methods](#advanced-operation-methods)
11. [Implementation Classes](#implementation-classes)

## Type Definition

```php
/**
 * Type for representing presence/absence of a value
 *
 * @template T The type of the value
 */
interface Option
```

## State Check Methods

### isSome(): bool

Checks if a value is present.

**Signature:**
```php
public function isSome(): bool
```

**Return value:**
- `true`: If in Some state (value present)
- `false`: If in None state (no value)

**Usage example:**
```php
$option = Some::of(42);
if ($option->isSome()) {
    echo "Value present: " . $option->unwrap();
}

$noneOption = None::instance();
var_dump($noneOption->isSome()); // false
```

**Related methods:** [isNone()](#isnone-bool), [isSomeAnd()](#issomeandcallable-predicate-bool)

---

### isNone(): bool

Checks if no value is present.

**Signature:**
```php
public function isNone(): bool
```

**Return value:**
- `true`: If in None state (no value)
- `false`: If in Some state (value present)

**Usage example:**
```php
$option = None::instance();
if ($option->isNone()) {
    echo "No value";
}

$someOption = Some::of("value");
var_dump($someOption->isNone()); // false
```

**Related methods:** [isSome()](#issome-bool), [contains()](#containsmixed-value-bool)

---

### isSomeAnd(callable $predicate): bool

Checks if a value is present and satisfies the predicate function.

**Signature:**
```php
/**
 * @param callable(T): bool $predicate
 * @return bool
 */
public function isSomeAnd(callable $predicate): bool
```

**Parameters:**
- `$predicate`: Validation function for the value

**Return value:**
- If Some state and satisfies predicate: `true`
- If None state or doesn't satisfy predicate: `false`

**Usage example:**
```php
$option = Some::of(10);
$isPositive = $option->isSomeAnd(fn($x) => $x > 0); // true
$isLarge = $option->isSomeAnd(fn($x) => $x > 100); // false

$noneOption = None::instance();
$anyCheck = $noneOption->isSomeAnd(fn($x) => true); // false (because it's None)
```

**Shorthand method:** Enables more concise conditional checks

**Practical example:**
```php
// User permission check
$user = $session->getUser();
$isAdmin = $user->isSomeAnd(fn($u) => $u->hasRole('admin'));
```

**Related methods:** [isSome()](#issome-bool), [contains()](#containsmixed-value-bool), [filter()](#filtercallable-predicate-option)

## Transformation Methods

### map(callable $fn): Option

Applies a function to the value and creates a new Option.

**Signature:**
```php
/**
 * @template U
 * @param callable(T): U $fn
 * @return Option<U>
 */
public function map(callable $fn): Option
```

**Parameters:**
- `$fn`: Transformation function to apply to the value

**Return value:**
- Some state: Some with transformed value
- None state: None (transformation not executed)

**Usage example:**
```php
$option = Some::of(5);
$doubled = $option->map(fn($x) => $x * 2); // Some(10)

$noneOption = None::instance();
$notExecuted = $noneOption->map(fn($x) => $x * 2); // None - function not executed
```

**String manipulation example:**
```php
$name = Some::of("john");
$capitalized = $name->map(fn($n) => ucfirst($n)); // Some("John")

$emptyName = None::instance();
$unchanged = $emptyName->map(fn($n) => ucfirst($n)); // None
```

**Chain operations:**
```php
$result = Some::of("  hello world  ")
    ->map(fn($s) => trim($s))
    ->map(fn($s) => strtoupper($s))
    ->map(fn($s) => str_replace(' ', '_', $s)); // Some("HELLO_WORLD")
```

**Related methods:** [mapOr()](#maporcallable-fn-mixed-default-mixed), [mapOrElse()](#maporelsecallable-fn-callable-defaultfn-mixed), [andThen()](#andthencallable-fn-option)

---

### mapOr(callable $fn, mixed $default): mixed

Applies a function to the value, or returns a default value if no value is present.

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
- `$fn`: Transformation function to apply to the value
- `$default`: Default value when no value is present

**Return value:**
- Some state: Result of applying `$fn`
- None state: `$default`

**Usage example:**
```php
$option = Some::of(5);
$doubled = $option->mapOr(fn($x) => $x * 2, 0); // 10

$noneOption = None::instance();
$defaultUsed = $noneOption->mapOr(fn($x) => $x * 2, 0); // 0
```

**Practical examples:**
```php
// Configuration value transformation
$configValue = $config->get('timeout')
    ->mapOr(fn($t) => $t * 1000, 5000); // Default 5 seconds

// User display name generation
$displayName = $user->getName()
    ->mapOr(fn($name) => "Hello, {$name}", "Guest User");
```

**Shorthand benefit:**
```php
// Traditional approach
$value = $option->map(fn($x) => $x * 2)->unwrapOr(0);

// Using mapOr()
$value = $option->mapOr(fn($x) => $x * 2, 0); // More efficient
```

**Related methods:** [map()](#mapcallable-fn-option), [mapOrElse()](#maporelsecallable-fn-callable-defaultfn-mixed), [unwrapOr()](#unwrapormixed-default-mixed)

---

### mapOrElse(callable $fn, callable $defaultFn): mixed

Applies a function to the value, or executes a closure if no value is present.

**Signature:**
```php
/**
 * @template U
 * @param callable(T): U $fn
 * @param callable(): U $defaultFn
 * @return U
 */
public function mapOrElse(callable $fn, callable $defaultFn): mixed
```

**Parameters:**
- `$fn`: Transformation function to apply to the value
- `$defaultFn`: Closure to execute when no value is present

**Return value:**
- Some state: Result of applying `$fn`
- None state: Result of executing `$defaultFn`

**Usage example:**
```php
$option = Some::of(5);
$result = $option->mapOrElse(
    fn($x) => $x * 2,
    fn() => time()
); // 10

$noneOption = None::instance();
$timestamp = $noneOption->mapOrElse(
    fn($x) => $x * 2,
    fn() => time()
); // Current timestamp
```

**Lazy evaluation benefit:**
```php
// Heavy processing only executed when no value is present
$result = $cache->get('expensive_data')
    ->mapOrElse(
        fn($data) => $data->process(),
        fn() => $this->calculateExpensiveDefault() // Not executed on Some
    );
```

**Dynamic default values:**
```php
$message = $user->getLastLogin()
    ->mapOrElse(
        fn($login) => "Last login: " . $login->format('Y-m-d'),
        fn() => "First time login (" . date('Y-m-d') . ")"
    );
```

**Related methods:** [mapOr()](#maporcallable-fn-mixed-default-mixed), [unwrapOrElse()](#unwraporelsecallable-fn-mixed), [orElse()](#orelsecallable-fn-option)

---

### andThen(callable $fn): Option

Applies a function that returns an Option to the value (monadic chaining).

**Signature:**
```php
/**
 * @template U
 * @param callable(T): Option<U> $fn
 * @return Option<U>
 */
public function andThen(callable $fn): Option
```

**Parameters:**
- `$fn`: Function that takes value and returns an Option

**Return value:**
- Some state: Option returned by `$fn`
- None state: None

**Usage example:**
```php
function parseNumber(string $str): Option {
    return is_numeric($str) ? Some::of((int)$str) : None::instance();
}

$option = Some::of("42")
    ->andThen(fn($str) => parseNumber($str))  // Some(42)
    ->andThen(fn($num) => $num > 0 ? Some::of($num) : None::instance()); // Some(42)

$invalidOption = Some::of("abc")
    ->andThen(fn($str) => parseNumber($str)); // None
```

**Complex chaining:**
```php
$result = $this->getUser($id)
    ->andThen(fn($user) => $this->loadProfile($user))
    ->andThen(fn($profile) => $this->checkPermissions($profile))
    ->andThen(fn($profile) => $this->formatUserData($profile));
```

**Relationship to flatMap:** `andThen` corresponds to `flatMap` in other languages

**Related methods:** [map()](#mapcallable-fn-option), [filter()](#filtercallable-predicate-option), [flatten()](#flatten-option)

## Value Extraction Methods

### unwrap(): mixed

Gets the value. Throws an exception if no value is present.

**Signature:**
```php
/**
 * @return T
 * @throws UnwrapException
 */
public function unwrap(): mixed
```

**Return value:**
- Some state: The value
- None state: Throws UnwrapException

**Usage example:**
```php
$option = Some::of(42);
$value = $option->unwrap(); // 42

$noneOption = None::instance();
try {
    $value = $noneOption->unwrap(); // UnwrapException
} catch (UnwrapException $e) {
    echo $e->getMessage(); // "None value"
}
```

**⚠️ Caution:**
- To avoid unexpected exceptions, it's recommended to check with `isSome()` first or use `unwrapOr()`
- Use carefully in production code

**Safe usage example:**
```php
if ($option->isSome()) {
    $value = $option->unwrap(); // Safe
}
```

**Related methods:** [unwrapOr()](#unwrapormixed-default-mixed), [unwrapOrElse()](#unwraporelsecallable-fn-mixed), [expect()](#expectstring-message-mixed)

---

### unwrapOr(mixed $default): mixed

Gets the value, or returns a default value if no value is present.

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
- `$default`: Default value when no value is present

**Return value:**
- Some state: The value
- None state: `$default`

**Usage example:**
```php
$option = Some::of(42);
$value = $option->unwrapOr(0); // 42

$noneOption = None::instance();
$value = $noneOption->unwrapOr(0); // 0
```

**Practical examples:**
```php
// Configuration value retrieval
$maxItems = $config->getMaxItems()->unwrapOr(10);

// User settings
$theme = $user->getPreference('theme')->unwrapOr('default');

// API response
$timeout = $response->getTimeout()->unwrapOr(30);
```

**Related methods:** [unwrap()](#unwrap-mixed), [unwrapOrElse()](#unwraporelsecallable-fn-mixed), [mapOr()](#maporcallable-fn-mixed-default-mixed)

---

### unwrapOrElse(callable $fn): mixed

Gets the value, or returns the result of a closure if no value is present.

**Signature:**
```php
/**
 * @template U
 * @param callable(): U $fn
 * @return T|U
 */
public function unwrapOrElse(callable $fn): mixed
```

**Parameters:**
- `$fn`: Closure to execute when no value is present

**Return value:**
- Some state: The value
- None state: Result of executing `$fn`

**Usage example:**
```php
$option = Some::of(42);
$value = $option->unwrapOrElse(fn() => time()); // 42

$noneOption = None::instance();
$value = $noneOption->unwrapOrElse(fn() => time()); // Current timestamp
```

**Lazy evaluation benefit:**
```php
// Heavy processing only executed when no value is present
$value = $cache->get('data')
    ->unwrapOrElse(fn() => $this->computeExpensiveValue());
```

**Dynamic default generation:**
```php
$sessionId = $session->getId()
    ->unwrapOrElse(fn() => $this->generateNewSessionId());
```

**Related methods:** [unwrapOr()](#unwrapormixed-default-mixed), [mapOrElse()](#maporelsecallable-fn-callable-defaultfn-mixed), [orElse()](#orelsecallable-fn-option)

---

### expect(string $message): mixed

Gets the value. Throws an exception with custom message if no value is present.

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
- `$message`: Exception message when no value is present

**Return value:**
- Some state: The value
- None state: Throws UnwrapException with custom message

**Usage example:**
```php
$option = Some::of(42);
$value = $option->expect("Value is required"); // 42

$noneOption = None::instance();
try {
    $value = $noneOption->expect("Configuration file not found");
} catch (UnwrapException $e) {
    echo $e->getMessage(); // "Configuration file not found"
}
```

**Usage in debugging:**
```php
// Clarify expected situations
$config = $this->loadConfig()
    ->expect("Configuration file loading required");

$user = $session->getAuthenticatedUser()
    ->expect("Authenticated user required");
```

**Related methods:** [unwrap()](#unwrap-mixed), [unwrapOr()](#unwrapormixed-default-mixed)

## Inspection Methods

### inspect(callable $fn): Option

Inspects the value and executes side effects (value remains unchanged).

**Signature:**
```php
/**
 * @param callable(T): void $fn
 * @return Option<T>
 */
public function inspect(callable $fn): Option
```

**Parameters:**
- `$fn`: Inspection function that receives the value (side effects only)

**Return value:** Original Option (unchanged)

**Usage example:**
```php
$option = Some::of(42)
    ->inspect(fn($x) => error_log("Value: $x"))
    ->map(fn($x) => $x * 2);
// Log output: "Value: 42"
// Result: Some(84)

$noneOption = None::instance()
    ->inspect(fn($x) => error_log("Value: $x")); // Not executed
```

**Usage in debugging:**
```php
$result = $this->processUser($userId)
    ->inspect(fn($user) => $this->logUserAccess($user))
    ->map(fn($user) => $user->getProfile())
    ->inspect(fn($profile) => $this->trackProfileView($profile));
```

**Conditional logging:**
```php
$debugMode = $_ENV['DEBUG'] ?? false;

$result = $data->getProcessedValue()
    ->inspect(function($value) use ($debugMode) {
        if ($debugMode) {
            error_log("Processed value: " . json_encode($value));
        }
    });
```

**Related methods:** [map()](#mapcallable-fn-option), [filter()](#filtercallable-predicate-option)

## Filtering Methods

### filter(callable $predicate): Option

Retains the value only if it satisfies the predicate function.

**Signature:**
```php
/**
 * @param callable(T): bool $predicate
 * @return Option<T>
 */
public function filter(callable $predicate): Option
```

**Parameters:**
- `$predicate`: Predicate function to evaluate the value

**Return value:**
- Some state and satisfies predicate: Original Some
- Some state and doesn't satisfy predicate, or None state: None

**Usage example:**
```php
$option = Some::of(10);
$positive = $option->filter(fn($x) => $x > 0); // Some(10)
$negative = $option->filter(fn($x) => $x < 0); // None

$noneOption = None::instance();
$filtered = $noneOption->filter(fn($x) => true); // None
```

**Practical examples:**
```php
// Filtering active users
$activeUser = $this->getUser($id)
    ->filter(fn($user) => $user->isActive())
    ->filter(fn($user) => !$user->isBanned());

// Number range check
$validAge = $input->getAge()
    ->filter(fn($age) => $age >= 0 && $age <= 150);

// Permission check
$authorizedUser = $session->getUser()
    ->filter(fn($user) => $user->hasPermission('admin'));
```

**Chain operations:**
```php
$result = $data->getValue()
    ->filter(fn($val) => is_string($val))
    ->filter(fn($val) => strlen($val) > 0)
    ->filter(fn($val) => !str_contains($val, 'invalid'));
```

**Related methods:** [isSomeAnd()](#issomeandcallable-predicate-bool), [map()](#mapcallable-fn-option), [andThen()](#andthencallable-fn-option)

## Combination Methods

### or(Option $opt): Option

Returns alternative Option if None (eager evaluation).

**Signature:**
```php
/**
 * @template U
 * @param Option<U> $opt
 * @return Option<T|U>
 */
public function or(Option $opt): Option
```

**Parameters:**
- `$opt`: Alternative Option

**Return value:**
- Some state: Original Some
- None state: `$opt`

**Usage example:**
```php
$primary = Some::of(42);
$fallback = Some::of(100);
$result = $primary->or($fallback); // Some(42)

$primaryNone = None::instance();
$fallbackSome = Some::of(100);
$result = $primaryNone->or($fallbackSome); // Some(100)
```

**Multiple fallbacks:**
```php
$result = $this->getFromCache($key)
    ->or($this->getFromDatabase($key))
    ->or($this->getFromBackup($key))
    ->or(Some::of($this->getDefault()));
```

**Configuration value priority:**
```php
$config = $userConfig
    ->or($projectConfig)
    ->or($globalConfig)
    ->or(Some::of($defaultConfig));
```

**Related methods:** [orElse()](#orelsecallable-fn-option), [and()](#andoption-opt-option), [unwrapOr()](#unwrapormixed-default-mixed)

---

### orElse(callable $fn): Option

Returns alternative Option if None (lazy evaluation).

**Signature:**
```php
/**
 * @template U
 * @param callable(): Option<U> $fn
 * @return Option<T|U>
 */
public function orElse(callable $fn): Option
```

**Parameters:**
- `$fn`: Closure that returns alternative Option

**Return value:**
- Some state: Original Some
- None state: Result of executing `$fn`

**Usage example:**
```php
$result = None::instance()
    ->orElse(fn() => Some::of("alternative value")); // Some("alternative value")

$result = Some::of(42)
    ->orElse(fn() => Some::of("alternative value")); // Some(42) - closure not executed
```

**Lazy evaluation benefit:**
```php
// Heavy processing only executed on None
$result = $cache->get('data')
    ->orElse(fn() => $this->computeExpensiveAlternative());
```

**Conditional fallback:**
```php
$result = $primary->orElse(function() {
    if ($this->isOnline()) {
        return $this->fetchFromRemote();
    }
    return $this->getLocalFallback();
});
```

**Related methods:** [or()](#oroption-opt-option), [unwrapOrElse()](#unwraporelsecallable-fn-mixed), [mapOrElse()](#maporelsecallable-fn-callable-defaultfn-mixed)

---

### and(Option $opt): Option

Returns another Option if Some, returns self if None (eager evaluation).

**Signature:**
```php
/**
 * @template U
 * @param Option<U> $opt
 * @return Option<U>
 */
public function and(Option $opt): Option
```

**Parameters:**
- `$opt`: Option to continue with

**Return value:**
- Some state: `$opt`
- None state: None

**Usage example:**
```php
$step1 = Some::of("Step 1 complete");
$step2 = Some::of("Step 2 complete");
$result = $step1->and($step2); // Some("Step 2 complete")

$step1None = None::instance();
$step2 = Some::of("Step 2 complete");
$result = $step1None->and($step2); // None
```

**Validation chain:**
```php
$result = $this->validateRequired($input)
    ->and($this->validateFormat($input))
    ->and($this->validateBusinessRules($input));
```

**Difference from andThen:** `and` continues without using the value, `andThen` transforms using the value

**Related methods:** [andThen()](#andthencallable-fn-option), [or()](#oroption-opt-option), [filter()](#filtercallable-predicate-option)

## Utility Methods

### contains(mixed $value): bool

Checks if Some value contains the specified value.

**Signature:**
```php
/**
 * @param mixed $value The value to check
 * @return bool true if Some value strictly equals the specified value, false otherwise
 */
public function contains(mixed $value): bool
```

**Parameters:**
- `$value`: Value to check

**Return value:**
- If Some state and value matches: `true`
- If Some state and value doesn't match or None state: `false`

**Usage example:**
```php
$option = Some::of(42);
var_dump($option->contains(42));   // true
var_dump($option->contains("42")); // false (strict comparison)
var_dump($option->contains(100));  // false

$noneOption = None::instance();
var_dump($noneOption->contains(42)); // false (because it's None)
```

**Importance of strict comparison:**
```php
$option = Some::of([1, 2, 3]);
var_dump($option->contains([1, 2, 3])); // true
var_dump($option->contains([1, 2]));    // false

$objectOption = Some::of(new stdClass());
var_dump($objectOption->contains(new stdClass())); // false (different instances)
```

**Practical examples:**
```php
// Permission check
$hasAdminRole = $user->getRoles()->contains('admin');

// Configuration value check
$isDebugMode = $config->getMode()->contains('debug');
```

**PHP-specific feature:** Not available in Rust's standard Option type, convenient method for PHP

**Related methods:** [isSomeAnd()](#issomeandcallable-predicate-bool), [filter()](#filtercallable-predicate-option)

---

### flatten(): Option

Flattens a nested Option by one level.

**Signature:**
```php
/**
 * @return Option<mixed>
 */
public function flatten(): Option
```

**Return value:**
- Some(Option): Inner Option
- Some(non-Option): Original Some
- None: None

**Usage example:**
```php
// Nested Option
$nested = Some::of(Some::of(42));
$flattened = $nested->flatten(); // Some(42)

// Non-Option value
$notNested = Some::of(42);
$unchanged = $notNested->flatten(); // Some(42)

// None case
$none = None::instance();
$stillNone = $none->flatten(); // None
```

**Complex nesting:**
```php
// Some(Some(None))
$deepNested = Some::of(Some::of(None::instance()));
$oneLevel = $deepNested->flatten(); // Some(None)
$fullyFlat = $oneLevel->flatten();  // None
```

**Practical example:**
```php
// Flattening results of functions that conditionally return Options
function findUserMaybe(int $id): Option {
    return $id > 0 ? Some::of($this->findUser($id)) : None::instance();
}

$user = Some::of(42)
    ->map(fn($id) => $this->findUserMaybe($id))
    ->flatten(); // Some(User) or None
```

**Related methods:** [andThen()](#andthencallable-fn-option), [map()](#mapcallable-fn-option)

## Type Conversion Methods

### transpose(): Result

Converts Option<Result<T, E>> → Result<Option<T>, E>.

**Signature:**
```php
/**
 * @return Result<mixed, mixed>
 */
public function transpose(): Result
```

**Conversion rules:**
- `Some(Ok(value))` → `Ok(Some(value))`
- `Some(Err(error))` → `Err(error)`
- `None` → `Ok(None)`
- `Some(non-Result)` → `Ok(Some(value))`

**Usage example:**
```php
// Some(Ok(value)) → Ok(Some(value))
$option = Some::of(Ok::of(42));
$result = $option->transpose(); // Ok(Some(42))

// Some(Err(error)) → Err(error)
$option = Some::of(Err::of("error"));
$result = $option->transpose(); // Err("error")

// None → Ok(None)
$option = None::instance();
$result = $option->transpose(); // Ok(None)
```

**Practical usage example:**
```php
// Batch processing result aggregation
$items = [1, 2, 3];
$results = array_map(fn($id) => 
    $this->findItem($id)  // Option<Item>
        ->map(fn($item) => $this->validateItem($item)), // Option<Result<Item, Error>>
    $items
);

$transposed = array_map(fn($opt) => $opt->transpose(), $results);
// [Ok(Some(item1)), Err("validation_error"), Ok(Some(item3))]
```

**Rust compatibility:** Same conversion rules as Rust's standard library

**Related methods:** [okOr()](#okormixed-err-result), [okOrElse()](#okorelsecallable-fn-result), [Result::transpose()](result_api_reference.md#transpose-option)

---

### okOr(mixed $err): Result

Converts Option to Result (None becomes Err with specified error).

**Signature:**
```php
/**
 * @param mixed $err
 * @return Result<mixed, mixed>
 */
public function okOr(mixed $err): Result
```

**Parameters:**
- `$err`: Error value for None case

**Return value:**
- Some state: `Ok(value)`
- None state: `Err($err)`

**Usage example:**
```php
$option = Some::of(42);
$result = $option->okOr("error"); // Ok(42)

$noneOption = None::instance();
$result = $noneOption->okOr("value not found"); // Err("value not found")
```

**Practical examples:**
```php
// Required field validation
$name = $input->getName()
    ->okOr("Name is required");

// Configuration value retrieval
$config = $this->loadConfig()
    ->okOr("Configuration file not found");

// User authentication
$user = $session->getUser()
    ->okOr("Authentication required");
```

**Structured error information:**
```php
$result = $data->getValue()
    ->okOr([
        'type' => 'missing_value',
        'message' => 'Required value not found',
        'timestamp' => time()
    ]);
```

**Related methods:** [okOrElse()](#okorelsecallable-fn-result), [transpose()](#transpose-result), [unwrapOr()](#unwrapormixed-default-mixed)

---

### okOrElse(callable $fn): Result

Converts Option to Result (None becomes Err with closure result).

**Signature:**
```php
/**
 * @param callable $fn
 * @return Result<mixed, mixed>
 */
public function okOrElse(callable $fn): Result
```

**Parameters:**
- `$fn`: Closure that generates error value for None case

**Return value:**
- Some state: `Ok(value)`
- None state: `Err($fn())`

**Usage example:**
```php
$option = Some::of(42);
$result = $option->okOrElse(fn() => "dynamic error"); // Ok(42)

$noneOption = None::instance();
$result = $noneOption->okOrElse(fn() => "generated error"); // Err("generated error")
```

**Dynamic error generation:**
```php
$result = $cache->get($key)
    ->okOrElse(fn() => "Cache miss for key: {$key} at " . date('Y-m-d H:i:s'));

$user = $session->getUser()
    ->okOrElse(fn() => [
        'error' => 'authentication_required',
        'redirect_url' => '/login',
        'timestamp' => time()
    ]);
```

**Lazy evaluation benefit:**
```php
// Heavy processing only executed when error is actually needed
$result = $primaryData
    ->okOrElse(fn() => $this->generateDetailedErrorReport());
```

**Related methods:** [okOr()](#okormixed-err-result), [unwrapOrElse()](#unwraporelsecallable-fn-mixed), [orElse()](#orelsecallable-fn-option)

## Advanced Operation Methods

### xor(Option $opt): Option

Exclusive OR operation: Some if only one is Some, None if both Some/both None.

**Signature:**
```php
/**
 * @template U
 * @param Option<U> $opt
 * @return Option<T|U>
 */
public function xor(Option $opt): Option
```

**Parameters:**
- `$opt`: Option to compare with

**Return value:**
- Only one is Some: Some with that value
- Both Some or both None: None

**Usage example:**
```php
$a = Some::of(1);
$b = Some::of(2);
$result = $a->xor($b); // None (both Some)

$a = Some::of(1);
$b = None::instance();
$result = $a->xor($b); // Some(1) (only one Some)

$a = None::instance();
$b = Some::of(2);
$result = $a->xor($b); // Some(2) (only one Some)

$a = None::instance();
$b = None::instance();
$result = $a->xor($b); // None (both None)
```

**Practical examples:**
```php
// Configuration exclusivity control
$useCache = $config->getCacheEnabled();
$useDatabase = $config->getDatabaseEnabled();
$storage = $useCache->xor($useDatabase); // Get value when only one is enabled

// User selection exclusivity
$emailNotify = $user->getEmailNotification();
$smsNotify = $user->getSmsNotification();
$notification = $emailNotify->xor($smsNotify); // When only one is selected
```

**Logical exclusivity check:**
```php
// Process only when exactly one of two conditions is true
$developmentMode = $env->isDevelopment();
$testingMode = $env->isTesting();
$singleMode = $developmentMode->xor($testingMode);

if ($singleMode->isSome()) {
    echo "Running in single mode";
}
```

**Related methods:** [or()](#oroption-opt-option), [and()](#andoption-opt-option), [zip()](#zipoption-opt-option)

---

### zip(Option $opt): Option

Combines two Options: tuple if both Some, None if either is None.

**Signature:**
```php
/**
 * @template U
 * @param Option<U> $opt
 * @return Option<array{T, U}>
 */
public function zip(Option $opt): Option
```

**Parameters:**
- `$opt`: Option to combine with

**Return value:**
- Both Some: Some with tuple (array)
- Either is None: None

**Usage example:**
```php
$a = Some::of(1);
$b = Some::of("hello");
$result = $a->zip($b); // Some([1, "hello"])

$a = Some::of(1);
$b = None::instance();
$result = $a->zip($b); // None

$a = None::instance();
$b = Some::of("hello");
$result = $a->zip($b); // None
```

**Practical examples:**
```php
// User information combination
$firstName = $user->getFirstName();
$lastName = $user->getLastName();
$fullName = $firstName->zip($lastName)
    ->map(fn($names) => $names[0] . ' ' . $names[1]);

// Coordinate combination
$x = $input->getX();
$y = $input->getY();
$coordinate = $x->zip($y)
    ->map(fn($coords) => ['x' => $coords[0], 'y' => $coords[1]]);

// Validation result combination
$email = $this->validateEmail($input);
$password = $this->validatePassword($input);
$credentials = $email->zip($password)
    ->map(fn($creds) => ['email' => $creds[0], 'password' => $creds[1]]);
```

**Multiple value combination:**
```php
// zip3 implementation example
function zip3(Option $a, Option $b, Option $c): Option {
    return $a->zip($b)
        ->andThen(fn($ab) => $c->map(fn($c_val) => [...$ab, $c_val]));
}

$result = zip3(Some::of(1), Some::of(2), Some::of(3)); // Some([1, 2, 3])
```

**Array destructuring:**
```php
$zipped = $option1->zip($option2);
if ($zipped->isSome()) {
    [$first, $second] = $zipped->unwrap();
    // Use $first, $second
}
```

**Related methods:** [and()](#andoption-opt-option), [xor()](#xoroption-opt-option), [map()](#mapcallable-fn-option)

## Implementation Classes

### Some<T>

Class representing an Option with a value.

```php
final class Some implements Option
{
    public function __construct(private readonly mixed $value) {}
    public static function of(mixed $value): self {}
}
```

**Usage example:**
```php
$some = Some::of(42);
$some = Some::of("string");
$some = Some::of(['key' => 'value']);
$some = Some::of(new User());
```

### None

Class representing an Option without a value (singleton).

```php
final class None implements Option
{
    private static ?self $instance = null;
    
    private function __construct() {}
    
    public static function instance(): self {}
}
```

**Usage example:**
```php
$none = None::instance(); // Always the same instance
```

**Singleton pattern benefits:**
- Memory efficiency
- Fast comparison operations
- Consistency guarantee

## Usage Considerations

### Performance Considerations
- Overhead from object wrapping
- Optimization with None's singleton pattern
- See [Performance Guide](../guide/performance_guide.md) for details

### Type Safety
- PHPStan Level MAX compatible
- Generic type annotations recommended
- See [Type Error Troubleshooting](../guide/debugging_guide.md#type-error-troubleshooting) for details

### Null Safety
- Use as alternative to null values
- Use `unwrap()` methods carefully
- See [Best Practices](../guide/best_practices.md) for details

### Practical Design Patterns
- Optional fields in Builder pattern
- Search results in Repository pattern
- Configuration values in Configuration pattern