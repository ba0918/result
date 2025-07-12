<?php

use Mizumi\Result\Ok;
use Mizumi\Result\Err;
use Mizumi\Result\Exception\UnwrapException;
use PHPUnit\Framework\TestCase;

class ExpectTest extends TestCase
{
    public function testExpectOnOk(): void
    {
        $ok = new Ok(10);
        $this->assertEquals(10, $ok->expect('This should not fail'));
    }

    public function testExpectOnErrThrowsExceptionWithMessage(): void
    {
        $errorMessage = 'Something went wrong';
        $errorValue = 'test error';
        $this->expectException(UnwrapException::class);
        $this->expectExceptionMessage($errorMessage . ': ' . print_r($errorValue, true));

        $err = new Err($errorValue);
        $err->expect($errorMessage);
    }
}
