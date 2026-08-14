<?php

declare(strict_types=1);

namespace ba0918\Result;

use ba0918\Result\Exception\UnwrapException;

/**
 * Type for representing success/failure states
 *
 * @template T The type of the success value
 * @template E The type of the error value
 */
interface Result
{
    /**
     * Checks if the result is a success
     *
     * @return bool
     */
    public function isOk(): bool;

    /**
     * Checks if the result is an error
     *
     * @return bool
     */
    public function isErr(): bool;

    /**
     * Validates the value with a predicate function if successful
     *
     * @param callable(T): bool $predicate
     *
     * @return bool
     */
    public function isOkAnd(callable $predicate): bool;

    /**
     * Validates the error with a predicate function if failed
     *
     * @param callable(E): bool $predicate
     *
     * @return bool
     */
    public function isErrAnd(callable $predicate): bool;

    /**
     * Applies a function to the contained value if successful
     *
     * @template U
     *
     * @param callable(T): U $fn
     *
     * @return Result<U, E>
     */
    public function map(callable $fn): Result;

    /**
     * Applies a function to the contained error if failed
     *
     * @template F
     *
     * @param callable(E): F $fn
     *
     * @return Result<T, F>
     */
    public function mapErr(callable $fn): Result;

    /**
     * Applies a function if successful, returns default value if failed
     *
     * @template U
     *
     * @param callable(T): U $fn
     * @param U $default
     *
     * @return U
     */
    public function mapOr(callable $fn, mixed $default): mixed;

    /**
     * Applies a function if successful, returns closure result if failed
     *
     * @template U
     *
     * @param callable(T): U $fn
     * @param callable(E): U $defaultFn
     *
     * @return U
     */
    public function mapOrElse(callable $fn, callable $defaultFn): mixed;

    /**
     * Applies a function to the contained value if successful and returns the result
     *
     * @template U
     * @template F
     *
     * @param callable(T): Result<U, F> $fn
     *
     * @return Result<U, E|F>
     */
    public function andThen(callable $fn): Result;

    /**
     * Returns the value if successful, throws exception if failed
     *
     * @throws UnwrapException
     *
     * @return T
     */
    public function unwrap(): mixed;

    /**
     * Returns the error if failed, throws exception if successful
     *
     * @throws UnwrapException
     *
     * @return E
     */
    public function unwrapErr(): mixed;

    /**
     * Returns the value if successful, returns default value if failed
     *
     * @template U
     *
     * @param U $default
     *
     * @return T|U
     */
    public function unwrapOr(mixed $default): mixed;

    /**
     * Returns the value if successful, returns closure result if failed
     *
     * @template U
     *
     * @param callable(E): U $fn
     *
     * @return T|U
     */
    public function unwrapOrElse(callable $fn): mixed;

    /**
     * Returns the value if successful, throws exception with specified message if failed
     *
     * @param string $message
     *
     * @throws UnwrapException
     *
     * @return T
     */
    public function expect(string $message): mixed;

    /**
     * Inspects the success value and executes side effects (value remains unchanged)
     *
     * @param callable(T): void $fn
     *
     * @return Result<T, E>
     */
    public function inspect(callable $fn): Result;

    /**
     * Inspects the error value and executes side effects (error remains unchanged)
     *
     * @param callable(E): void $fn
     *
     * @return Result<T, E>
     */
    public function inspectErr(callable $fn): Result;

    /**
     * Returns alternative Result if Err (eager evaluation)
     *
     * @template U
     * @template F
     *
     * @param Result<U, F> $res
     *
     * @return Result<T|U, F>
     */
    public function or(Result $res): Result;

    /**
     * Returns alternative Result if Err (lazy evaluation)
     *
     * @template U
     * @template F
     *
     * @param callable(E): Result<U, F> $fn
     *
     * @return Result<T|U, F>
     */
    public function orElse(callable $fn): Result;

    /**
     * Returns another Result if Ok, returns self if Err (eager evaluation)
     *
     * @template U
     * @template F
     *
     * @param Result<U, F> $res
     *
     * @return Result<U, E|F>
     */
    public function and(Result $res): Result;

    /**
     * Checks if Ok value contains the specified value
     *
     * @param mixed $value The value to check
     *
     * @return bool true if Ok value strictly equals the specified value, false otherwise
     */
    public function contains(mixed $value): bool;

    /**
     * Checks if Err value contains the specified error
     *
     * @param mixed $error The error value to check
     *
     * @return bool true if Err value strictly equals the specified error, false otherwise
     */
    public function containsErr(mixed $error): bool;

    /**
     * Flattens a nested Result by one level
     *
     * @return Result<T, E>
     */
    public function flatten(): Result;

    /**
     * Converts Result<Option<T>, E> → Option<Result<T, E>>
     *
     * @return Option<mixed>
     */
    public function transpose(): Option;

    /**
     * Gets the success value as Option
     *
     * @return Option<T>
     */
    public function ok(): Option;

    /**
     * Gets the error value as Option
     *
     * @return Option<E>
     */
    public function err(): Option;

    /**
     * Returns the error value if failed, throws exception with specified message if successful
     *
     * @param string $message
     *
     * @throws UnwrapException
     *
     * @return E
     */
    public function expectErr(string $message): mixed;
}
