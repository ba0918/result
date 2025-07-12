<?php

use Mizumi\Result\Ok;
use Mizumi\Result\Err;
use Mizumi\Result\Exception\UnwrapException;
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
