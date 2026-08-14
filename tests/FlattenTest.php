<?php

declare(strict_types=1);

use ba0918\Result\Err;
use ba0918\Result\Ok;
use PHPUnit\Framework\TestCase;

class FlattenTest extends TestCase
{
    // Basic tests for the flatten() method

    public function testOkOkFlattensToOk(): void
    {
        $inner = new Ok(42);
        $outer = new Ok($inner);
        $flattened = $outer->flatten();

        $this->assertInstanceOf(Ok::class, $flattened);
        $this->assertSame($inner, $flattened);
        $this->assertSame(42, $flattened->unwrap());
    }

    public function testOkErrFlattensToErr(): void
    {
        $inner = new Err('inner error');
        $outer = new Ok($inner);
        $flattened = $outer->flatten();

        $this->assertInstanceOf(Err::class, $flattened);
        $this->assertSame($inner, $flattened);
        $this->assertSame('inner error', $flattened->unwrapErr());
    }

    public function testErrFlattensToSelf(): void
    {
        $err = new Err('original error');
        $flattened = $err->flatten();

        $this->assertSame($err, $flattened);
        $this->assertSame('original error', $flattened->unwrapErr());
    }

    public function testOkWithNonResultFlattensToSelf(): void
    {
        $ok = new Ok('simple value');
        $flattened = $ok->flatten();

        $this->assertSame($ok, $flattened);
        $this->assertSame('simple value', $flattened->unwrap());
    }

    // Tests by value type

    public function testFlattenWithStringValue(): void
    {
        $inner = new Ok('hello world');
        $outer = new Ok($inner);
        $flattened = $outer->flatten();

        $this->assertInstanceOf(Ok::class, $flattened);
        $this->assertSame('hello world', $flattened->unwrap());
    }

    public function testFlattenWithIntegerValue(): void
    {
        $inner = new Ok(123);
        $outer = new Ok($inner);
        $flattened = $outer->flatten();

        $this->assertInstanceOf(Ok::class, $flattened);
        $this->assertSame(123, $flattened->unwrap());
    }

    public function testFlattenWithArrayValue(): void
    {
        $array = [1, 2, 3];
        $inner = new Ok($array);
        $outer = new Ok($inner);
        $flattened = $outer->flatten();

        $this->assertInstanceOf(Ok::class, $flattened);
        $this->assertSame($array, $flattened->unwrap());
    }

    public function testFlattenWithObjectValue(): void
    {
        $obj = new \stdClass();
        $obj->value = 'test';
        $inner = new Ok($obj);
        $outer = new Ok($inner);
        $flattened = $outer->flatten();

        $this->assertInstanceOf(Ok::class, $flattened);
        $this->assertSame($obj, $flattened->unwrap());
    }

    // Tests by error type

    public function testFlattenWithStringError(): void
    {
        $inner = new Err('string error');
        $outer = new Ok($inner);
        $flattened = $outer->flatten();

        $this->assertInstanceOf(Err::class, $flattened);
        $this->assertSame('string error', $flattened->unwrapErr());
    }

    public function testFlattenWithIntegerError(): void
    {
        $inner = new Err(404);
        $outer = new Ok($inner);
        $flattened = $outer->flatten();

        $this->assertInstanceOf(Err::class, $flattened);
        $this->assertSame(404, $flattened->unwrapErr());
    }

    public function testFlattenWithArrayError(): void
    {
        $errorArray = ['code' => 500, 'message' => 'server error'];
        $inner = new Err($errorArray);
        $outer = new Ok($inner);
        $flattened = $outer->flatten();

        $this->assertInstanceOf(Err::class, $flattened);
        $this->assertSame($errorArray, $flattened->unwrapErr());
    }

    // Edge case tests

    public function testFlattenWithNullValue(): void
    {
        $inner = new Ok(null);
        $outer = new Ok($inner);
        $flattened = $outer->flatten();

        $this->assertInstanceOf(Ok::class, $flattened);
        $this->assertSame(null, $flattened->unwrap());
    }

    public function testFlattenWithNullError(): void
    {
        $inner = new Err(null);
        $outer = new Ok($inner);
        $flattened = $outer->flatten();

        $this->assertInstanceOf(Err::class, $flattened);
        $this->assertSame(null, $flattened->unwrapErr());
    }

    public function testFlattenNonResultWithNull(): void
    {
        $ok = new Ok(null);
        $flattened = $ok->flatten();

        $this->assertSame($ok, $flattened);
        $this->assertSame(null, $flattened->unwrap());
    }

    // Multiple nesting test (confirming only one level is flattened)

    public function testFlattenOnlyRemovesOneLevel(): void
    {
        $innermost = new Ok(42);
        $middle = new Ok($innermost);
        $outer = new Ok($middle);

        $flattened = $outer->flatten();

        $this->assertInstanceOf(Ok::class, $flattened);
        $this->assertSame($middle, $flattened);
        $this->assertInstanceOf(Ok::class, $flattened->unwrap());
        $this->assertSame(42, $flattened->unwrap()->unwrap());
    }

    public function testMultipleFlattenCalls(): void
    {
        $innermost = new Ok(42);
        $middle = new Ok($innermost);
        $outer = new Ok($middle);

        $firstFlatten = $outer->flatten();
        $secondFlatten = $firstFlatten->flatten();

        $this->assertInstanceOf(Ok::class, $secondFlatten);
        $this->assertSame($innermost, $secondFlatten);
        $this->assertSame(42, $secondFlatten->unwrap());
    }

    // Type safety tests

    public function testFlattenReturnTypeIsResult(): void
    {
        $inner = new Ok('test');
        $outer = new Ok($inner);
        $flattened = $outer->flatten();

        $this->assertInstanceOf(\ba0918\Result\Result::class, $flattened);
    }

    public function testErrFlattenReturnTypeIsResult(): void
    {
        $err = new Err('error');
        $flattened = $err->flatten();

        $this->assertInstanceOf(\ba0918\Result\Result::class, $flattened);
    }

    // Method chain tests

    public function testFlattenInMethodChain(): void
    {
        $innerValue = 42;
        $inner = new Ok($innerValue);
        $outer = new Ok($inner);

        $flattened = $outer->flatten();
        $this->assertInstanceOf(Ok::class, $flattened);
        $this->assertSame(42, $flattened->unwrap());

        $mapped = $flattened->map(fn (mixed $x): string => 'Value: ' . print_r($x, true));
        $result = $mapped->unwrap();

        $this->assertSame('Value: 42', $result);
    }

    public function testFlattenWithErrorInMethodChain(): void
    {
        $inner = new Err('calculation failed');
        $outer = new Ok($inner);

        $flattened = $outer->flatten();
        $this->assertInstanceOf(Err::class, $flattened);
        $this->assertSame('calculation failed', $flattened->unwrapErr());

        $mapped = $flattened->map(fn (mixed $x): string => 'Value: ' . print_r($x, true));
        $result = $mapped->unwrapOr('default');

        $this->assertSame('default', $result);
    }

    // Practical use case tests

    public function testFlattenInValidationScenario(): void
    {
        // When validation results are nested
        $validationResult = function ($input): \ba0918\Result\Result {
            if ($input > 0) {
                return new Ok(new Ok($input));
            }

            return new Ok(new Err('Value must be positive'));
        };

        // Normal case
        $result1 = $validationResult(10)->flatten();
        $this->assertInstanceOf(Ok::class, $result1);
        $this->assertSame(10, $result1->unwrap());

        // Error case
        $result2 = $validationResult(-5)->flatten();
        $this->assertInstanceOf(Err::class, $result2);
        $this->assertSame('Value must be positive', $result2->unwrapErr());
    }

    // Performance tests

    public function testFlattenPerformanceWithLargeData(): void
    {
        $largeArray = range(1, 1000);
        $inner = new Ok($largeArray);
        $outer = new Ok($inner);

        $startTime = microtime(true);
        $flattened = $outer->flatten();
        $endTime = microtime(true);

        $this->assertInstanceOf(Ok::class, $flattened);
        $this->assertSame($largeArray, $flattened->unwrap());
        $this->assertLessThan(0.01, $endTime - $startTime, 'flatten() should be fast for large data');
    }

    // Reference consistency tests

    public function testFlattenPreservesObjectReferences(): void
    {
        $sharedObject = new \stdClass();
        $sharedObject->id = 123;

        $inner = new Ok($sharedObject);
        $outer = new Ok($inner);
        $flattened = $outer->flatten();

        $this->assertSame($sharedObject, $flattened->unwrap());

        // Confirm that object mutations are reflected
        $sharedObject->modified = true;
        $this->assertTrue($flattened->unwrap()->modified ?? false);
    }

    // Boundary value tests

    public function testFlattenWithBooleanValues(): void
    {
        // true value
        $trueInner = new Ok(true);
        $trueOuter = new Ok($trueInner);
        $trueFlattened = $trueOuter->flatten();
        $this->assertTrue($trueFlattened->unwrap());

        // false value
        $falseInner = new Ok(false);
        $falseOuter = new Ok($falseInner);
        $falseFlattened = $falseOuter->flatten();
        $this->assertFalse($falseFlattened->unwrap());
    }

    public function testFlattenWithEmptyArrayAndString(): void
    {
        // Empty array
        $emptyArrayInner = new Ok([]);
        $emptyArrayOuter = new Ok($emptyArrayInner);
        $emptyArrayFlattened = $emptyArrayOuter->flatten();
        $this->assertSame([], $emptyArrayFlattened->unwrap());

        // Empty string
        $emptyStringInner = new Ok('');
        $emptyStringOuter = new Ok($emptyStringInner);
        $emptyStringFlattened = $emptyStringOuter->flatten();
        $this->assertSame('', $emptyStringFlattened->unwrap());
    }
}
