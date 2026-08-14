<?php

declare(strict_types=1);

use ba0918\Result\Err;
use ba0918\Result\Exception\UnwrapException;
use ba0918\Result\None;
use ba0918\Result\Ok;
use ba0918\Result\Some;
use PHPUnit\Framework\TestCase;

class ResultConversionTest extends TestCase
{
    // ok()メソッドのテスト
    public function testOkMethodOnOkReturnsOptionWithValue(): void
    {
        $ok = new Ok(42);
        $option = $ok->ok();

        $this->assertInstanceOf(Some::class, $option);
        $this->assertTrue($option->isSome());
        $this->assertEquals(42, $option->unwrap());
    }

    public function testOkMethodOnErrReturnsNone(): void
    {
        $err = new Err('error');
        $option = $err->ok();

        $this->assertInstanceOf(None::class, $option);
        $this->assertTrue($option->isNone());
    }

    public function testOkMethodWithComplexValue(): void
    {
        $complexValue = ['key' => 'value', 'nested' => ['inner' => 123]];
        $ok = new Ok($complexValue);
        $option = $ok->ok();

        $this->assertTrue($option->isSome());
        $this->assertEquals($complexValue, $option->unwrap());
    }

    public function testOkMethodWithNullValue(): void
    {
        $ok = new Ok(null);
        $option = $ok->ok();

        $this->assertTrue($option->isSome());
        /** @phpstan-ignore method.alreadyNarrowedType */
        $this->assertNull($option->unwrap());
    }

    // err()メソッドのテスト
    public function testErrMethodOnErrReturnsOptionWithError(): void
    {
        $err = new Err('error message');
        $option = $err->err();

        $this->assertInstanceOf(Some::class, $option);
        $this->assertTrue($option->isSome());
        $this->assertEquals('error message', $option->unwrap());
    }

    public function testErrMethodOnOkReturnsNone(): void
    {
        $ok = new Ok(42);
        $option = $ok->err();

        $this->assertInstanceOf(None::class, $option);
        $this->assertTrue($option->isNone());
    }

    public function testErrMethodWithComplexError(): void
    {
        $complexError = new \Exception('Complex error');
        $err = new Err($complexError);
        $option = $err->err();

        $this->assertTrue($option->isSome());
        $this->assertSame($complexError, $option->unwrap());
    }

    public function testErrMethodWithNullError(): void
    {
        $err = new Err(null);
        $option = $err->err();

        $this->assertTrue($option->isSome());
        /** @phpstan-ignore method.alreadyNarrowedType */
        $this->assertNull($option->unwrap());
    }

    // expectErr()メソッドのテスト
    public function testExpectErrOnErrReturnsError(): void
    {
        $errorValue = 'test error';
        $err = new Err($errorValue);

        $result = $err->expectErr('This should not fail');
        $this->assertEquals($errorValue, $result);
    }

    public function testExpectErrOnOkThrowsExceptionWithMessage(): void
    {
        $message = 'Expected error but got Ok';
        $value = 42;
        $this->expectException(UnwrapException::class);
        $this->expectExceptionMessage($message . ': ' . print_r($value, true));

        $ok = new Ok($value);
        $ok->expectErr($message);
    }

    public function testExpectErrWithComplexErrorValue(): void
    {
        $complexError = ['error' => 'details', 'code' => 500];
        $err = new Err($complexError);

        $result = $err->expectErr('Should return complex error');
        $this->assertEquals($complexError, $result);
    }

    public function testExpectErrOnOkWithComplexValueThrowsException(): void
    {
        $message = 'Expected error';
        $complexValue = ['data' => 'value'];
        $this->expectException(UnwrapException::class);
        $this->expectExceptionMessage($message . ': ' . print_r($complexValue, true));

        $ok = new Ok($complexValue);
        $ok->expectErr($message);
    }

    // 統合テスト
    public function testResultToOptionConversionChain(): void
    {
        // Ok値からのチェーン変換
        $ok = new Ok(100);
        $okOption = $ok->ok();
        $errOption = $ok->err();

        $this->assertTrue($okOption->isSome());
        $this->assertEquals(100, $okOption->unwrap());
        $this->assertTrue($errOption->isNone());

        // Err値からのチェーン変換
        $err = new Err('failure');
        $okOption2 = $err->ok();
        $errOption2 = $err->err();

        $this->assertTrue($okOption2->isNone());
        $this->assertTrue($errOption2->isSome());
        $this->assertEquals('failure', $errOption2->unwrap());
    }

    public function testExpectErrWithDifferentMessageFormats(): void
    {
        $err = new Err('test');

        // 正常系
        $this->assertEquals('test', $err->expectErr(''));
        $this->assertEquals('test', $err->expectErr('Custom message'));

        // Ok値での例外系
        $ok = new Ok('value');

        $this->expectException(UnwrapException::class);
        $ok->expectErr('Custom error message');
    }
}
