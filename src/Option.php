<?php

declare(strict_types=1);

namespace Mizumi\Result;

use Mizumi\Result\Exception\UnwrapException;

/**
 * Type for representing presence/absence of a value
 *
 * @template T The type of the value
 */
interface Option
{
    /**
     * Checks if a value is present
     *
     * @return bool
     */
    public function isSome(): bool;

    /**
     * Checks if no value is present
     *
     * @return bool
     */
    public function isNone(): bool;

    /**
     * Validates the value with a predicate function if value is present
     *
     * @param callable(T): bool $predicate
     *
     * @return bool
     */
    public function isSomeAnd(callable $predicate): bool;

    /**
     * Applies a function to the contained value if present
     *
     * @template U
     *
     * @param callable(T): U $fn
     *
     * @return Option<U>
     */
    public function map(callable $fn): Option;

    /**
     * Applies a function if value is present, returns default value if absent
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
     * Applies a function if value is present, returns closure result if absent
     *
     * @template U
     *
     * @param callable(T): U $fn
     * @param callable(): U $defaultFn
     *
     * @return U
     */
    public function mapOrElse(callable $fn, callable $defaultFn): mixed;

    /**
     * Applies a function to the contained value if present and returns the result
     *
     * @template U
     *
     * @param callable(T): Option<U> $fn
     *
     * @return Option<U>
     */
    public function andThen(callable $fn): Option;

    /**
     * Checks if the value satisfies the predicate function if present
     *
     * @param callable(T): bool $predicate
     *
     * @return Option<T>
     */
    public function filter(callable $predicate): Option;

    /**
     * Returns the value if present, throws exception if absent
     *
     * @throws UnwrapException
     *
     * @return T
     */
    public function unwrap(): mixed;

    /**
     * Returns the value if present, returns default value if absent
     *
     * @template U
     *
     * @param U $default
     *
     * @return T|U
     */
    public function unwrapOr(mixed $default): mixed;

    /**
     * Returns the value if present, returns closure result if absent
     *
     * @template U
     *
     * @param callable(): U $fn
     *
     * @return T|U
     */
    public function unwrapOrElse(callable $fn): mixed;

    /**
     * Returns the value if present, throws exception with specified message if absent
     *
     * @param string $message
     *
     * @throws UnwrapException
     *
     * @return T
     */
    public function expect(string $message): mixed;

    /**
     * Inspects the value and executes side effects (value remains unchanged)
     *
     * @param callable(T): void $fn
     *
     * @return Option<T>
     */
    public function inspect(callable $fn): Option;

    /**
     * Returns alternative Option if None (eager evaluation)
     *
     * @template U
     *
     * @param Option<U> $opt
     *
     * @return Option<T|U>
     */
    public function or(Option $opt): Option;

    /**
     * Returns alternative Option if None (lazy evaluation)
     *
     * @template U
     *
     * @param callable(): Option<U> $fn
     *
     * @return Option<T|U>
     */
    public function orElse(callable $fn): Option;

    /**
     * Returns another Option if Some, returns self if None (eager evaluation)
     *
     * @template U
     *
     * @param Option<U> $opt
     *
     * @return Option<U>
     */
    public function and(Option $opt): Option;

    /**
     * Checks if Some value contains the specified value
     *
     * @param mixed $value The value to check
     *
     * @return bool true if Some value strictly equals the specified value, false otherwise
     */
    public function contains(mixed $value): bool;

    /**
     * Converts Option<Result<T, E>> → Result<Option<T>, E>
     *
     * @return Result<mixed, mixed>
     */
    public function transpose(): Result;

    /**
     * Converts Option to Result (None becomes Err with specified error)
     *
     * @param mixed $err
     *
     * @return Result<mixed, mixed>
     */
    public function okOr(mixed $err): Result;

    /**
     * Converts Option to Result (None becomes Err with closure result)
     *
     * @param callable $fn
     *
     * @return Result<mixed, mixed>
     */
    public function okOrElse(callable $fn): Result;

    /**
     * Flattens a nested Option by one level
     *
     * @return Option<mixed>
     */
    public function flatten(): Option;

    /**
     * Exclusive OR operation: Some if only one is Some, None if both Some/both None
     *
     * @template U
     *
     * @param Option<U> $opt
     *
     * @return Option<T|U>
     */
    public function xor(Option $opt): Option;

    /**
     * Combines two Options: tuple if both Some, None if either is None
     *
     * @template U
     *
     * @param Option<U> $opt
     *
     * @return Option<array{T, U}>
     */
    public function zip(Option $opt): Option;
}
