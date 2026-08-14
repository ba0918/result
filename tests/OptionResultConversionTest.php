<?php

declare(strict_types=1);

namespace ba0918\Result\Tests;

use ba0918\Result\None;
use ba0918\Result\Some;
use Exception;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Tests for mutual conversion between Option and Result
 */
final class OptionResultConversionTest extends TestCase
{
    public function testSomeOkOr(): void
    {
        // Some(value) → Ok(value)
        $option = Some::of('success');
        $result = $option->okOr('error');

        $this->assertTrue($result->isOk());
        $this->assertSame('success', $result->unwrap());
    }

    public function testNoneOkOr(): void
    {
        // None → Err(error)
        $option = None::instance();
        $result = $option->okOr('error');

        $this->assertTrue($result->isErr());
        $this->assertSame('error', $result->unwrapErr());
    }

    public function testSomeOkOrElse(): void
    {
        // Some(value) → Ok(value) (the closure is not called)
        $called = false;
        $option = Some::of('value');
        $result = $option->okOrElse(function () use (&$called) {
            $called = true;

            return 'error';
        });

        $this->assertTrue($result->isOk());
        $this->assertSame('value', $result->unwrap());
        $this->assertFalse($called);
    }

    public function testNoneOkOrElse(): void
    {
        // None → Err(closure_result)
        $option = None::instance();
        $result = $option->okOrElse(fn () => 'computed_error');

        $this->assertTrue($result->isErr());
        $this->assertSame('computed_error', $result->unwrapErr());
    }

    public function testOkOrWithDifferentErrorTypes(): void
    {
        // Tests with different error types
        $errors = [
            'string_error',
            42,
            ['array', 'error'],
            new Exception('exception_error'),
            null,
        ];

        foreach ($errors as $error) {
            $option = None::instance();
            $result = $option->okOr($error);

            $this->assertTrue($result->isErr());
            $this->assertSame($error, $result->unwrapErr());
        }
    }

    public function testOkOrElseWithDifferentReturnTypes(): void
    {
        // Tests with closures of different return types
        $testCases = [
            ['generator' => fn () => 'string', 'expected' => 'string'],
            ['generator' => fn () => 123, 'expected' => 123],
            ['generator' => fn () => ['array'], 'expected' => ['array']],
            ['generator' => fn () => null, 'expected' => null],
        ];

        foreach ($testCases as $testCase) {
            $option = None::instance();
            $result = $option->okOrElse($testCase['generator']);

            $this->assertTrue($result->isErr());
            $this->assertSame($testCase['expected'], $result->unwrapErr());
        }

        // Objects are tested separately
        $option = None::instance();
        $result = $option->okOrElse(fn () => new stdClass());

        $this->assertTrue($result->isErr());
        $this->assertInstanceOf(stdClass::class, $result->unwrapErr());
    }

    public function testOkOrWithComplexValues(): void
    {
        // Tests with complex values
        $complexValue = [
            'nested' => [
                'object' => new stdClass(),
                'array' => [1, 2, 3],
            ],
        ];

        $option = Some::of($complexValue);
        $result = $option->okOr('error');

        $this->assertTrue($result->isOk());
        $this->assertSame($complexValue, $result->unwrap());
    }

    public function testOkOrElseWithClosureState(): void
    {
        // Tests with closure state
        $counter = 0;
        $option = None::instance();

        $result = $option->okOrElse(function () use (&$counter) {
            $counter++;

            return "error_{$counter}";
        });

        $this->assertTrue($result->isErr());
        $this->assertSame('error_1', $result->unwrapErr());
        $this->assertSame(1, $counter);
    }

    public function testChainedOkOrOperations(): void
    {
        // Method chain tests
        $value = Some::of(42)
            ->okOr('error')
            ->map(function (mixed $x): int {
                assert(is_int($x));

                return $x * 2;
            })
            ->unwrap();

        $this->assertSame(84, $value);

        $error = None::instance()
            ->okOr('original_error')
            ->mapErr(function (mixed $e): string {
                assert(is_string($e));

                return "wrapped_{$e}";
            })
            ->unwrapErr();

        $this->assertSame('wrapped_original_error', $error);
    }

    public function testOkOrPreservesOriginalValue(): void
    {
        // Tests that the original value is preserved
        $original = 'original';
        $option = Some::of($original);
        $result = $option->okOr('error');

        // Confirm identity of the reference
        $this->assertTrue($result->isOk());
        $this->assertSame($original, $result->unwrap());
    }

    public function testOkOrElseLazyEvaluation(): void
    {
        // Tests for lazy evaluation
        $expensiveOperationCalled = false;

        $option = Some::of('value');
        $result = $option->okOrElse(function () use (&$expensiveOperationCalled) {
            $expensiveOperationCalled = true;

            // Simulation of an expensive operation
            return 'expensive_result';
        });

        $this->assertTrue($result->isOk());
        $this->assertSame('value', $result->unwrap());
        $this->assertFalse($expensiveOperationCalled);
    }
}
