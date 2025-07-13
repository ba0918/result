<?php

namespace Mizumi\Result;

use Mizumi\Result\Exception\UnwrapException;

/**
 * 値を持つOptionを表すクラス
 *
 * @template T
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
     * @param T $value
     * @return self<T>
     */
    public static function of(mixed $value): self
    {
        return new self($value);
    }

    /**
     * 値を持っているか確認する
     *
     * @return bool
     */
    public function isSome(): bool
    {
        return true;
    }

    /**
     * 値を持っていないか確認する
     *
     * @return bool
     */
    public function isNone(): bool
    {
        return false;
    }

    /**
     * 値を持っている場合、中の値に関数を適用する
     *
     * @template U
     * @param callable(T): U $fn
     * @return Option<U>
     */
    public function map(callable $fn): Option
    {
        return new Some($fn($this->value));
    }

    /**
     * 値を持っている場合は関数を適用し、持っていない場合はデフォルト値を返す
     *
     * @template U
     * @param callable(T): U $fn
     * @param U $default
     * @return U
     */
    public function mapOr(callable $fn, mixed $default): mixed
    {
        return $fn($this->value);
    }

    /**
     * 値を持っている場合は関数を適用し、持っていない場合はクロージャの結果を返す
     *
     * @template U
     * @param callable(T): U $fn
     * @param callable(): U $defaultFn
     * @return U
     */
    public function mapOrElse(callable $fn, callable $defaultFn): mixed
    {
        return $fn($this->value);
    }

    /**
     * 値を持っている場合、中の値に関数を適用し、その結果を返す
     *
     * @template U
     * @param callable(T): Option<U> $fn
     * @return Option<U>
     */
    public function andThen(callable $fn): Option
    {
        return $fn($this->value);
    }

    /**
     * 値を持っている場合、述語関数を満たすかチェックする
     *
     * @param callable(T): bool $predicate
     * @return Option<T>
     */
    public function filter(callable $predicate): Option
    {
        return $predicate($this->value) ? $this : None::instance();
    }

    /**
     * 値を持っていれば値を返し、持っていなければ例外をスローする
     *
     * @return T
     */
    public function unwrap(): mixed
    {
        return $this->value;
    }

    /**
     * 値を持っていれば値を返し、持っていなければデフォルト値を返す
     *
     * @template U
     * @param U $default
     * @return T|U
     */
    public function unwrapOr(mixed $default): mixed
    {
        return $this->value;
    }

    /**
     * 値を持っていれば値を返し、持っていなければクロージャの結果を返す
     *
     * @template U
     * @param callable(): U $fn
     * @return T|U
     */
    public function unwrapOrElse(callable $fn): mixed
    {
        return $this->value;
    }

    /**
     * 値を持っていれば値を返し、持っていなければ指定されたメッセージで例外をスローする
     *
     * @param string $message
     * @return T
     */
    public function expect(string $message): mixed
    {
        return $this->value;
    }

    /**
     * 値を検査し、副作用を実行する（値は変更しない）
     *
     * @param callable(T): void $fn
     * @return Option<T>
     */
    public function inspect(callable $fn): Option
    {
        $fn($this->value);
        return $this;
    }

    /**
     * Noneの場合に代替のOptionを返す（即座評価）
     *
     * @template U
     * @param Option<U> $opt
     * @return Option<T|U>
     */
    public function or(Option $opt): Option
    {
        return $this;
    }

    /**
     * Noneの場合に代替のOptionを返す（遅延評価）
     *
     * @template U
     * @param callable(): Option<U> $fn
     * @return Option<T|U>
     */
    public function orElse(callable $fn): Option
    {
        return $this;
    }

    /**
     * Someの場合に別のOptionを返し、Noneの場合は自身を返す（即座評価）
     *
     * @template U
     * @param Option<U> $opt
     * @return Option<U>
     */
    public function and(Option $opt): Option
    {
        return $opt;
    }

    /**
     * Some値が指定された値を含むかどうかを確認する
     *
     * @param mixed $value 確認したい値
     * @return bool Some値が指定値と厳密に等価な場合true、それ以外はfalse
     */
    public function contains(mixed $value): bool
    {
        return $this->value === $value;
    }

    /**
     * Option<Result<T, E>> → Result<Option<T>, E> への変換
     *
     * @return Result<mixed, mixed>
     */
    public function transpose(): Result
    {
        // Some(Result) の場合
        if ($this->value instanceof Result) {
            if ($this->value->isOk()) {
                // Some(Ok(value)) → Ok(Some(value))
                return Ok::of(Some::of($this->value->unwrap()));
            } else {
                // Some(Err(error)) → Err(error)
                return Err::of($this->value->unwrapErr());
            }
        }

        // Some(non-Result) → Ok(Some(value))
        return Ok::of($this);
    }

    /**
     * OptionをResultに変換する（Noneの場合は指定されたエラーでErr）
     *
     * @param mixed $err
     * @return Result<mixed, mixed>
     */
    public function okOr(mixed $err): Result
    {
        return Ok::of($this->value);
    }

    /**
     * OptionをResultに変換する（Noneの場合はクロージャの結果でErr）
     *
     * @param callable $fn
     * @return Result<mixed, mixed>
     */
    public function okOrElse(callable $fn): Result
    {
        return Ok::of($this->value);
    }
}