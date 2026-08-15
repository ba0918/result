<?php

declare(strict_types=1);

use ba0918\Result\Err;
use ba0918\Result\Ok;
use ba0918\Result\Result;
use PHPUnit\Framework\TestCase;

use function ba0918\Result\Pipe\andThen;
use function ba0918\Result\Pipe\inspect;
use function ba0918\Result\Pipe\inspectErr;
use function ba0918\Result\Pipe\map;
use function ba0918\Result\Pipe\mapErr;
use function ba0918\Result\Pipe\orElse;

/**
 * Tests for the Pipe operator adapter functions applied directly (without the |> operator)
 *
 * These functions return Closures, so PHP 8.3/8.4 users can apply them
 * without the PHP 8.5 pipe operator: map($fn)($result).
 */
class PipeFunctionsTest extends TestCase
{
    // map

    public function testMapReturnsClosure(): void
    {
        $operator = map(fn (int $v): int => $v * 2);

        $this->assertInstanceOf(Closure::class, $operator, 'map() should return a Closure');
    }

    public function testMapAppliesFunctionOnOk(): void
    {
        $result = map(fn (int $v): int => $v * 2)(Ok::of(21));

        $this->assertTrue($result->isOk(), 'map() should keep the result successful');
        $this->assertSame(42, $result->unwrap(), 'map() should apply the function to the Ok value');
    }

    public function testMapPassesThroughErr(): void
    {
        $err = Err::of('error message');

        $result = map(fn (int $v): int => $v * 2)($err);

        $this->assertSame($err, $result, 'map() should return the same Err instance');
    }

    // mapErr

    public function testMapErrAppliesFunctionOnErr(): void
    {
        $result = mapErr(fn (string $e): int => strlen($e))(Err::of('hello'));

        $this->assertTrue($result->isErr(), 'mapErr() should keep the result failed');
        $this->assertSame(5, $result->unwrapErr(), 'mapErr() should apply the function to the Err value');
    }

    public function testMapErrPassesThroughOk(): void
    {
        $ok = Ok::of(42);

        $result = mapErr(fn (string $e): int => strlen($e))($ok);

        $this->assertSame($ok, $result, 'mapErr() should return the same Ok instance');
    }

    // andThen

    public function testAndThenAppliesOperationOnOk(): void
    {
        $result = andThen(fn (int $v): Result => Ok::of($v * 2))(Ok::of(21));

        $this->assertTrue($result->isOk(), 'andThen() should keep the result successful');
        $this->assertSame(42, $result->unwrap(), 'andThen() should apply the operation to the Ok value');
    }

    public function testAndThenDoesNotExecuteOperationOnErr(): void
    {
        $executed = false;
        $err = Err::of('error message');

        $result = andThen(function (int $v) use (&$executed): Result {
            $executed = true;

            return Ok::of($v);
        })($err);

        $this->assertFalse($executed, 'andThen() should not execute the operation on Err');
        $this->assertSame($err, $result, 'andThen() should return the same Err instance');
    }

    // orElse

    public function testOrElseAppliesOperationOnErr(): void
    {
        $result = orElse(fn (string $e): Result => Ok::of(strlen($e)))(Err::of('hello'));

        $this->assertTrue($result->isOk(), 'orElse() should recover the result');
        $this->assertSame(5, $result->unwrap(), 'orElse() should apply the operation to the Err value');
    }

    public function testOrElseDoesNotExecuteOperationOnOk(): void
    {
        $executed = false;
        $ok = Ok::of(42);

        $result = orElse(function (string $e) use (&$executed): Result {
            $executed = true;

            return Ok::of(strlen($e));
        })($ok);

        $this->assertFalse($executed, 'orElse() should not execute the operation on Ok');
        $this->assertSame($ok, $result, 'orElse() should return the same Ok instance');
    }

    // inspect

    public function testInspectExecutesSideEffectOnOk(): void
    {
        $inspectedValues = [];
        $ok = Ok::of(42);

        $result = inspect(function (int $value) use (&$inspectedValues): void {
            $inspectedValues[] = $value;
        })($ok);

        $this->assertSame([42], $inspectedValues, 'inspect() should execute the function on Ok');
        $this->assertSame($ok, $result, 'inspect() should return the same Ok instance');
    }

    public function testInspectDoesNotExecuteOnErr(): void
    {
        $executed = false;
        $err = Err::of('error message');

        $result = inspect(function (int $value) use (&$executed): void {
            $executed = true;
        })($err);

        $this->assertFalse($executed, 'inspect() should not execute the function on Err');
        $this->assertSame($err, $result, 'inspect() should return the same Err instance');
    }

    // inspectErr

    public function testInspectErrExecutesSideEffectOnErr(): void
    {
        $inspectedErrors = [];
        $err = Err::of('error message');

        $result = inspectErr(function (string $error) use (&$inspectedErrors): void {
            $inspectedErrors[] = $error;
        })($err);

        $this->assertSame(['error message'], $inspectedErrors, 'inspectErr() should execute the function on Err');
        $this->assertSame($err, $result, 'inspectErr() should return the same Err instance');
    }

    public function testInspectErrDoesNotExecuteOnOk(): void
    {
        $executed = false;
        $ok = Ok::of(42);

        $result = inspectErr(function (string $error) use (&$executed): void {
            $executed = true;
        })($ok);

        $this->assertFalse($executed, 'inspectErr() should not execute the function on Ok');
        $this->assertSame($ok, $result, 'inspectErr() should return the same Ok instance');
    }

    // chain semantics

    public function testChainShortCircuitsBusinessCallablesOnErr(): void
    {
        $andThenExecuted = false;
        $orElseExecuted = false;

        $result = andThen(function (int $v) use (&$andThenExecuted): Result {
            $andThenExecuted = true;

            return Ok::of($v);
        })(Err::of('error message'));

        $result = orElse(function (string $e) use (&$orElseExecuted): Result {
            $orElseExecuted = true;

            return Ok::of(strlen($e));
        })($result);

        $this->assertFalse($andThenExecuted, 'business callable of andThen() should be short-circuited on Err');
        $this->assertTrue($orElseExecuted, 'business callable of orElse() should execute on Err');
        $this->assertSame(13, $result->unwrap(), 'orElse() should recover with the computed value');
    }

    public function testRecoveryWithOkAllowsSubsequentAndThenToExecute(): void
    {
        $result = andThen(fn (int $v): Result => Ok::of($v * 2))(Ok::of(10));
        $result = orElse(fn (string $e): Result => Ok::of(0))($result);
        $result = andThen(fn (int $v): Result => Ok::of($v + 1))($result);

        $this->assertSame(21, $result->unwrap(), 'andThen() after orElse() recovery should execute');
    }

    public function testRecoveryFromErrThenAndThenExecutes(): void
    {
        $result = orElse(fn (string $e): Result => Ok::of(42))(Err::of('error message'));
        $result = andThen(fn (int $v): Result => Ok::of($v + 1))($result);

        $this->assertSame(43, $result->unwrap(), 'andThen() should execute after orElse() recovers an Err');
    }

    public function testChainComposesMultipleTransformations(): void
    {
        $result = map(fn (int $v): int => $v * 2)(Ok::of(21));
        $result = map(fn (int $v): int => $v + 1)($result);
        $result = andThen(fn (int $v): Result => Ok::of($v * 10))($result);

        $this->assertSame(430, $result->unwrap(), 'the composed chain should transform the value');
    }

    public function testErrorTypeWideningThroughAndThenAndOrElse(): void
    {
        $result = andThen(fn (int $v): Result => Err::of($v))(Ok::of(5));
        $result = orElse(fn (int $e): Result => Ok::of($e * 2))($result);

        $this->assertSame(10, $result->unwrap(), 'orElse() should handle the new error type from andThen()');
    }

    public function testInspectInChainDoesNotDisturbValueFlow(): void
    {
        $inspectedValues = [];

        $result = map(fn (int $v): int => $v * 2)(Ok::of(21));
        $result = inspect(function (int $value) use (&$inspectedValues): void {
            $inspectedValues[] = $value;
        })($result);
        $result = map(fn (int $v): int => $v + 1)($result);

        $this->assertSame([42], $inspectedValues, 'inspect() should observe the value between transformations');
        $this->assertSame(43, $result->unwrap(), 'inspect() should not change the value');
    }
}
