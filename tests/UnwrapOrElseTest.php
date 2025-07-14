<?php

declare(strict_types=1);

use Mizumi\Result\Err;
use Mizumi\Result\Ok;
use PHPUnit\Framework\TestCase;

class UnwrapOrElseTest extends TestCase
{
    public function testUnwrapOrElseOnOk(): void
    {
        $result = new Ok(10);
        $value = $result->unwrapOrElse(fn ($err) => strlen($err));

        $this->assertEquals(10, $value);
    }

    public function testUnwrapOrElseOnErr(): void
    {
        $result = new Err('some error');
        $value = $result->unwrapOrElse(fn ($err) => strlen($err));

        // 'some error' has 10 characters
        $this->assertEquals(10, $value);
    }

    public function testUnwrapOrElseDoesNotExecuteClosureOnOk(): void
    {
        $closureExecuted = false;
        $result = new Ok(5);
        $value = $result->unwrapOrElse(function ($err) use (&$closureExecuted) {
            $closureExecuted = true;

            return strlen($err);
        });

        $this->assertEquals(5, $value);
        $this->assertFalse($closureExecuted, 'Closure should not be executed for Ok');
    }
}
