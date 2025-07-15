# Performance Optimization Guide

This guide provides a detailed explanation of the performance characteristics and optimization techniques for the PHP Result/Option type library.

## Table of Contents

1. [Performance Characteristics Overview](#performance-characteristics-overview)
2. [Benchmark Results](#benchmark-results)
3. [Optimization Techniques](#optimization-techniques)
4. [Anti-patterns to Avoid](#anti-patterns-to-avoid)
5. [Usage Patterns for Performance-Critical Scenarios](#usage-patterns-for-performance-critical-scenarios)
6. [Practical Best Practices](#practical-best-practices)

## Performance Characteristics Overview

### Efficiency through Object Design

This library is designed with high performance in mind:

```php
// All classes are final and immutable
final class Ok implements Result
{
    public function __construct(private readonly mixed $value) {}
}

// None uses singleton pattern for memory efficiency
final class None implements Option
{
    private static ?self $instance = null;
    
    public static function instance(): self
    {
        return self::$instance ??= new self();
    }
}
```

### Key Performance Factors

#### 1. Object Creation Cost
- **Lightweight design**: Each object holds only a single property
- **Immutable**: No copying cost due to value changes
- **Singleton**: Reuse of None instances

#### 2. Memory Usage
- **Wrapping overhead**: Approximately 16-24 bytes/object (PHP 8.4)
- **Reference efficiency**: Optimization through readonly properties
- **Garbage collection**: Compatibility with automatic memory management

#### 3. Method Call Cost
- **Inlining**: Target for JIT compiler optimization
- **Type inference**: Optimization through opcache
- **Shorthand**: One-shot conversion methods like `mapOr`

## Benchmark Results

### Basic Operation Performance

The following are comparison results from 100,000 iterations:

```php
// Test environment: PHP 8.4, opcache enabled, JIT enabled

// 1. Value wrapping and unwrapping
$start = microtime(true);
for ($i = 0; $i < 100000; $i++) {
    $result = Ok::of($i);
    $value = $result->unwrap();
}
$elapsed = microtime(true) - $start;
// Result: ~15ms (vs native PHP: ~2ms)

// 2. Conditional branching
$start = microtime(true);
for ($i = 0; $i < 100000; $i++) {
    $result = $i % 2 === 0 ? Ok::of($i) : Err::of("error");
    if ($result->isOk()) {
        $value = $result->unwrap();
    }
}
$elapsed = microtime(true) - $start;
// Result: ~25ms (vs native PHP if: ~8ms)

// 3. map operation
$start = microtime(true);
for ($i = 0; $i < 100000; $i++) {
    $result = Ok::of($i)->map(fn($x) => $x * 2);
}
$elapsed = microtime(true) - $start;
// Result: ~35ms (vs native PHP: ~12ms)
```

### Memory Usage Comparison

```php
// Memory usage measurement
$beforeMemory = memory_get_usage();

// Native PHP array
$nativeArray = [];
for ($i = 0; $i < 10000; $i++) {
    $nativeArray[] = $i;
}
$nativeMemory = memory_get_usage() - $beforeMemory;
// ~400KB

// Storage with Option type
$beforeMemory = memory_get_usage();
$optionArray = [];
for ($i = 0; $i < 10000; $i++) {
    $optionArray[] = Some::of($i);
}
$optionMemory = memory_get_usage() - $beforeMemory;
// ~800KB (approximately 2x overhead)
```

## Optimization Techniques

### 1. Utilizing Shorthand Methods

Performance improves by executing multiple operations with a single method:

```php
// ❌ Poor performance
$result = $option->map(fn($x) => $x * 2)->unwrapOr(0);

// ✅ Optimized pattern
$result = $option->mapOr(fn($x) => $x * 2, 0);
```

### 2. Utilizing Early Returns

```php
// ❌ Avoid unnecessary chains
public function processData(?array $data): Result
{
    return Option::of($data)
        ->map(fn($d) => $this->validate($d))
        ->map(fn($d) => $this->transform($d))
        ->map(fn($d) => $this->save($d))
        ->okOr('Processing error');
}

// ✅ Performance improvement with early returns
public function processData(?array $data): Result
{
    if ($data === null) {
        return Err::of('No data');
    }
    
    $validated = $this->validate($data);
    if (!$validated) {
        return Err::of('Validation error');
    }
    
    // Execute only when necessary...
}
```

### 3. Efficient Use of Option Type

```php
// ❌ Frequent None generation
public function findItems(array $conditions): array
{
    $results = [];
    foreach ($conditions as $condition) {
        $item = $this->findOne($condition); // Returns Option<Item>
        if ($item->isSome()) {
            $results[] = $item->unwrap();
        }
    }
    return $results;
}

// ✅ Efficient pattern
public function findItems(array $conditions): array
{
    $results = [];
    foreach ($conditions as $condition) {
        $item = $this->findOneNative($condition); // Returns null | Item
        if ($item !== null) {
            $results[] = $item;
        }
    }
    return $results;
}
```

### 4. Batch Processing Optimization

```php
// ❌ Individual processing
public function processItems(array $items): array
{
    return array_map(function ($item) {
        return Option::of($item)
            ->filter(fn($i) => $i->isValid())
            ->map(fn($i) => $i->transform())
            ->unwrapOr(null);
    }, $items);
}

// ✅ Batch optimization
public function processItems(array $items): array
{
    $validItems = array_filter($items, fn($item) => $item->isValid());
    return array_map(fn($item) => $item->transform(), $validItems);
}
```

## Anti-patterns to Avoid

### 1. Excessive Nesting

```php
// ❌ Excessive nesting - performance degradation
$result = $option
    ->map(fn($x) => Option::of($x->getValue()))
    ->flatten()
    ->map(fn($x) => Result::ok($x))
    ->transpose()
    ->map(fn($x) => $x->process());

// ✅ Simple processing
$value = $option->unwrapOr(null);
if ($value !== null && $value->getValue() !== null) {
    return Ok::of($value->getValue()->process());
}
return Err::of('Processing failed');
```

### 2. Unnecessary Object Creation

```php
// ❌ New instance every time
public function getDefault(): Option
{
    return None::instance(); // ✅ Use singleton
    // return new None();    // ❌ Create new every time
}

// ❌ Unnecessary wrapping
public function calculate(int $x): int
{
    return Ok::of($x)
        ->map(fn($n) => $n * 2)
        ->unwrap(); // ✅ Direct $x * 2
}
```

### 3. Heavy Processing with Closures

```php
// ❌ Execute heavy processing every time
$result = $option->mapOrElse(
    fn($x) => $x->process(),
    fn() => $this->heavyDefaultCalculation() // Executed every time
);

// ✅ Pre-calculation or lazy evaluation
$default = $this->heavyDefaultCalculation();
$result = $option->mapOr(fn($x) => $x->process(), $default);
```

## Usage Patterns for Performance-Critical Scenarios

### High-Frequency Processing (1000+ times per second)

```php
// Avoid Result/Option types, use native PHP
public function hotPath(array $data): ?array
{
    if (empty($data)) {
        return null;
    }
    
    $result = [];
    foreach ($data as $item) {
        if ($item['valid'] ?? false) {
            $result[] = $item['value'] * 2;
        }
    }
    
    return empty($result) ? null : $result;
}
```

### Medium-Frequency Processing (100-1000 times per second)

```php
// Utilize shorthand methods
public function mediumPath(array $data): Result
{
    return Option::of($data)
        ->filter(fn($d) => !empty($d))
        ->mapOr(fn($d) => $this->processArray($d), Err::of('Empty data'));
}
```

### Low-Frequency Processing (Less than 100 times per second)

```php
// Full utilization of Result/Option types
public function complexBusinessLogic(UserInput $input): Result
{
    return $input->validate()
        ->andThen(fn($data) => $this->authorize($data))
        ->andThen(fn($data) => $this->process($data))
        ->andThen(fn($result) => $this->save($result))
        ->map(fn($saved) => $saved->toArray());
}
```

## Practical Best Practices

### 1. Implement Profiling

```php
// Helper for performance measurement
class PerformanceProfiler
{
    public static function measure(callable $fn, string $label): mixed
    {
        $start = hrtime(true);
        $memory = memory_get_usage();
        
        $result = $fn();
        
        $elapsed = (hrtime(true) - $start) / 1_000_000; // ms
        $memoryUsed = memory_get_usage() - $memory;
        
        echo "{$label}: {$elapsed}ms, {$memoryUsed} bytes\n";
        
        return $result;
    }
}

// Usage example
$result = PerformanceProfiler::measure(
    fn() => $this->processWithResultType($data),
    'Processing with Result type'
);
```

### 2. Gradual Introduction

```php
// Phase 1: Introduce from critical error handling parts
public function criticalDatabaseOperation(): Result
{
    try {
        $data = $this->database->fetch();
        return Ok::of($data);
    } catch (Exception $e) {
        return Err::of($e->getMessage());
    }
}

// Phase 2: Expand to business logic
public function businessLogic(): Result
{
    return $this->criticalDatabaseOperation()
        ->andThen(fn($data) => $this->validateBusinessRules($data))
        ->map(fn($data) => $this->transformForOutput($data));
}

// Phase 3: Full-stack application
public function apiEndpoint(): JsonResponse
{
    return $this->businessLogic()
        ->map(fn($data) => response()->json($data))
        ->unwrapOr(response()->json(['error' => 'Internal error'], 500));
}
```

### 3. Performance Monitoring

```php
// Performance monitoring within applications
class ResultPerformanceMonitor
{
    private static array $metrics = [];
    
    public static function trackOperation(string $operation, callable $fn): mixed
    {
        $start = microtime(true);
        
        try {
            $result = $fn();
            $success = $result instanceof Result ? $result->isOk() : true;
        } catch (Exception $e) {
            $success = false;
            throw $e;
        } finally {
            $elapsed = microtime(true) - $start;
            self::$metrics[$operation][] = [
                'elapsed' => $elapsed,
                'success' => $success ?? false,
                'timestamp' => time()
            ];
        }
        
        return $result;
    }
    
    public static function getMetrics(): array
    {
        return self::$metrics;
    }
}
```

## Summary

This library provides practical performance while significantly improving type safety and expressiveness.

### Performance Decision Guidelines

- **Microservices/APIs**: Recommended - significant error handling benefits
- **Batch processing**: Partially recommended - optimize critical paths
- **Real-time processing**: Consider carefully - profiling essential
- **Libraries/Frameworks**: Recommended - improved developer experience

By balancing performance and developer experience while introducing gradually, you can achieve optimal results.