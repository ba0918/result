<?php

declare(strict_types=1);

use ba0918\Result\Err;
use ba0918\Result\Ok;
use PHPUnit\Framework\TestCase;

class OrMethodTest extends TestCase
{
    // Tests for the or() method

    public function testOrWithOkAndOk(): void
    {
        $first = Ok::of(10);
        $second = Ok::of(20);

        $result = $first->or($second);

        $this->assertTrue($result->isOk());
        $this->assertSame(10, $result->unwrap());
    }

    public function testOrWithOkAndErr(): void
    {
        $ok = Ok::of(10);
        $err = Err::of('error');

        $result = $ok->or($err);

        $this->assertTrue($result->isOk());
        $this->assertSame(10, $result->unwrap());
    }

    public function testOrWithErrAndOk(): void
    {
        $err = Err::of('error');
        $ok = Ok::of(20);

        $result = $err->or($ok);

        $this->assertTrue($result->isOk());
        $this->assertSame(20, $result->unwrap());
    }

    public function testOrWithErrAndErr(): void
    {
        $firstErr = Err::of('first error');
        $secondErr = Err::of('second error');

        $result = $firstErr->or($secondErr);

        $this->assertTrue($result->isErr());
        $this->assertSame('second error', $result->unwrapErr());
    }

    // Tests for the orElse() method

    public function testOrElseWithOk(): void
    {
        $ok = Ok::of(10);
        $called = false;

        $result = $ok->orElse(function () use (&$called) {
            $called = true;

            return Ok::of(99);
        });

        $this->assertTrue($result->isOk());
        $this->assertSame(10, $result->unwrap());
        $this->assertFalse($called, 'Function should not be called for Ok values');
    }

    public function testOrElseWithErrReturningOk(): void
    {
        $err = Err::of('original error');

        $result = $err->orElse(function ($error) {
            $this->assertSame('original error', $error);

            return Ok::of(42);
        });

        $this->assertTrue($result->isOk());
        $this->assertSame(42, $result->unwrap());
    }

    public function testOrElseWithErrReturningErr(): void
    {
        $err = Err::of('original error');

        $result = $err->orElse(function ($error) {
            return Err::of('transformed: ' . $error);
        });

        $this->assertTrue($result->isErr());
        $this->assertSame('transformed: original error', $result->unwrapErr());
    }

    public function testOrElseErrorValuePassedCorrectly(): void
    {
        $originalError = ['code' => 404, 'message' => 'Not found'];
        $err = Err::of($originalError);

        $result = $err->orElse(function ($error) use ($originalError) {
            $this->assertSame($originalError, $error);

            return Ok::of('recovered');
        });

        $this->assertTrue($result->isOk());
        $this->assertSame('recovered', $result->unwrap());
    }

    // Chain tests

    public function testOrChaining(): void
    {
        $result = (Err::of('first'))
            ->or(Err::of('second'))
            ->or(Ok::of('success'));

        $this->assertTrue($result->isOk());
        $this->assertSame('success', $result->unwrap());
    }

    public function testOrElseChaining(): void
    {
        $result = (Err::of('first'))
            ->orElse(fn ($e) => Err::of('second: ' . $e))
            ->orElse(fn ($e) => Ok::of('recovered from: ' . $e));

        $this->assertTrue($result->isOk());
        $this->assertSame('recovered from: second: first', $result->unwrap());
    }

    // Tests with different error types combined

    public function testOrWithDifferentErrorTypes(): void
    {
        $stringErr = Err::of('string error');
        $intErr = Err::of(404);

        $result = $stringErr->or($intErr);

        $this->assertTrue($result->isErr());
        $this->assertSame(404, $result->unwrapErr());
    }

    public function testOrElseWithDifferentErrorTypes(): void
    {
        $stringErr = Err::of('string error');

        $result = $stringErr->orElse(function () {
            return Err::of(500); // returns a different error type
        });

        $this->assertTrue($result->isErr());
        $this->assertSame(500, $result->unwrapErr());
    }
}
