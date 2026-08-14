<?php

declare(strict_types=1);

use ba0918\Result\Err;
use ba0918\Result\Ok;
use PHPUnit\Framework\TestCase;

class InspectTest extends TestCase
{
    public function testInspectOnOkExecutesFunction(): void
    {
        $sideEffectExecuted = false;
        $inspectedValue = null;

        $result = new Ok(42);
        $returnedResult = $result->inspect(function ($value) use (&$sideEffectExecuted, &$inspectedValue): void {
            $sideEffectExecuted = true;
            $inspectedValue = $value;
        });

        $this->assertTrue($sideEffectExecuted, 'inspect() should execute function on Ok');
        $this->assertSame(42, $inspectedValue, 'inspect() should pass the value to the function');
        $this->assertSame($result, $returnedResult, 'inspect() should return the same instance');
    }

    public function testInspectOnErrDoesNotExecute(): void
    {
        $sideEffectExecuted = false;

        $result = new Err('error message');
        $returnedResult = $result->inspect(function ($value) use (&$sideEffectExecuted): void {
            $sideEffectExecuted = true;
        });

        $this->assertFalse($sideEffectExecuted, 'inspect() should not execute function on Err');
        $this->assertSame($result, $returnedResult, 'inspect() should return the same instance');
    }

    public function testInspectErrOnOkDoesNotExecute(): void
    {
        $sideEffectExecuted = false;

        $result = new Ok(42);
        $returnedResult = $result->inspectErr(function ($error) use (&$sideEffectExecuted): void {
            $sideEffectExecuted = true;
        });

        $this->assertFalse($sideEffectExecuted, 'inspectErr() should not execute function on Ok');
        $this->assertSame($result, $returnedResult, 'inspectErr() should return the same instance');
    }

    public function testInspectErrOnErrExecutesFunction(): void
    {
        $sideEffectExecuted = false;
        $inspectedError = null;

        $result = new Err('error message');
        $returnedResult = $result->inspectErr(function ($error) use (&$sideEffectExecuted, &$inspectedError): void {
            $sideEffectExecuted = true;
            $inspectedError = $error;
        });

        $this->assertTrue($sideEffectExecuted, 'inspectErr() should execute function on Err');
        $this->assertSame('error message', $inspectedError, 'inspectErr() should pass the error to the function');
        $this->assertSame($result, $returnedResult, 'inspectErr() should return the same instance');
    }

    public function testInspectReturnsOriginalResult(): void
    {
        $okResult = new Ok(100);
        $errResult = new Err('test error');

        $okInspected = $okResult->inspect(fn ($value) => null);
        $errInspected = $errResult->inspect(fn ($value) => null);

        $this->assertSame($okResult, $okInspected, 'inspect() should return the same Ok instance');
        $this->assertSame($errResult, $errInspected, 'inspect() should return the same Err instance');
    }

    public function testInspectErrReturnsOriginalResult(): void
    {
        $okResult = new Ok(100);
        $errResult = new Err('test error');

        $okInspected = $okResult->inspectErr(fn ($error) => null);
        $errInspected = $errResult->inspectErr(fn ($error) => null);

        $this->assertSame($okResult, $okInspected, 'inspectErr() should return the same Ok instance');
        $this->assertSame($errResult, $errInspected, 'inspectErr() should return the same Err instance');
    }

    public function testInspectInMethodChain(): void
    {
        $inspectedValues = [];

        $result = (new Ok(10))
            ->map(fn ($x) => $x * 2)
            ->inspect(function ($value) use (&$inspectedValues): void {
                $inspectedValues[] = $value;
            })
            ->map(fn ($x) => $x + 5)
            ->inspect(function ($value) use (&$inspectedValues): void {
                $inspectedValues[] = $value;
            });

        $this->assertSame([20, 25], $inspectedValues, 'inspect() should work correctly in method chains');
        $this->assertSame(25, $result->unwrap(), 'Method chain should continue normally after inspect()');
    }

    public function testInspectDoesNotModifyValue(): void
    {
        $originalValue = 'original';
        $result = new Ok($originalValue);

        $result->inspect(function ($value): void {
            // Confirm that attempting to change the value has no effect
            $value = 'modified';
        });

        $this->assertSame($originalValue, $result->unwrap(), 'inspect() should not modify the original value');
    }

    public function testInspectErrDoesNotModifyError(): void
    {
        $originalError = 'original error';
        $result = new Err($originalError);

        $result->inspectErr(function ($error): void {
            // Confirm that attempting to change the error has no effect
            $error = 'modified error';
        });

        $this->assertSame($originalError, $result->unwrapErr(), 'inspectErr() should not modify the original error');
    }

    public function testInspectSideEffectExecution(): void
    {
        $log = [];

        (new Ok('test value'))->inspect(function ($value) use (&$log): void {
            $log[] = "Inspected value: $value";
        });

        (new Err('test error'))->inspectErr(function ($error) use (&$log): void {
            $log[] = "Inspected error: $error";
        });

        $expectedLog = [
            'Inspected value: test value',
            'Inspected error: test error',
        ];

        $this->assertSame($expectedLog, $log, 'inspect methods should execute side effects correctly');
    }
}
