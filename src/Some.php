<?php

declare(strict_types=1);

namespace ba0918\Result;

use Override;

/**
 * Class representing an Option with a value
 *
 * @template T
 *
 * @implements Option<T>
 */
final class Some implements Option
{
    /**
     * @param T $value
     */
    public function __construct(private readonly mixed $value)
    {
    }

    /**
     * @template U
     *
     * @param U $value
     *
     * @return self<U>
     */
    public static function of(mixed $value): self
    {
        return new self($value);
    }

    /**
     * Checks if a value is present
     *
     * @return bool
     */
    #[Override]
    public function isSome(): bool
    {
        return true;
    }

    /**
     * Checks if no value is present
     *
     * @return bool
     */
    #[Override]
    public function isNone(): bool
    {
        return false;
    }

    /**
     * Validates the value with a predicate function if a value is present
     *
     * @param callable(T): bool $predicate
     *
     * @return bool
     */
    #[Override]
    public function isSomeAnd(callable $predicate): bool
    {
        return $predicate($this->value);
    }

    /**
     * Applies a function to the contained value if a value is present
     *
     * @template U
     *
     * @param callable(T): U $fn
     *
     * @return Option<U>
     */
    #[Override]
    public function map(callable $fn): Option
    {
        return Some::of($fn($this->value));
    }

    /**
     * Applies a function if a value is present, returns default value if absent
     *
     * @template U
     *
     * @param callable(T): U $fn
     * @param U $default
     *
     * @return U
     */
    #[Override]
    public function mapOr(callable $fn, mixed $default): mixed
    {
        return $fn($this->value);
    }

    /**
     * Applies a function if a value is present, returns closure result if absent
     *
     * @template U
     *
     * @param callable(T): U $fn
     * @param callable(): U $defaultFn
     *
     * @return U
     */
    #[Override]
    public function mapOrElse(callable $fn, callable $defaultFn): mixed
    {
        return $fn($this->value);
    }

    /**
     * Applies a function to the contained value if a value is present and returns the result
     *
     * @template U
     *
     * @param callable(T): Option<U> $fn
     *
     * @return Option<U>
     */
    #[Override]
    public function andThen(callable $fn): Option
    {
        return $fn($this->value);
    }

    /**
     * Checks if the value satisfies the predicate function if a value is present
     *
     * @param callable(T): bool $predicate
     *
     * @return Option<T>
     */
    #[Override]
    public function filter(callable $predicate): Option
    {
        return $predicate($this->value) ? $this : None::instance();
    }

    /**
     * Returns the value if present, throws exception if absent
     *
     * @return T
     */
    #[Override]
    public function unwrap(): mixed
    {
        return $this->value;
    }

    /**
     * Returns the value if present, returns default value if absent
     *
     * @template U
     *
     * @param U $default
     *
     * @return T|U
     */
    #[Override]
    public function unwrapOr(mixed $default): mixed
    {
        return $this->value;
    }

    /**
     * Returns the value if present, returns closure result if absent
     *
     * @template U
     *
     * @param callable(): U $fn
     *
     * @return T|U
     */
    #[Override]
    public function unwrapOrElse(callable $fn): mixed
    {
        return $this->value;
    }

    /**
     * Returns the value if present, throws exception with specified message if absent
     *
     * @param string $message
     *
     * @return T
     */
    #[Override]
    public function expect(string $message): mixed
    {
        return $this->value;
    }

    /**
     * Inspects the value and executes side effects (value remains unchanged)
     *
     * @param callable(T): void $fn
     *
     * @return Option<T>
     */
    #[Override]
    public function inspect(callable $fn): Option
    {
        $fn($this->value);

        return $this;
    }

    /**
     * Returns alternative Option if None (eager evaluation)
     *
     * @template U
     *
     * @param Option<U> $opt
     *
     * @return Option<T|U>
     */
    #[Override]
    public function or(Option $opt): Option
    {
        return $this;
    }

    /**
     * Returns alternative Option if None (lazy evaluation)
     *
     * @template U
     *
     * @param callable(): Option<U> $fn
     *
     * @return Option<T|U>
     */
    #[Override]
    public function orElse(callable $fn): Option
    {
        return $this;
    }

    /**
     * Returns another Option if Some, returns self if None (eager evaluation)
     *
     * @template U
     *
     * @param Option<U> $opt
     *
     * @return Option<U>
     */
    #[Override]
    public function and(Option $opt): Option
    {
        return $opt;
    }

    /**
     * Checks if the Some value contains the specified value
     *
     * @param mixed $value The value to check
     *
     * @return bool true if the Some value strictly equals the specified value, false otherwise
     */
    #[Override]
    public function contains(mixed $value): bool
    {
        return $this->value === $value;
    }

    /**
     * Converts Option<Result<T, E>> to Result<Option<T>, E>
     *
     * @return Result<mixed, mixed>
     */
    #[Override]
    public function transpose(): Result
    {
        // If Some(Result)
        if ($this->value instanceof Result) {
            if ($this->value->isOk()) {
                // Some(Ok(value)) → Ok(Some(value))
                /** @phpstan-ignore return.type */
                return Ok::of(Some::of($this->value->unwrap()));
            }

            // Some(Err(error)) → Err(error)
            return Err::of($this->value->unwrapErr());
        }

        // Some(non-Result) → Ok(Some(value))
        /** @phpstan-ignore return.type */
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
        return Ok::of($this->value);
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
        return Ok::of($this->value);
    }

    /**
     * Flattens a nested Option by one level
     *
     * @return Option<mixed>
     */
    #[Override]
    public function flatten(): Option
    {
        return $this->value instanceof Option ? $this->value : $this;
    }

    /**
     * Exclusive OR operation: Some if only one is Some, None if both Some/both None
     *
     * @template U
     *
     * @param Option<U> $opt
     *
     * @return Option<T|U>
     */
    #[Override]
    public function xor(Option $opt): Option
    {
        return $opt->isNone() ? $this : None::instance();
    }

    /**
     * Combines two Options: tuple if both Some, None if either is None
     *
     * @template U
     *
     * @param Option<U> $opt
     *
     * @return Option<array{T, U}>
     */
    #[Override]
    public function zip(Option $opt): Option
    {
        return $opt->isSome() ? Some::of([$this->value, $opt->unwrap()]) : None::instance();
    }
}
