<?php declare(strict_types=1);

namespace Mizumi\Result;

use Mizumi\Result\Exception\UnwrapException;

/**
 * 値の有無を表現する為の型
 *
 * @template T 値の型
 */
interface Option
{
    /**
     * 値を持っているか確認する
     *
     * @return bool
     */
    public function isSome(): bool;

    /**
     * 値を持っていないか確認する
     *
     * @return bool
     */
    public function isNone(): bool;

    /**
     * 値を持っている場合に述語関数で値を検証する
     *
     * @param callable(T): bool $predicate
     * @return bool
     */
    public function isSomeAnd(callable $predicate): bool;

    /**
     * 値を持っている場合、中の値に関数を適用する
     *
     * @template U
     * @param callable(T): U $fn
     * @return Option<U>
     */
    public function map(callable $fn): Option;

    /**
     * 値を持っている場合は関数を適用し、持っていない場合はデフォルト値を返す
     *
     * @template U
     * @param callable(T): U $fn
     * @param U $default
     * @return U
     */
    public function mapOr(callable $fn, mixed $default): mixed;

    /**
     * 値を持っている場合は関数を適用し、持っていない場合はクロージャの結果を返す
     *
     * @template U
     * @param callable(T): U $fn
     * @param callable(): U $defaultFn
     * @return U
     */
    public function mapOrElse(callable $fn, callable $defaultFn): mixed;

    /**
     * 値を持っている場合、中の値に関数を適用し、その結果を返す
     *
     * @template U
     * @param callable(T): Option<U> $fn
     * @return Option<U>
     */
    public function andThen(callable $fn): Option;

    /**
     * 値を持っている場合、述語関数を満たすかチェックする
     *
     * @param callable(T): bool $predicate
     * @return Option<T>
     */
    public function filter(callable $predicate): Option;

    /**
     * 値を持っていれば値を返し、持っていなければ例外をスローする
     *
     * @return T
     * @throws UnwrapException
     */
    public function unwrap(): mixed;

    /**
     * 値を持っていれば値を返し、持っていなければデフォルト値を返す
     *
     * @template U
     * @param U $default
     * @return T|U
     */
    public function unwrapOr(mixed $default): mixed;

    /**
     * 値を持っていれば値を返し、持っていなければクロージャの結果を返す
     *
     * @template U
     * @param callable(): U $fn
     * @return T|U
     */
    public function unwrapOrElse(callable $fn): mixed;

    /**
     * 値を持っていれば値を返し、持っていなければ指定されたメッセージで例外をスローする
     *
     * @param string $message
     * @return T
     * @throws UnwrapException
     */
    public function expect(string $message): mixed;

    /**
     * 値を検査し、副作用を実行する（値は変更しない）
     *
     * @param callable(T): void $fn
     * @return Option<T>
     */
    public function inspect(callable $fn): Option;

    /**
     * Noneの場合に代替のOptionを返す（即座評価）
     *
     * @template U
     * @param Option<U> $opt
     * @return Option<T|U>
     */
    public function or(Option $opt): Option;

    /**
     * Noneの場合に代替のOptionを返す（遅延評価）
     *
     * @template U
     * @param callable(): Option<U> $fn
     * @return Option<T|U>
     */
    public function orElse(callable $fn): Option;

    /**
     * Someの場合に別のOptionを返し、Noneの場合は自身を返す（即座評価）
     *
     * @template U
     * @param Option<U> $opt
     * @return Option<U>
     */
    public function and(Option $opt): Option;

    /**
     * Some値が指定された値を含むかどうかを確認する
     *
     * @param mixed $value 確認したい値
     * @return bool Some値が指定値と厳密に等価な場合true、それ以外はfalse
     */
    public function contains(mixed $value): bool;

    /**
     * Option<Result<T, E>> → Result<Option<T>, E> への変換
     *
     * @return Result<mixed, mixed>
     */
    public function transpose(): Result;

    /**
     * OptionをResultに変換する（Noneの場合は指定されたエラーでErr）
     *
     * @param mixed $err
     * @return Result<mixed, mixed>
     */
    public function okOr(mixed $err): Result;

    /**
     * OptionをResultに変換する（Noneの場合はクロージャの結果でErr）
     *
     * @param callable $fn
     * @return Result<mixed, mixed>
     */
    public function okOrElse(callable $fn): Result;

    /**
     * ネストしたOptionを一段階平坦化する
     *
     * @return Option<mixed>
     */
    public function flatten(): Option;

    /**
     * 排他的OR操作：片方のみSomeの場合にSome、両方Some/両方Noneの場合にNone
     *
     * @template U
     * @param Option<U> $opt
     * @return Option<T|U>
     */
    public function xor(Option $opt): Option;

    /**
     * 2つのOptionを結合：両方Someの場合にタプル、片方でもNoneの場合にNone
     *
     * @template U
     * @param Option<U> $opt
     * @return Option<array{T, U}>
     */
    public function zip(Option $opt): Option;
}