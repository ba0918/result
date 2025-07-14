<?php

declare(strict_types=1);

use Mizumi\Result\Err;
use Mizumi\Result\Ok;
use PHPUnit\Framework\TestCase;

class OrMethodTest extends TestCase
{
    // or()メソッドのテスト

    public function testOrWithOkAndOk(): void
    {
        $first = new Ok(10);
        $second = new Ok(20);

        $result = $first->or($second);

        $this->assertTrue($result->isOk());
        $this->assertEquals(10, $result->unwrap());
    }

    public function testOrWithOkAndErr(): void
    {
        $ok = new Ok(10);
        $err = new Err('error');

        $result = $ok->or($err);

        $this->assertTrue($result->isOk());
        $this->assertEquals(10, $result->unwrap());
    }

    public function testOrWithErrAndOk(): void
    {
        $err = new Err('error');
        $ok = new Ok(20);

        $result = $err->or($ok);

        $this->assertTrue($result->isOk());
        $this->assertEquals(20, $result->unwrap());
    }

    public function testOrWithErrAndErr(): void
    {
        $firstErr = new Err('first error');
        $secondErr = new Err('second error');

        $result = $firstErr->or($secondErr);

        $this->assertTrue($result->isErr());
        $this->assertEquals('second error', $result->unwrapErr());
    }

    // orElse()メソッドのテスト

    public function testOrElseWithOk(): void
    {
        $ok = new Ok(10);
        $called = false;

        $result = $ok->orElse(function () use (&$called) {
            $called = true;

            return new Ok(99);
        });

        $this->assertTrue($result->isOk());
        $this->assertEquals(10, $result->unwrap());
        $this->assertFalse($called, 'Function should not be called for Ok values');
    }

    public function testOrElseWithErrReturningOk(): void
    {
        $err = new Err('original error');

        $result = $err->orElse(function ($error) {
            $this->assertEquals('original error', $error);

            return new Ok(42);
        });

        $this->assertTrue($result->isOk());
        $this->assertEquals(42, $result->unwrap());
    }

    public function testOrElseWithErrReturningErr(): void
    {
        $err = new Err('original error');

        $result = $err->orElse(function ($error) {
            return new Err('transformed: ' . $error);
        });

        $this->assertTrue($result->isErr());
        $this->assertEquals('transformed: original error', $result->unwrapErr());
    }

    public function testOrElseErrorValuePassedCorrectly(): void
    {
        $originalError = ['code' => 404, 'message' => 'Not found'];
        $err = new Err($originalError);

        $result = $err->orElse(function ($error) use ($originalError) {
            $this->assertEquals($originalError, $error);

            return new Ok('recovered');
        });

        $this->assertTrue($result->isOk());
        $this->assertEquals('recovered', $result->unwrap());
    }

    // チェーンテスト

    public function testOrChaining(): void
    {
        $result = (new Err('first'))
            ->or(new Err('second'))
            ->or(new Ok('success'));

        $this->assertTrue($result->isOk());
        $this->assertEquals('success', $result->unwrap());
    }

    public function testOrElseChaining(): void
    {
        $result = (new Err('first'))
            ->orElse(fn ($e) => new Err('second: ' . $e))
            ->orElse(fn ($e) => new Ok('recovered from: ' . $e));

        $this->assertTrue($result->isOk());
        $this->assertEquals('recovered from: second: first', $result->unwrap());
    }

    // 型の異なるエラーとの組み合わせテスト

    public function testOrWithDifferentErrorTypes(): void
    {
        $stringErr = new Err('string error');
        $intErr = new Err(404);

        $result = $stringErr->or($intErr);

        $this->assertTrue($result->isErr());
        $this->assertEquals(404, $result->unwrapErr());
    }

    public function testOrElseWithDifferentErrorTypes(): void
    {
        $stringErr = new Err('string error');

        $result = $stringErr->orElse(function () {
            return new Err(500); // 異なる型のエラーを返す
        });

        $this->assertTrue($result->isErr());
        $this->assertEquals(500, $result->unwrapErr());
    }
}
