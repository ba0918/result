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
    // Tests for the ok() method
    public function testOkMethodOnOkReturnsOptionWithValue(): void
    {
        $ok = Ok::of(42);
        $option = $ok->ok();

        $this->assertInstanceOf(Some::class, $option);
        $this->assertTrue($option->isSome());
        $this->assertSame(42, $option->unwrap());
    }

    public function testOkMethodOnErrReturnsNone(): void
    {
        $err = Err::of('error');
        $option = $err->ok();

        $this->assertInstanceOf(None::class, $option);
        $this->assertTrue($option->isNone());
    }

    public function testOkMethodWithComplexValue(): void
    {
        $complexValue = ['key' => 'value', 'nested' => ['inner' => 123]];
        $ok = Ok::of($complexValue);
        $option = $ok->ok();

        $this->assertTrue($option->isSome());
        $this->assertSame($complexValue, $option->unwrap());
    }

    public function testOkMethodWithNullValue(): void
    {
        $ok = Ok::of(null);
        $option = $ok->ok();

        $this->assertTrue($option->isSome());
        /** @phpstan-ignore method.alreadyNarrowedType */
        $this->assertNull($option->unwrap());
    }

    // Tests for the err() method
    public function testErrMethodOnErrReturnsOptionWithError(): void
    {
        $err = Err::of('error message');
        $option = $err->err();

        $this->assertInstanceOf(Some::class, $option);
        $this->assertTrue($option->isSome());
        $this->assertSame('error message', $option->unwrap());
    }

    public function testErrMethodOnOkReturnsNone(): void
    {
        $ok = Ok::of(42);
        $option = $ok->err();

        $this->assertInstanceOf(None::class, $option);
        $this->assertTrue($option->isNone());
    }

    public function testErrMethodWithComplexError(): void
    {
        $complexError = new \Exception('Complex error');
        $err = Err::of($complexError);
        $option = $err->err();

        $this->assertTrue($option->isSome());
        $this->assertSame($complexError, $option->unwrap());
    }

    public function testErrMethodWithNullError(): void
    {
        $err = Err::of(null);
        $option = $err->err();

        $this->assertTrue($option->isSome());
        /** @phpstan-ignore method.alreadyNarrowedType */
        $this->assertNull($option->unwrap());
    }

    // Tests for the expectErr() method
    public function testExpectErrOnErrReturnsError(): void
    {
        $errorValue = 'test error';
        $err = Err::of($errorValue);

        $result = $err->expectErr('This should not fail');
        $this->assertSame($errorValue, $result);
    }

    public function testExpectErrOnOkThrowsExceptionWithMessage(): void
    {
        $message = 'Expected error but got Ok';
        $value = 42;
        $this->expectException(UnwrapException::class);
        $this->expectExceptionMessage($message . ': ' . print_r($value, true));

        $ok = Ok::of($value);
        $ok->expectErr($message);
    }

    public function testExpectErrWithComplexErrorValue(): void
    {
        $complexError = ['error' => 'details', 'code' => 500];
        $err = Err::of($complexError);

        $result = $err->expectErr('Should return complex error');
        $this->assertSame($complexError, $result);
    }

    public function testExpectErrOnOkWithComplexValueThrowsException(): void
    {
        $message = 'Expected error';
        $complexValue = ['data' => 'value'];
        $this->expectException(UnwrapException::class);
        $this->expectExceptionMessage($message . ': ' . print_r($complexValue, true));

        $ok = Ok::of($complexValue);
        $ok->expectErr($message);
    }

    // Integration tests
    public function testResultToOptionConversionChain(): void
    {
        // Chain conversion from an Ok value
        $ok = Ok::of(100);
        $okOption = $ok->ok();
        $errOption = $ok->err();

        $this->assertTrue($okOption->isSome());
        $this->assertSame(100, $okOption->unwrap());
        $this->assertTrue($errOption->isNone());

        // Chain conversion from an Err value
        $err = Err::of('failure');
        $okOption2 = $err->ok();
        $errOption2 = $err->err();

        $this->assertTrue($okOption2->isNone());
        $this->assertTrue($errOption2->isSome());
        $this->assertSame('failure', $errOption2->unwrap());
    }

    public function testExpectErrWithDifferentMessageFormats(): void
    {
        $err = Err::of('test');

        // Normal cases
        $this->assertSame('test', $err->expectErr(''));
        $this->assertSame('test', $err->expectErr('Custom message'));

        // Exception case with an Ok value
        $ok = Ok::of('value');

        $this->expectException(UnwrapException::class);
        $ok->expectErr('Custom error message');
    }
}
