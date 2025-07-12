<?php

use Mizumi\Result\Ok;
use Mizumi\Result\Err;
use Mizumi\Result\Result;
use PHPUnit\Framework\TestCase;

/**
 * @param float $a
 * @param float $b
 * @return Result<float, string>
 */
function safe_divide(float $a, float $b): Result
{
    if ($b === 0.0) {
        return new Err('Division by zero');
    }
    return new Ok($a / $b);
}

class AndThenTest extends TestCase
{
    public function testAndThenWithSuccess(): void
    {
        $result = (new Ok(10.0))
            ->andThen(fn(float $x) => safe_divide($x, 2.0));

        $result = (new Ok(10))
            ->andThen(fn($x) => safe_divide($x, 2)) // 10 / 2 = 5
            ->andThen(fn($x) => safe_divide($x, 5)); // 5 / 5 = 1

        $this->assertTrue($result->isOk());
        $this->assertEquals(1, $result->unwrap());
    }

    public function testAndThenWithFailure(): void
    {
        $result = (new Ok(10))
            ->andThen(fn($x) => safe_divide($x, 0)) // Fails here
            ->andThen(fn($x) => safe_divide($x, 5)); // This is not executed

        $this->assertTrue($result->isErr());
        $this->assertEquals('Division by zero', $result->unwrapErr());
    }

    public function testAndThenOnErr(): void
    {
        $result = (new Err('Initial error'))
            ->andThen(fn($x) => safe_divide($x, 5)); // This is not executed

        $this->assertTrue($result->isErr());
        $this->assertEquals('Initial error', $result->unwrapErr());
    }
}
