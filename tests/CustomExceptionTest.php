<?php

declare(strict_types=1);

use ba0918\Result\Err;
use ba0918\Result\Exception\UnwrapException;
use ba0918\Result\Ok;
use PHPUnit\Framework\TestCase;

class CustomExceptionTest extends TestCase
{
    public function testUnwrapOnErrThrowsCustomException(): void
    {
        $errorValue = 'error';
        $this->expectException(UnwrapException::class);
        $this->expectExceptionMessage('Called unwrap() on an Err value: ' . print_r($errorValue, true));
        $err = Err::of($errorValue);
        $err->unwrap();
    }

    public function testUnwrapErrOnOkThrowsCustomException(): void
    {
        $successValue = 'success';
        $this->expectException(UnwrapException::class);
        $this->expectExceptionMessage('Called unwrapErr() on an Ok value: ' . print_r($successValue, true));
        $ok = Ok::of($successValue);
        $ok->unwrapErr();
    }
}
