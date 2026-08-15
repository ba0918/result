<?php

declare(strict_types=1);

namespace ba0918\Result;

use ba0918\Result\Exception\UnwrapException;
use Override;

/**
 * Class representing an Option without a value
 *
 * @implements Option<never>
 */
final class None implements Option
{
    private static ?self $instance = null;

    private function __construct()
    {
    }

    /**
     * Gets the None singleton instance
     *
     * @return self
     */
    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Checks if a value is present
     *
     * @return bool
     */
    #[Override]
    public function isSome(): bool
    {
        return false;
    }

    /**
     * Checks if no value is present
     *
     * @return bool
     */
    #[Override]
    public function isNone(): bool
    {
        return true;
    }

    /**
     * Validates the value with a predicate function if a value is present
     *
     * @param callable(never): bool $predicate
     *
     * @return bool
     */
    #[Override]
    public function isSomeAnd(callable $predicate): bool
    {
        return false;
    }

    /**
     * Applies a function to the contained value if a value is present
     *
     * @template U
     *
     * @param callable(never): U $fn
     *
     * @return Option<U>
     */
    #[Override]
    public function map(callable $fn): Option
    {
        return $this;
    }

    /**
     * Applies a function if a value is present, returns default value if absent
     *
     * @template U
     *
     * @param callable(never): U $fn
     * @param U $default
     *
     * @return U
     */
    #[Override]
    public function mapOr(callable $fn, mixed $default): mixed
    {
        return $default;
    }

    /**
     * Applies a function if a value is present, returns closure result if absent
     *
     * @template U
     *
     * @param callable(never): U $fn
     * @param callable(): U $defaultFn
     *
     * @return U
     */
    #[Override]
    public function mapOrElse(callable $fn, callable $defaultFn): mixed
    {
        return $defaultFn();
    }

    /**
     * Applies a function to the contained value if a value is present and returns the result
     *
     * @template U
     *
     * @param callable(never): Option<U> $fn
     *
     * @return Option<U>
     */
    #[Override]
    public function andThen(callable $fn): Option
    {
        return $this;
    }

    /**
     * Checks if the value satisfies the predicate function if a value is present
     *
     * @param callable(never): bool $predicate
     *
     * @return Option<never>
     */
    #[Override]
    public function filter(callable $predicate): Option
    {
        return $this;
    }

    /**
     * Returns the value if present, throws exception if absent
     *
     * @throws UnwrapException
     *
     * @return never
     */
    #[Override]
    public function unwrap(): mixed
    {
        throw new UnwrapException('Called unwrap() on a None value');
    }

    /**
     * Returns the value if present, returns default value if absent
     *
     * @template U
     *
     * @param U $default
     *
     * @return U
     */
    #[Override]
    public function unwrapOr(mixed $default): mixed
    {
        return $default;
    }

    /**
     * Returns the value if present, returns closure result if absent
     *
     * @template U
     *
     * @param callable(): U $fn
     *
     * @return U
     */
    #[Override]
    public function unwrapOrElse(callable $fn): mixed
    {
        return $fn();
    }

    /**
     * Returns the value if present, throws exception with specified message if absent
     *
     * @param string $message
     *
     * @throws UnwrapException
     *
     * @return never
     */
    #[Override]
    public function expect(string $message): mixed
    {
        throw new UnwrapException($message);
    }

    /**
     * Inspects the value and executes side effects (value remains unchanged)
     *
     * @param callable(never): void $fn
     *
     * @return Option<never>
     */
    #[Override]
    public function inspect(callable $fn): Option
    {
        return $this;
    }

    /**
     * Returns alternative Option if None (eager evaluation)
     *
     * @template U
     *
     * @param Option<U> $opt
     *
     * @return Option<U>
     */
    #[Override]
    public function or(Option $opt): Option
    {
        return $opt;
    }

    /**
     * Returns alternative Option if None (lazy evaluation)
     *
     * @template U
     *
     * @param callable(): Option<U> $fn
     *
     * @return Option<U>
     */
    #[Override]
    public function orElse(callable $fn): Option
    {
        return $fn();
    }

    /**
     * Returns another Option if Some, returns self if None (eager evaluation)
     *
     * @template U
     *
     * @param Option<U> $opt
     *
     * @return Option<never>
     */
    #[Override]
    public function and(Option $opt): Option
    {
        return $this;
    }

    /**
     * Checks if the Some value contains the specified value
     *
     * @param mixed $value The value to check
     *
     * @return false
     */
    #[Override]
    public function contains(mixed $value): bool
    {
        return false;
    }

    /**
     * Converts Option<Result<T, E>> to Result<Option<T>, E>
     *
     * @return Result<mixed, mixed>
     */
    #[Override]
    public function transpose(): Result
    {
        // None → Ok(None)
        return Ok::of($this);
    }

    /**
     * Converts Option to Result (None becomes Err with the specified error)
     *
     * @param mixed $err
     *
     * @return Result<mixed, mixed>
     */
    #[Override]
    public function okOr(mixed $err): Result
    {
        return Err::of($err);
    }

    /**
     * Converts Option to Result (None becomes Err with the closure result)
     *
     * @param callable $fn
     *
     * @return Result<mixed, mixed>
     */
    #[Override]
    public function okOrElse(callable $fn): Result
    {
        return Err::of($fn());
    }

    /**
     * Flattens a nested Option by one level
     *
     * @return Option<mixed>
     */
    #[Override]
    public function flatten(): Option
    {
        return $this;
    }

    /**
     * Exclusive OR operation: Some if only one is Some, None if both Some/both None
     *
     * @template U
     *
     * @param Option<U> $opt
     *
     * @return Option<U>
     */
    #[Override]
    public function xor(Option $opt): Option
    {
        return $opt;
    }

    /**
     * Combines two Options: tuple if both Some, None if either is None
     *
     * @template U
     *
     * @param Option<U> $opt
     *
     * @return Option<never>
     */
    #[Override]
    public function zip(Option $opt): Option
    {
        return $this;
    }
}
