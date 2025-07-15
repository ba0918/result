# Debugging and Troubleshooting Guide

This guide explains common issues and solutions when using the PHP Result/Option type library.

## Table of Contents

1. [Common Issues and Solutions](#common-issues-and-solutions)
2. [Debugging Techniques](#debugging-techniques)
3. [Utilizing the inspect Method](#utilizing-the-inspect-method)
4. [Reading Error Messages](#reading-error-messages)
5. [IDE Integration Support Features](#ide-integration-support-features)
6. [Performance Debugging](#performance-debugging)
7. [Type Error Troubleshooting](#type-error-troubleshooting)

## Common Issues and Solutions

### 1. UnwrapException Occurs

#### Problem
```php
$option = None::instance();
$value = $option->unwrap(); // UnwrapException: None value
```

#### Solutions
```php
// ❌ Dangerous: Direct use of unwrap()
$value = $option->unwrap();

// ✅ Safe: Pre-check
if ($option->isSome()) {
    $value = $option->unwrap();
}

// ✅ Safe: With default value
$value = $option->unwrapOr('default value');

// ✅ Safe: Conditional default
$value = $option->unwrapOrElse(fn() => $this->generateDefault());

// ✅ Safe: Pattern matching
$result = $option->map(fn($x) => $x->process())->unwrapOr(null);
```

### 2. Calling Ok-only Methods on Err Values

#### Problem
```php
$result = Err::of('error');
$value = $result->unwrap(); // UnwrapException
```

#### Solutions
```php
// ✅ Check state before processing
if ($result->isOk()) {
    $value = $result->unwrap();
} else {
    $error = $result->unwrapErr();
    $this->handleError($error);
}

// ✅ Type-safe pattern matching
$finalValue = $result
    ->map(fn($value) => $value->process())
    ->mapErr(fn($error) => $this->logError($error))
    ->unwrapOr('default value');
```

### 3. Type Inference Issues

#### Problem
```php
// PHPStan type error
$option = Some::of(42);
$doubled = $option->map(fn($x) => $x * 2); // mixed type error
```

#### Solutions
```php
// ✅ Add type annotations
/** @var Option<int> $option */
$option = Some::of(42);
$doubled = $option->map(fn(int $x): int => $x * 2);

// ✅ Explicit typing with factory methods
/** @return Option<User> */
public function findUser(int $id): Option
{
    $user = $this->repository->find($id);
    return $user ? Some::of($user) : None::instance();
}
```

### 4. Handling Nested Types

#### Problem
```php
// Option<Option<T>> processing is complex
$nested = Some::of(Some::of(42));
$value = $nested->unwrap()->unwrap(); // Dangerous
```

#### Solutions
```php
// ✅ Use flatten()
$flattened = $nested->flatten();
$value = $flattened->unwrapOr(0);

// ✅ Chain with andThen()
$result = $nested->andThen(fn($inner) => $inner);

// ✅ Review design
// Design to avoid nesting in the first place
public function findAndProcess(int $id): Option
{
    return $this->findUser($id)
        ->andThen(fn($user) => $this->processUser($user));
}
```

## Debugging Techniques

### 1. Debugging with inspect() Method

```php
// Track processing flow
$result = $this->getData()
    ->inspect(fn($data) => error_log("Data received: " . print_r($data, true)))
    ->map(fn($data) => $this->validate($data))
    ->inspect(fn($validated) => error_log("Validation result: " . var_export($validated, true)))
    ->andThen(fn($validated) => $this->process($validated))
    ->inspect(fn($processed) => error_log("Processing complete: " . json_encode($processed)));

// Detailed logging on errors
$result = $this->riskyOperation()
    ->inspectErr(fn($error) => error_log("Error occurred: " . $error))
    ->inspectErr(fn($error) => $this->notifyError($error));
```

### 2. Custom Debug Helpers

```php
class ResultDebugger
{
    public static function trace(Result $result, string $label = ''): Result
    {
        $prefix = $label ? "[$label] " : '';
        
        if ($result->isOk()) {
            error_log($prefix . "OK: " . print_r($result->unwrap(), true));
        } else {
            error_log($prefix . "ERR: " . print_r($result->unwrapErr(), true));
        }
        
        return $result;
    }
    
    public static function dump(Option $option, string $label = ''): Option
    {
        $prefix = $label ? "[$label] " : '';
        
        if ($option->isSome()) {
            error_log($prefix . "Some: " . print_r($option->unwrap(), true));
        } else {
            error_log($prefix . "None");
        }
        
        return $option;
    }
}

// Usage example
$result = ResultDebugger::trace(
    $this->complexOperation(),
    'Complex Operation'
);
```

### 3. Step-by-Step Debugging

```php
public function debugComplexChain(array $input): Result
{
    // Step 1: Input verification
    error_log("Input: " . json_encode($input));
    
    $step1 = Option::of($input)
        ->filter(fn($data) => !empty($data));
    
    if ($step1->isNone()) {
        error_log("Step 1 failed: empty input");
        return Err::of('Empty input');
    }
    
    // Step 2: Validation
    $step2 = $step1->andThen(fn($data) => $this->validate($data));
    
    if ($step2->isNone()) {
        error_log("Step 2 failed: validation error");
        return Err::of('Validation failed');
    }
    
    // Step 3: Processing
    return $step2
        ->map(fn($data) => $this->process($data))
        ->okOr('Processing failed');
}
```

## Utilizing the inspect Method

### 1. Basic Usage

```php
// Tracking values with Result type
$result = $this->processData($input)
    ->inspect(fn($data) => $this->logSuccess('Data processed', $data))
    ->inspectErr(fn($error) => $this->logError('Processing failed', $error));

// Tracking values with Option type
$option = $this->findUser($id)
    ->inspect(fn($user) => $this->logActivity('User found', $user->getId()));
```

### 2. Conditional Debugging

```php
class ConditionalDebugger
{
    public static function inspectIf(bool $condition): callable
    {
        return function($value) use ($condition) {
            if ($condition) {
                error_log("Debug: " . print_r($value, true));
            }
        };
    }
}

// Usage example
$debug = $_ENV['APP_DEBUG'] ?? false;

$result = $this->complexOperation()
    ->inspect(ConditionalDebugger::inspectIf($debug));
```

### 3. Performance Monitoring

```php
class PerformanceInspector
{
    private static array $timers = [];
    
    public static function startTimer(string $name): callable
    {
        return function($value) use ($name) {
            self::$timers[$name] = microtime(true);
            return $value;
        };
    }
    
    public static function endTimer(string $name): callable
    {
        return function($value) use ($name) {
            if (isset(self::$timers[$name])) {
                $elapsed = microtime(true) - self::$timers[$name];
                error_log("Timer [$name]: " . round($elapsed * 1000, 2) . "ms");
                unset(self::$timers[$name]);
            }
            return $value;
        };
    }
}

// Usage example
$result = $this->heavyOperation()
    ->inspect(PerformanceInspector::startTimer('heavy_op'))
    ->map(fn($data) => $this->processHeavyData($data))
    ->inspect(PerformanceInspector::endTimer('heavy_op'));
```

## Reading Error Messages

### 1. UnwrapException Details

```php
// Exception message: "Called unwrap() on an Err value: error_details"
try {
    $value = $result->unwrap();
} catch (UnwrapException $e) {
    // Error content can be extracted from message
    $message = $e->getMessage();
    
    if (str_contains($message, 'Called unwrap() on an Err value:')) {
        $errorValue = substr($message, strlen('Called unwrap() on an Err value: '));
        error_log("Error value was: " . $errorValue);
    }
}
```

### 2. Decoding Type Errors

```php
// PHPStan error: "Parameter #1 $fn of method map() expects callable(T): U, callable(mixed): int given"

// Solution: Specify types explicitly
/** @var Option<string> $option */
$option = Some::of("hello");

$result = $option->map(fn(string $str): int => strlen($str));
```

### 3. Custom Error Information

```php
// Include more detailed error information
public function processWithContext(array $data): Result
{
    try {
        $result = $this->validate($data);
        return Ok::of($result);
    } catch (ValidationException $e) {
        return Err::of([
            'type' => 'validation_error',
            'message' => $e->getMessage(),
            'field' => $e->getField(),
            'input' => $data,
            'timestamp' => time()
        ]);
    }
}
```

## IDE Integration Support Features

### 1. PhpStorm Configuration

```php
// .phpstorm.meta.php
<?php
namespace PHPSTORM_META {
    
    // Improve Option type inference
    override(\Mizumi\Result\Option::map(0), map([
        '' => '@',
    ]));
    
    // Improve Result type inference
    override(\Mizumi\Result\Result::map(0), map([
        '' => '@',
    ]));
    
    // Return type inference for unwrap()
    override(\Mizumi\Result\Some::unwrap(), type(0));
    override(\Mizumi\Result\Ok::unwrap(), type(0));
}
```

### 2. VS Code Configuration

```json
// settings.json
{
    "php.suggest.basic": false,
    "php.validate.enable": true,
    "phpstan.enabled": true,
    "phpstan.level": "max",
    "intelephense.completion.insertUseDeclaration": true,
    "intelephense.completion.fullyQualifyGlobalConstantsAndFunctions": true
}
```

### 3. Custom Live Templates

```php
// PhpStorm Live Template: "optmap"
$SELECTION$->map(fn($VAR$) => $END$)

// PhpStorm Live Template: "reschain"
$SELECTION$
    ->map(fn($VAR$) => $END$)
    ->mapErr(fn($error) => $this->handleError($error))
    ->unwrapOr($DEFAULT$)
```

## Performance Debugging

### 1. Memory Usage Monitoring

```php
class MemoryProfiler
{
    public static function profile(callable $operation, string $label): mixed
    {
        $memoryBefore = memory_get_usage(true);
        $peakBefore = memory_get_peak_usage(true);
        
        $result = $operation();
        
        $memoryAfter = memory_get_usage(true);
        $peakAfter = memory_get_peak_usage(true);
        
        error_log(sprintf(
            "%s - Memory: %s bytes, Peak: %s bytes",
            $label,
            number_format($memoryAfter - $memoryBefore),
            number_format($peakAfter - $peakBefore)
        ));
        
        return $result;
    }
}

// Usage example
$result = MemoryProfiler::profile(
    fn() => $this->processLargeDataset($data),
    'Large Dataset Processing'
);
```

### 2. Object Creation Tracking

```php
// Debug factory methods
class DebugOption
{
    private static int $instanceCount = 0;
    
    public static function of(mixed $value): Option
    {
        self::$instanceCount++;
        error_log("Option instance #" . self::$instanceCount . " created");
        
        return $value === null ? None::instance() : Some::of($value);
    }
    
    public static function getInstanceCount(): int
    {
        return self::$instanceCount;
    }
}
```

## Type Error Troubleshooting

### 1. PHPStan Level MAX Support

```php
// Problem: mixed type warning
public function processData(mixed $data): Result
{
    return Ok::of($data)
        ->map(fn($x) => $x->process()); // PHPStan error: mixed type
}

// Solution 1: Type assertion
public function processData(mixed $data): Result
{
    assert($data instanceof ProcessableData);
    
    return Ok::of($data)
        ->map(fn(ProcessableData $x) => $x->process());
}

// Solution 2: Runtime type checking
public function processData(mixed $data): Result
{
    if (!$data instanceof ProcessableData) {
        return Err::of('Invalid data type');
    }
    
    return Ok::of($data)
        ->map(fn(ProcessableData $x) => $x->process());
}
```

### 2. Generics Type Issues

```php
// Problem: Generic types not inferred
class Repository
{
    /**
     * @template T
     * @param class-string<T> $class
     * @return Option<T>
     */
    public function find(string $class, int $id): Option
    {
        $entity = $this->database->find($class, $id);
        return $entity ? Some::of($entity) : None::instance();
    }
}

// Specify type when using
/** @var Option<User> $user */
$user = $repository->find(User::class, 123);
```

### 3. Practical Error Handling

```php
class RobustProcessor
{
    public function process(mixed $input): Result
    {
        try {
            // Type-safe processing chain
            return $this->validateInput($input)
                ->andThen(fn($data) => $this->transformData($data))
                ->andThen(fn($transformed) => $this->saveData($transformed));
                
        } catch (Throwable $e) {
            return Err::of([
                'error' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    private function validateInput(mixed $input): Result
    {
        if (!is_array($input)) {
            return Err::of('Input must be array');
        }
        
        if (empty($input)) {
            return Err::of('Input cannot be empty');
        }
        
        return Ok::of($input);
    }
}
```

## Summary

Principles for effective debugging:

1. **Preventive debugging**: Use unwrapOr() instead of unwrap()
2. **Step-by-step tracking**: Visualize processing flow with inspect() method
3. **Type safety**: Utilize PHPStan and IDE for type checking
4. **Error context**: Error messages with sufficient information
5. **Performance monitoring**: Track execution time and memory usage

By utilizing these techniques, you can significantly improve the quality and maintainability of code using Result/Option types.