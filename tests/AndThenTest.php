<?php

declare(strict_types=1);

use ba0918\Result\Err;
use ba0918\Result\Ok;
use ba0918\Result\Result;
use PHPUnit\Framework\TestCase;

/**
 * @param float $a
 * @param float $b
 *
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
        $single = (new Ok(10.0))
            ->andThen(fn (float $x) => safe_divide($x, 2.0));

        $this->assertTrue($single->isOk());
        $this->assertSame(5.0, $single->unwrap());

        $chained = (new Ok(10.0))
            ->andThen(fn (float $x) => safe_divide($x, 2.0)) // 10 / 2 = 5
            ->andThen(fn (float $x) => safe_divide($x, 5.0)); // 5 / 5 = 1

        $this->assertTrue($chained->isOk());
        $this->assertSame(1.0, $chained->unwrap());
    }

    public function testAndThenWithFailure(): void
    {
        $executionCount = 0;
        $result = (new Ok(10))
            ->andThen(function ($x) use (&$executionCount): Result {
                $executionCount++;

                return safe_divide($x, 0);
            }) // Fails here
            ->andThen(function ($x) use (&$executionCount): Result {
                $executionCount++;

                return safe_divide($x, 5); // This is not executed
            });

        $this->assertTrue($result->isErr());
        $this->assertSame('Division by zero', $result->unwrapErr());
        $this->assertSame(1, $executionCount, 'andThen after failure must not be executed');
    }

    public function testAndThenOnErr(): void
    {
        $executionCount = 0;
        $result = (new Err('Initial error'))
            ->andThen(function ($x) use (&$executionCount): Result {
                $executionCount++;

                return safe_divide($x, 5); // This is not executed
            });

        $this->assertTrue($result->isErr());
        $this->assertSame('Initial error', $result->unwrapErr());
        $this->assertSame(0, $executionCount, 'andThen on Err must not be executed');
    }
}
