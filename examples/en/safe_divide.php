<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use ba0918\Result\Err;
use ba0918\Result\None;
use ba0918\Result\Ok;
use ba0918\Result\Option;
use ba0918\Result\Result;
use ba0918\Result\Some;

/**
 * Basic safe division
 *
 * @param float $a Dividend
 * @param float $b Divisor
 *
 * @return Result<float, string> Float on success, error message on failure
 */
function safe_divide(float $a, float $b): Result
{
    if ($b === 0.0) {
        return Err::of('Cannot divide by zero');
    }

    return Ok::of($a / $b);
}

/**
 * Division with detailed error information
 *
 * @param float $a Dividend
 * @param float $b Divisor
 *
 * @return Result<float, array> Float on success, detailed error info on failure
 */
function safe_divide_detailed(float $a, float $b): Result
{
    if ($b === 0.0) {
        return Err::of([
            'error' => 'division_by_zero',
            'message' => 'Cannot divide by zero',
            'dividend' => $a,
            'divisor' => $b,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    if (!is_finite($a) || !is_finite($b)) {
        return Err::of([
            'error' => 'invalid_number',
            'message' => 'Cannot divide infinite or NaN values',
            'dividend' => $a,
            'divisor' => $b,
        ]);
    }

    return Ok::of($a / $b);
}

/**
 * Safe division from string input (including type conversion)
 *
 * @param string $a Dividend (string)
 * @param string $b Divisor (string)
 *
 * @return Result<float, string> Parse and calculation result
 */
function safe_divide_from_string(string $a, string $b): Result
{
    return parse_number($a)
        ->okOr("Invalid dividend: '$a'")
        ->andThen(function ($dividend) use ($b) {
            return parse_number($b)
                ->okOr("Invalid divisor: '$b'")
                ->andThen(fn ($divisor) => safe_divide($dividend, $divisor));
        });
}

/**
 * Parse numeric string and return as Option
 *
 * @param string $str Numeric string
 *
 * @return Option<float> Number on success, None on failure
 */
function parse_number(string $str): Option
{
    $trimmed = trim($str);
    if (!is_numeric($trimmed)) {
        return None::instance();
    }

    return Some::of((float) $trimmed);
}

/**
 * Execute multiple divisions in sequence (stops at first error)
 *
 * @param float $initial Initial value
 * @param array<float> $divisors Array of divisors
 *
 * @return Result<float, string> Final result or error
 */
function chain_divide(float $initial, array $divisors): Result
{
    return array_reduce(
        $divisors,
        fn (Result $acc, float $divisor) => $acc->andThen(fn ($value) => safe_divide($value, $divisor)),
        Ok::of($initial),
    );
}

/**
 * Format division result as string
 *
 * @param Result $result Division result
 * @param int $precision Decimal places
 *
 * @return string Formatted result
 */
function format_division_result(Result $result, int $precision = 2): string
{
    return $result
        ->map(fn ($value) => number_format($value, $precision))
        ->map(fn ($formatted) => "Result: $formatted")
        ->unwrapOr('Error: ' . $result->unwrapErr());
}

/**
 * Statistical calculation: Safe average calculation
 *
 * @param array<float> $numbers Array of numbers
 *
 * @return Result<float, string> Average value or error
 */
function safe_average(array $numbers): Result
{
    if (empty($numbers)) {
        return Err::of('Cannot calculate average of empty array');
    }

    $sum = array_sum($numbers);
    $count = count($numbers);

    return safe_divide($sum, $count);
}

/**
 * Practical example: Price calculation (tax-inclusive price calculation)
 *
 * @param float $basePrice Base price
 * @param float $taxRate Tax rate (e.g., 0.10 = 10%)
 *
 * @return Result<array, string> Tax-inclusive price info or error
 */
function calculate_tax_inclusive_price(float $basePrice, float $taxRate): Result
{
    if ($basePrice < 0) {
        return Err::of('Base price cannot be negative');
    }

    if ($taxRate < 0) {
        return Err::of('Tax rate cannot be negative');
    }

    $taxAmount = $basePrice * $taxRate;
    $totalPrice = $basePrice + $taxAmount;

    return Ok::of([
        'base_price' => $basePrice,
        'tax_rate' => $taxRate,
        'tax_amount' => $taxAmount,
        'total_price' => $totalPrice,
        'effective_rate' => safe_divide($totalPrice, $basePrice)->unwrapOr(1.0),
    ]);
}

/**
 * Performance measurement: Execution time calculation
 *
 * @param callable $operation Operation to execute
 *
 * @return Result<array, string> Execution result and timing info
 */
function measure_performance(callable $operation): Result
{
    try {
        $startTime = microtime(true);
        $result = $operation();
        $endTime = microtime(true);

        $executionTime = $endTime - $startTime;

        return Ok::of([
            'result' => $result,
            'execution_time_ms' => $executionTime * 1000,
            'execution_time_readable' => number_format($executionTime * 1000, 2) . ' ms',
        ]);
    } catch (Exception $e) {
        return Err::of('Performance measurement failed: ' . $e->getMessage());
    }
}

/**
 * Result display function
 *
 * @param Result $result Result to display
 * @param string $label Label
 */
function display_result(Result $result, string $label = 'Result'): void
{
    echo "\n=== $label ===\n";

    if ($result->isOk()) {
        $value = $result->unwrap();
        if (is_array($value)) {
            echo "Success:\n";
            foreach ($value as $key => $val) {
                echo "  $key: " . (is_float($val) ? number_format($val, 4) : $val) . "\n";
            }
        } else {
            echo 'Success: ' . (is_float($value) ? number_format($value, 4) : $value) . "\n";
        }
    } else {
        $error = $result->unwrapErr();
        if (is_array($error)) {
            echo "Error:\n";
            foreach ($error as $key => $val) {
                echo "  $key: $val\n";
            }
        } else {
            echo "Error: $error\n";
        }
    }
}

// ===== Execution Examples =====

echo "PHP Result/Option Type Library - Safe Calculation Examples\n";
echo str_repeat('=', 60) . "\n";

// 1. Basic division
display_result(safe_divide(100, 5), 'Basic Division (100 ÷ 5)');
display_result(safe_divide(100, 0), 'Division by Zero Error (100 ÷ 0)');

// 2. Division with detailed error info
display_result(safe_divide_detailed(100, 0), 'Detailed Error Info');
display_result(safe_divide_detailed(INF, 5), 'Infinity Handling');

// 3. String input conversion
display_result(safe_divide_from_string('100', '5'), "String Input ('100' ÷ '5')");
display_result(safe_divide_from_string('100', 'abc'), "Invalid String ('100' ÷ 'abc')");

// 4. Chain division
display_result(chain_divide(1000, [2, 5, 10]), 'Chain Division (1000 ÷ 2 ÷ 5 ÷ 10)');
display_result(chain_divide(1000, [2, 0, 10]), 'Chain Division (error in middle)');

// 5. Formatted result display
echo "\n=== Formatted Results ===\n";
echo format_division_result(safe_divide(22, 7), 6) . "\n";
echo format_division_result(safe_divide(100, 0)) . "\n";

// 6. Statistical calculation
display_result(safe_average([10, 20, 30, 40, 50]), 'Average Calculation');
display_result(safe_average([]), 'Empty Array Average');

// 7. Practical example: Tax-inclusive price calculation
display_result(
    calculate_tax_inclusive_price(1000, 0.10),
    'Tax-Inclusive Price (Base: ¥1000, Tax: 10%)',
);
display_result(
    calculate_tax_inclusive_price(-100, 0.10),
    'Negative Price Error',
);

// 8. Performance measurement
$performanceResult = measure_performance(function () {
    $result = 0;
    for ($i = 1; $i <= 1000; $i++) {
        $divisionResult = safe_divide(100000, $i);
        if ($divisionResult->isOk()) {
            $result += $divisionResult->unwrap();
        }
    }

    return $result;
});

display_result($performanceResult, 'Performance Measurement (1000 safe divisions)');

// 9. Method chaining examples
echo "\n=== Method Chaining Example ===\n";

$chainResult = safe_divide(100, 4)
    ->map(fn ($x) => $x * 2)                    // 50 (25 * 2)
    ->andThen(fn ($x) => safe_divide($x, 10))   // 5 (50 ÷ 10)
    ->map(fn ($x) => 'Final result: ' . number_format($x, 1));

echo $chainResult->unwrapOr('Calculation error') . "\n";

// 10. Error handling pattern comparison
echo "\n=== Error Handling Patterns ===\n";

// Pattern 1: Early return
function early_return_pattern($a, $b, $c): string
{
    $step1 = safe_divide($a, $b);
    if ($step1->isErr()) {
        return 'Step 1 failed: ' . $step1->unwrapErr();
    }

    $step2 = safe_divide($step1->unwrap(), $c);
    if ($step2->isErr()) {
        return 'Step 2 failed: ' . $step2->unwrapErr();
    }

    return 'Success: ' . number_format($step2->unwrap(), 2);
}

// Pattern 2: Method chaining
function method_chain_pattern($a, $b, $c): string
{
    return safe_divide($a, $b)
        ->andThen(fn ($result) => safe_divide($result, $c))
        ->map(fn ($final) => 'Success: ' . number_format($final, 2))
        ->unwrapOr('Calculation failed');
}

echo 'Early return pattern: ' . early_return_pattern(100, 5, 2) . "\n";
echo 'Method chaining pattern: ' . method_chain_pattern(100, 5, 2) . "\n";

echo "\n" . str_repeat('=', 60) . "\n";
echo "Safe calculation examples completed.\n";
echo "Please check the benefits of error handling, type safety, and functional programming.\n";
