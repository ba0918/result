<?php

namespace Mizumi\Result;

use Mizumi\Result\Exception\UnwrapException;

/**
 * 成功を表すクラス
 *
 * @template T
 * @implements Result<T, never>
 */
final class Ok implements Result
{
    /**
     * @param T $value
     */
    public function __construct(private readonly mixed $value)
    {

    }

    /**
     * @param T $value
     * @return self<T>
     */
    public static function of(mixed $value): self
    {
        return new self($value);
    }

    #[\Override]
    public function isOk(): bool
    {
        return true;
    }

    #[\Override]
    public function isErr(): bool
    {
        return false;
    }

    #[\Override]
    public function map(callable $fn): Result
    {
        return new Ok($fn($this->value));
    }

    #[\Override]
    public function mapErr(callable $fn): Result
    {
        return $this;
    }

    /**
     * @template U
     * @template F
     * @param callable(T): Result<U, F> $fn
     * @return Result<U, F>
     */
    #[\Override]
    public function andThen(callable $fn): Result
    {
        return $fn($this->value);
    }

    #[\Override]
    public function unwrap(): mixed
    {
        return $this->value;
    }

    #[\Override]
    public function unwrapErr(): mixed
    {
        throw new UnwrapException('Called unwrapErr() on an Ok value: ' . print_r($this->value, true));
    }

    #[\Override]
    public function unwrapOr(mixed $default): mixed
    {
        return $this->value;
    }

    #[\Override]
    public function unwrapOrElse(callable $fn): mixed
    {
        return $this->value;
    }

    #[\Override]
    public function expect(string $message): mixed
    {
        return $this->value;
    }

    #[\Override]
    public function inspect(callable $fn): Result
    {
        $fn($this->value);
        return $this;
    }

    #[\Override]
    public function inspectErr(callable $fn): Result
    {
        return $this;
    }

    #[\Override]
    public function or(Result $res): Result
    {
        return $this;
    }

    #[\Override]
    public function orElse(callable $fn): Result
    {
        return $this;
    }

    #[\Override]
    public function and(Result $res): Result
    {
        return $res;
    }

    #[\Override]
    public function contains(mixed $value): bool
    {
        return $this->value === $value;
    }

    #[\Override]
    public function containsErr(mixed $error): bool
    {
        return false;
    }

    #[\Override]
    public function flatten(): Result
    {
        return $this->value instanceof Result ? $this->value : $this;
    }
}
