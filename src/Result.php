<?php

namespace Mizumi\Result;

use Mizumi\Result\Exception\UnwrapException;

/**
 * 成功/失敗を表現する為の型
 *
 * @template T 成功時の値の型
 * @template E 失敗時のエラーの型
 */
interface Result
{
    /**
     * 成功しているか確認する
     *
     * @return bool
     */
    public function isOk(): bool;

    /**
     * 失敗しているか確認する
     *
     * @return bool
     */
    public function isErr(): bool;

    /**
     * 成功している場合、中の値に関数を適用する
     *
     * @template U
     * @param callable(T): U $fn
     * @return Result<U, E>
     */
    public function map(callable $fn): Result;

    /**
     * 失敗している場合、中のエラーに関数を適用する
     *
     * @template F
     * @param callable(E): F $fn
     * @return Result<T, F>
     */
    public function mapErr(callable $fn): Result;

    /**
     * 成功している場合、中の値に関数を適用し、その結果を返す
     *
     * @template U
     * @template F
     * @param callable(T): Result<U, F> $fn
     * @return Result<U, E|F>
     */
    public function andThen(callable $fn): Result;

    /**
     * 成功していれば値を返し、失敗していれば例外をスローする
     *
     * @return T
     * @throws UnwrapException
     */
    public function unwrap(): mixed;

    /**
     * 失敗していれば値を返し、成功していれば例外をスローする
     *
     * @return E
     * @throws UnwrapException
     */
    public function unwrapErr(): mixed;

    /**
     * 成功していれば値を返し、失敗していればデフォルト値を返す
     *
     * @template U
     * @param U $default
     * @return T|U
     */
    public function unwrapOr(mixed $default): mixed;

    /**
     * 成功していれば値を返し、失敗していればクロージャの結果を返す
     *
     * @template U
     * @param callable(E): U $fn
     * @return T|U
     */
    public function unwrapOrElse(callable $fn): mixed;

    /**
     * 成功していれば値を返し、失敗していれば指定されたメッセージで例外をスローする
     *
     * @param string $message
     * @return T
     * @throws UnwrapException
     */
    public function expect(string $message): mixed;

    /**
     * 成功値を検査し、副作用を実行する（値は変更しない）
     *
     * @param callable(T): void $fn
     * @return Result<T, E>
     */
    public function inspect(callable $fn): Result;

    /**
     * エラー値を検査し、副作用を実行する（エラーは変更しない）
     *
     * @param callable(E): void $fn
     * @return Result<T, E>
     */
    public function inspectErr(callable $fn): Result;

    /**
     * Errの場合に代替のResultを返す（即座評価）
     *
     * @template U
     * @template F
     * @param Result<U, F> $res
     * @return Result<T|U, F>
     */
    public function or(Result $res): Result;

    /**
     * Errの場合に代替のResultを返す（遅延評価）
     *
     * @template U
     * @template F
     * @param callable(E): Result<U, F> $fn
     * @return Result<T|U, F>
     */
    public function orElse(callable $fn): Result;

    /**
     * Okの場合に別のResultを返し、Errの場合は自身を返す（即座評価）
     *
     * @template U
     * @template F
     * @param Result<U, F> $res
     * @return Result<U, E|F>
     */
    public function and(Result $res): Result;

    /**
     * Ok値が指定された値を含むかどうかを確認する
     *
     * @param mixed $value 確認したい値
     * @return bool Ok値が指定値と厳密に等価な場合true、それ以外はfalse
     */
    public function contains(mixed $value): bool;

    /**
     * Err値が指定されたエラーを含むかどうかを確認する
     *
     * @param mixed $error 確認したいエラー値
     * @return bool Err値が指定エラーと厳密に等価な場合true、それ以外はfalse
     */
    public function containsErr(mixed $error): bool;

    /**
     * ネストしたResultを一段階平坦化する
     *
     * @return Result<T, E>
     */
    public function flatten(): Result;

    /**
     * Result<Option<T>, E> → Option<Result<T, E>> への変換
     *
     * @return Option<mixed>
     */
    public function transpose(): Option;
}
