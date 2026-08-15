<?php

declare(strict_types=1);

namespace ba0918\Result\Pipe;

use ba0918\Result\Result;
use Closure;

/**
 * Applies a function to the contained value if successful
 *
 * @template T
 * @template U
 *
 * @param callable(T): U $fn
 *
 * @return Closure<E>(Result<T, E>): Result<U, E>
 */
function map(callable $fn): Closure
{
    /**
     * PHPStan cannot prove this closure body against the declared generic
     * contract: native types cannot express Result<T, E>, so the body infers
     * Result<U, mixed> and fails the variance check. The declared contract
     * still governs call sites (mismatched inputs are static errors) and the
     * behavior is covered by PipeFunctionsTest.
     *
     * @phpstan-ignore return.type
     */
    return static fn (Result $result): Result => $result->map($fn);
}

/**
 * Applies a function to the contained error if failed
 *
 * @template E
 * @template F
 *
 * @param callable(E): F $fn
 *
 * @return Closure<T>(Result<T, E>): Result<T, F>
 */
function mapErr(callable $fn): Closure
{
    /**
     * PHPStan cannot prove this closure body against the declared generic
     * contract: native types cannot express Result<T, E>, so the body infers
     * Result<mixed, F> and fails the variance check. The declared contract
     * still governs call sites (mismatched inputs are static errors) and the
     * behavior is covered by PipeFunctionsTest.
     *
     * @phpstan-ignore return.type
     */
    return static fn (Result $result): Result => $result->mapErr($fn);
}

/**
 * Applies a function if successful and returns the result
 *
 * @template T
 * @template U
 * @template F
 *
 * @param callable(T): Result<U, F> $fn
 *
 * @return Closure<E>(Result<T, E>): Result<U, E|F>
 */
function andThen(callable $fn): Closure
{
    /**
     * PHPStan cannot prove this closure body against the declared generic
     * contract: native types cannot express Result<T, E>, so the body infers
     * Result<U, mixed> and fails the variance check. The declared contract
     * still governs call sites (mismatched inputs are static errors) and the
     * behavior is covered by PipeFunctionsTest.
     *
     * @phpstan-ignore return.type
     */
    return static fn (Result $result): Result => $result->andThen($fn);
}

/**
 * Returns the closure result if failed
 *
 * @template E
 * @template U
 * @template F
 *
 * @param callable(E): Result<U, F> $fn
 *
 * @return Closure<T>(Result<T, E>): Result<T|U, F>
 */
function orElse(callable $fn): Closure
{
    /**
     * PHPStan cannot prove this closure body against the declared generic
     * contract: native types cannot express Result<T, E>, so the body infers
     * Result<mixed, F> and fails the variance check. The declared contract
     * still governs call sites (mismatched inputs are static errors) and the
     * behavior is covered by PipeFunctionsTest.
     *
     * @phpstan-ignore return.type
     */
    return static fn (Result $result): Result => $result->orElse($fn);
}

/**
 * Inspects the success value and executes side effects (value remains unchanged)
 *
 * @template T
 *
 * @param callable(T): void $fn
 *
 * @return Closure<E>(Result<T, E>): Result<T, E>
 */
function inspect(callable $fn): Closure
{
    /**
     * PHPStan cannot prove this closure body against the declared generic
     * contract: native types cannot express Result<T, E>, so the body infers
     * Result<mixed, mixed> and fails the variance check. The declared contract
     * still governs call sites (mismatched inputs are static errors) and the
     * behavior is covered by PipeFunctionsTest.
     *
     * @phpstan-ignore return.type
     */
    return static fn (Result $result): Result => $result->inspect($fn);
}

/**
 * Inspects the error value and executes side effects (error remains unchanged)
 *
 * @template E
 *
 * @param callable(E): void $fn
 *
 * @return Closure<T>(Result<T, E>): Result<T, E>
 */
function inspectErr(callable $fn): Closure
{
    /**
     * PHPStan cannot prove this closure body against the declared generic
     * contract: native types cannot express Result<T, E>, so the body infers
     * Result<mixed, mixed> and fails the variance check. The declared contract
     * still governs call sites (mismatched inputs are static errors) and the
     * behavior is covered by PipeFunctionsTest.
     *
     * @phpstan-ignore return.type
     */
    return static fn (Result $result): Result => $result->inspectErr($fn);
}
