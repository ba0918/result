<?php

declare(strict_types=1);

namespace ba0918\Result\Tests;

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
 * Integration tests for the Pipe operator API (requires PHP 8.5)
 *
 * These tests use the |> operator, so the file cannot be parsed by PHP 8.3/8.4.
 * It is excluded from the default phpunit suite and run via phpunit-php85.xml.dist.
 */
final class PipeIntegrationTest extends TestCase
{
    public function testPipeAppliesOperatorsLeftToRight(): void
    {
        $result = Ok::of(21)
            |> map(fn (int $v): int => $v * 2)
            |> andThen(fn (int $v): Result => Ok::of($v + 1));

        $this->assertSame(43, $result->unwrap(), 'the pipe chain should apply each operator left to right');
    }

    public function testPipeOperatorDoesNotShortCircuitTheOperatorsThemselves(): void
    {
        $mapExecuted = false;
        $andThenExecuted = false;
        $orElseExecuted = false;

        $result = Err::of('error message')
            |> map(function (int $v) use (&$mapExecuted): int {
                $mapExecuted = true;

                return $v;
            })
            |> andThen(function (int $v) use (&$andThenExecuted): Result {
                $andThenExecuted = true;

                return Ok::of($v);
            })
            |> orElse(function (string $e) use (&$orElseExecuted): Result {
                $orElseExecuted = true;

                return Ok::of(strlen($e));
            });

        $this->assertFalse($mapExecuted, 'the business callable of map() should be short-circuited on Err');
        $this->assertFalse($andThenExecuted, 'the business callable of andThen() should be short-circuited on Err');
        $this->assertTrue($orElseExecuted, 'the business callable of orElse() should execute on Err');
        $this->assertSame(13, $result->unwrap(), 'orElse() should recover with the computed value');
    }

    public function testRecoveryWithOkAllowsSubsequentAndThenToExecute(): void
    {
        $result = Ok::of(10)
            |> andThen(fn (int $v): Result => Ok::of($v * 2))
            |> orElse(fn (string $e): Result => Ok::of(0))
            |> andThen(fn (int $v): Result => Ok::of($v + 1));

        $this->assertSame(21, $result->unwrap(), 'andThen() after orElse() on an Ok chain should execute');
    }

    public function testRecoveryFromErrThenAndThenExecutes(): void
    {
        $result = Err::of('error message')
            |> orElse(fn (string $e): Result => Ok::of(42))
            |> andThen(fn (int $v): Result => Ok::of($v + 1));

        $this->assertSame(43, $result->unwrap(), 'andThen() should execute after orElse() recovers an Err');
    }

    public function testErrorTypeWideningThroughAndThenAndOrElse(): void
    {
        $result = Ok::of(5)
            |> andThen(fn (int $v): Result => Err::of($v))
            |> orElse(fn (int $e): Result => Ok::of($e * 2));

        $this->assertSame(10, $result->unwrap(), 'orElse() should handle the new error type from andThen()');
    }

    public function testMapErrTransformsErrorAndContinuesChain(): void
    {
        $result = Err::of('hello')
            |> mapErr(fn (string $e): int => strlen($e))
            |> orElse(fn (int $e): Result => Ok::of($e * 10));

        $this->assertSame(50, $result->unwrap(), 'mapErr() should transform the error for orElse()');
    }

    public function testInspectAndInspectErrInPipeChain(): void
    {
        $inspectedValues = [];
        $inspectedErrors = [];

        $okResult = Ok::of(42)
            |> map(fn (int $v): int => $v + 1)
            |> inspect(function (int $value) use (&$inspectedValues): void {
                $inspectedValues[] = $value;
            });

        $errResult = Err::of('error message')
            |> inspectErr(function (string $error) use (&$inspectedErrors): void {
                $inspectedErrors[] = $error;
            });

        $this->assertSame([43], $inspectedValues, 'inspect() should observe the Ok value');
        $this->assertSame(['error message'], $inspectedErrors, 'inspectErr() should observe the Err value');
        $this->assertSame(43, $okResult->unwrap(), 'inspect() should not change the value');
        $this->assertSame('error message', $errResult->unwrapErr(), 'inspectErr() should not change the error');
    }

    public function testFullRecoveryFlow(): void
    {
        $result = Ok::of(10)
            |> andThen(fn (int $v): Result => Err::of("failed at $v"))
            |> orElse(fn (string $e): Result => Ok::of(strlen($e)))
            |> andThen(fn (int $v): Result => Ok::of($v * 2));

        $this->assertSame(24, $result->unwrap(), 'the chain should fail, recover, and continue');
    }

    public function testMultipleRecoveriesInOneChain(): void
    {
        $result = Ok::of(2)
            |> andThen(fn (int $v): Result => Err::of('first failure'))
            |> orElse(fn (string $e): Result => Ok::of(strlen($e)))
            |> andThen(fn (int $v): Result => Err::of('second failure'))
            |> orElse(fn (string $e): Result => Ok::of($e . '!'))
            |> map(fn (string $v): string => strtoupper($v));

        $this->assertSame('SECOND FAILURE!', $result->unwrap(), 'each failure should be recoverable independently');
    }
}
