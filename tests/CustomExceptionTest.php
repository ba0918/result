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
        $this->expectException(UnwrapException::class);
        $err = new Err('error');
        $err->unwrap();
    }

    public function testUnwrapErrOnOkThrowsCustomException(): void
    {
        $this->expectException(UnwrapException::class);
        $ok = new Ok('success');
        $ok->unwrapErr();
    }
}
