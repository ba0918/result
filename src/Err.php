<?php

namespace Mizumi\Result;

use Mizumi\Result\Exception\UnwrapException;

/**
 * 失敗を表すクラス
 *
 * @template E
 * @implements Result<never, E>
 */
final class Err implements Result
{
    /**
     * @param E $error
     */
    public function __construct(private readonly mixed $error)
    {

    }

    /**
     * @param E $error
     * @return self<E>
     */
    public static function of(mixed $error): self
    {
        return new self($error);
    }

    #[\Override]
    public function isOk(): bool
    {
        return false;
    }

    #[\Override]
    public function isErr(): bool
    {
        return true;
    }

    #[\Override]
    public function isOkAnd(callable $predicate): bool
    {
        return false;
    }

    #[\Override]
    public function isErrAnd(callable $predicate): bool
    {
        return $predicate($this->error);
    }

    #[\Override]
    public function map(callable $fn): Result
    {
        return $this;
    }

    #[\Override]
    public function mapErr(callable $fn): Result
    {
        return new Err($fn($this->error));
    }

    #[\Override]
    public function mapOr(callable $fn, mixed $default): mixed
    {
        return $default;
    }

    #[\Override]
    public function mapOrElse(callable $fn, callable $defaultFn): mixed
    {
        return $defaultFn($this->error);
    }

    /**
     * @template U
     * @template F
     * @param callable(never): Result<U, F> $fn
     * @return Result<U, E>
     */
    #[\Override]
    public function andThen(callable $fn): Result
    {
        return $this;
    }

    #[\Override]
    public function unwrap(): mixed
    {
        throw new UnwrapException('Called unwrap() on an Err value: ' . print_r($this->error, true));
    }

    #[\Override]
    public function unwrapErr(): mixed
    {
        return $this->error;
    }

    #[\Override]
    public function unwrapOr(mixed $default): mixed
    {
        return $default;
    }

    #[\Override]
    public function unwrapOrElse(callable $fn): mixed
    {
        return $fn($this->error);
    }

    #[\Override]
    public function expect(string $message): mixed
    {
        throw new UnwrapException($message . ': ' . print_r($this->error, true));
    }

    #[\Override]
    public function inspect(callable $fn): Result
    {
        return $this;
    }

    #[\Override]
    public function inspectErr(callable $fn): Result
    {
        $fn($this->error);
        return $this;
    }

    #[\Override]
    public function or(Result $res): Result
    {
        return $res;
    }

    #[\Override]
    public function orElse(callable $fn): Result
    {
        return $fn($this->error);
    }

    #[\Override]
    public function and(Result $res): Result
    {
        return $this;
    }

    #[\Override]
    public function contains(mixed $value): bool
    {
        return false;
    }

    #[\Override]
    public function containsErr(mixed $error): bool
    {
        return $this->error === $error;
    }

    #[\Override]
    public function flatten(): Result
    {
        return $this;
    }

    /**
     * @return Option<mixed>
     */
    #[\Override]
    public function transpose(): Option
    {
        // Err(error) → Some(Err(error))
        return Some::of($this);
    }

    #[\Override]
    public function ok(): Option
    {
        return None::instance();
    }

    #[\Override]
    public function err(): Option
    {
        /** @phpstan-ignore return.type */
        return Some::of($this->error);
    }

    #[\Override]
    public function expectErr(string $message): mixed
    {
        return $this->error;
    }
}
