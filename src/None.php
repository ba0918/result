<?php

namespace Mizumi\Result;

use Mizumi\Result\Exception\UnwrapException;

/**
 * 値を持たないOptionを表すクラス
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
     * Noneのシングルトンインスタンスを取得する
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
     * 値を持っているか確認する
     *
     * @return bool
     */
    public function isSome(): bool
    {
        return false;
    }

    /**
     * 値を持っていないか確認する
     *
     * @return bool
     */
    public function isNone(): bool
    {
        return true;
    }

    /**
     * 値を持っている場合、中の値に関数を適用する
     *
     * @template U
     * @param callable(never): U $fn
     * @return Option<U>
     */
    public function map(callable $fn): Option
    {
        return $this;
    }

    /**
     * 値を持っている場合は関数を適用し、持っていない場合はデフォルト値を返す
     *
     * @template U
     * @param callable(never): U $fn
     * @param U $default
     * @return U
     */
    public function mapOr(callable $fn, mixed $default): mixed
    {
        return $default;
    }

    /**
     * 値を持っている場合は関数を適用し、持っていない場合はクロージャの結果を返す
     *
     * @template U
     * @param callable(never): U $fn
     * @param callable(): U $defaultFn
     * @return U
     */
    public function mapOrElse(callable $fn, callable $defaultFn): mixed
    {
        return $defaultFn();
    }

    /**
     * 値を持っている場合、中の値に関数を適用し、その結果を返す
     *
     * @template U
     * @param callable(never): Option<U> $fn
     * @return Option<U>
     */
    public function andThen(callable $fn): Option
    {
        return $this;
    }

    /**
     * 値を持っている場合、述語関数を満たすかチェックする
     *
     * @param callable(never): bool $predicate
     * @return Option<never>
     */
    public function filter(callable $predicate): Option
    {
        return $this;
    }

    /**
     * 値を持っていれば値を返し、持っていなければ例外をスローする
     *
     * @return never
     * @throws UnwrapException
     */
    public function unwrap(): mixed
    {
        throw new UnwrapException('None value');
    }

    /**
     * 値を持っていれば値を返し、持っていなければデフォルト値を返す
     *
     * @template U
     * @param U $default
     * @return U
     */
    public function unwrapOr(mixed $default): mixed
    {
        return $default;
    }

    /**
     * 値を持っていれば値を返し、持っていなければクロージャの結果を返す
     *
     * @template U
     * @param callable(): U $fn
     * @return U
     */
    public function unwrapOrElse(callable $fn): mixed
    {
        return $fn();
    }

    /**
     * 値を持っていれば値を返し、持っていなければ指定されたメッセージで例外をスローする
     *
     * @param string $message
     * @return never
     * @throws UnwrapException
     */
    public function expect(string $message): mixed
    {
        throw new UnwrapException($message);
    }

    /**
     * 値を検査し、副作用を実行する（値は変更しない）
     *
     * @param callable(never): void $fn
     * @return Option<never>
     */
    public function inspect(callable $fn): Option
    {
        return $this;
    }

    /**
     * Noneの場合に代替のOptionを返す（即座評価）
     *
     * @template U
     * @param Option<U> $opt
     * @return Option<U>
     */
    public function or(Option $opt): Option
    {
        return $opt;
    }

    /**
     * Noneの場合に代替のOptionを返す（遅延評価）
     *
     * @template U
     * @param callable(): Option<U> $fn
     * @return Option<U>
     */
    public function orElse(callable $fn): Option
    {
        return $fn();
    }

    /**
     * Someの場合に別のOptionを返し、Noneの場合は自身を返す（即座評価）
     *
     * @template U
     * @param Option<U> $opt
     * @return Option<never>
     */
    public function and(Option $opt): Option
    {
        return $this;
    }

    /**
     * Some値が指定された値を含むかどうかを確認する
     *
     * @param mixed $value 確認したい値
     * @return false
     */
    public function contains(mixed $value): bool
    {
        return false;
    }

    /**
     * Option<Result<T, E>> → Result<Option<T>, E> への変換
     *
     * @return Result<mixed, mixed>
     */
    public function transpose(): Result
    {
        // None → Ok(None)
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
        return Err::of($err);
    }

    /**
     * OptionをResultに変換する（Noneの場合はクロージャの結果でErr）
     *
     * @param callable $fn
     * @return Result<mixed, mixed>
     */
    public function okOrElse(callable $fn): Result
    {
        return Err::of($fn());
    }

    /**
     * ネストしたOptionを一段階平坦化する
     *
     * @return Option<mixed>
     */
    public function flatten(): Option
    {
        return $this;
    }

    /**
     * 排他的OR操作：片方のみSomeの場合にSome、両方Some/両方Noneの場合にNone
     *
     * @template U
     * @param Option<U> $opt
     * @return Option<U>
     */
    public function xor(Option $opt): Option
    {
        return $opt;
    }

    /**
     * 2つのOptionを結合：両方Someの場合にタプル、片方でもNoneの場合にNone
     *
     * @template U
     * @param Option<U> $opt
     * @return Option<never>
     */
    public function zip(Option $opt): Option
    {
        return $this;
    }
}