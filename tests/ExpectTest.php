<?php

declare(strict_types=1);

use ba0918\Result\Err;
use ba0918\Result\Exception\UnwrapException;
use ba0918\Result\Ok;
use PHPUnit\Framework\TestCase;

class ExpectTest extends TestCase
{
    public function testExpectOnOk(): void
    {
        $ok = Ok::of(10);
        $this->assertSame(10, $ok->expect('This should not fail'));
    }

    public function testExpectOnErrThrowsExceptionWithMessage(): void
    {
        $errorMessage = 'Something went wrong';
        $errorValue = 'test error';
        $this->expectException(UnwrapException::class);
        $this->expectExceptionMessage($errorMessage . ': ' . print_r($errorValue, true));

        $err = Err::of($errorValue);
        $err->expect($errorMessage);
    }
}
