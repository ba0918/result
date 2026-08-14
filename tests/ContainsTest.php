<?php

declare(strict_types=1);

use ba0918\Result\Err;
use ba0918\Result\Ok;
use PHPUnit\Framework\TestCase;

class ContainsTest extends TestCase
{
    // Basic behavior tests for the contains() method

    public function testOkContainsWithEqualValue(): void
    {
        $ok = Ok::of(42);

        $this->assertTrue($ok->contains(42));
    }

    public function testOkContainsWithDifferentValue(): void
    {
        $ok = Ok::of(42);

        $this->assertFalse($ok->contains(24));
    }

    public function testOkContainsWithDifferentType(): void
    {
        $ok = Ok::of(42);

        $this->assertFalse($ok->contains('42'));
        $this->assertFalse($ok->contains(42.0));
    }

    public function testOkContainsWithString(): void
    {
        $ok = Ok::of('hello');

        $this->assertTrue($ok->contains('hello'));
        $this->assertFalse($ok->contains('world'));
        $this->assertFalse($ok->contains(null));
    }

    // Basic behavior tests for the containsErr() method

    public function testErrContainsErrWithEqualError(): void
    {
        $err = Err::of('database error');

        $this->assertTrue($err->containsErr('database error'));
    }

    public function testErrContainsErrWithDifferentError(): void
    {
        $err = Err::of('database error');

        $this->assertFalse($err->containsErr('network error'));
    }

    public function testErrContainsErrWithDifferentType(): void
    {
        $err = Err::of(404);

        $this->assertTrue($err->containsErr(404));
        $this->assertFalse($err->containsErr('404'));
    }

    // Cross-type tests

    public function testOkContainsErrAlwaysFalse(): void
    {
        $ok = Ok::of('success');

        $this->assertFalse($ok->containsErr('success'));
        $this->assertFalse($ok->containsErr('error'));
        $this->assertFalse($ok->containsErr(null));
    }

    public function testErrContainsAlwaysFalse(): void
    {
        $err = Err::of('error');

        $this->assertFalse($err->contains('error'));
        $this->assertFalse($err->contains('success'));
        $this->assertFalse($err->contains(null));
    }

    // Edge case tests: null values

    public function testOkContainsWithNull(): void
    {
        $ok = Ok::of(null);

        $this->assertTrue($ok->contains(null));
        $this->assertFalse($ok->contains(0));
        $this->assertFalse($ok->contains(''));
        $this->assertFalse($ok->contains(false));
    }

    public function testErrContainsErrWithNull(): void
    {
        $err = Err::of(null);

        $this->assertTrue($err->containsErr(null));
        $this->assertFalse($err->containsErr(0));
        $this->assertFalse($err->containsErr(''));
        $this->assertFalse($err->containsErr(false));
    }

    // Edge case tests: object reference comparison

    public function testOkContainsWithObjectReference(): void
    {
        $obj = new \stdClass();
        $obj->value = 'test';
        $ok = Ok::of($obj);

        $this->assertTrue($ok->contains($obj));

        $differentObj = new \stdClass();
        $differentObj->value = 'test';
        $this->assertFalse($ok->contains($differentObj));
    }

    public function testErrContainsErrWithObjectReference(): void
    {
        $errorObj = new \stdClass();
        $errorObj->message = 'error';
        $err = Err::of($errorObj);

        $this->assertTrue($err->containsErr($errorObj));

        $differentErrorObj = new \stdClass();
        $differentErrorObj->message = 'error';
        $this->assertFalse($err->containsErr($differentErrorObj));
    }

    // Edge case tests: strict array comparison

    public function testOkContainsWithArray(): void
    {
        $array = [1, 2, 3];
        $ok = Ok::of($array);

        $this->assertTrue($ok->contains([1, 2, 3]));
        $this->assertFalse($ok->contains(['1', '2', '3']));
        $this->assertFalse($ok->contains([1, 2, 3, 4]));
        $this->assertFalse($ok->contains([3, 2, 1]));
    }

    public function testErrContainsErrWithArray(): void
    {
        $errorArray = ['code' => 500, 'message' => 'server error'];
        $err = Err::of($errorArray);

        $this->assertTrue($err->containsErr(['code' => 500, 'message' => 'server error']));
        $this->assertFalse($err->containsErr(['code' => '500', 'message' => 'server error']));
        $this->assertFalse($err->containsErr(['message' => 'server error', 'code' => 500]));
    }

    // Edge case tests: strict numeric type comparison

    public function testOkContainsWithNumericTypes(): void
    {
        $intOk = Ok::of(42);
        $this->assertTrue($intOk->contains(42));
        $this->assertFalse($intOk->contains(42.0));
        $this->assertFalse($intOk->contains('42'));

        $floatOk = Ok::of(42.5);
        $this->assertTrue($floatOk->contains(42.5));
        $this->assertFalse($floatOk->contains(42));
        $this->assertFalse($floatOk->contains('42.5'));
    }

    // Edge case tests: strict bool comparison

    public function testOkContainsWithBooleanTypes(): void
    {
        $trueOk = Ok::of(true);
        $this->assertTrue($trueOk->contains(true));
        $this->assertFalse($trueOk->contains(1));
        $this->assertFalse($trueOk->contains('true'));

        $falseOk = Ok::of(false);
        $this->assertTrue($falseOk->contains(false));
        $this->assertFalse($falseOk->contains(0));
        $this->assertFalse($falseOk->contains(''));
        $this->assertFalse($falseOk->contains(null));
    }

    // Composite tests: complex data structures

    public function testOkContainsWithComplexData(): void
    {
        $complexData = [
            'user' => ['id' => 1, 'name' => 'Alice'],
            'permissions' => ['read', 'write'],
            'active' => true,
        ];
        $ok = Ok::of($complexData);

        $this->assertTrue($ok->contains($complexData));

        $similarData = [
            'user' => ['id' => 1, 'name' => 'Alice'],
            'permissions' => ['read', 'write'],
            'active' => true,
        ];
        $this->assertTrue($ok->contains($similarData));

        $differentData = [
            'user' => ['id' => 1, 'name' => 'Bob'],
            'permissions' => ['read', 'write'],
            'active' => true,
        ];
        $this->assertFalse($ok->contains($differentData));
    }

    // Performance tests: large data structures

    public function testContainsPerformanceWithLargeData(): void
    {
        $largeArray = range(1, 1000);
        $ok = Ok::of($largeArray);

        $startTime = microtime(true);
        $result = $ok->contains($largeArray);
        $endTime = microtime(true);

        $this->assertTrue($result);
        $this->assertLessThan(0.01, $endTime - $startTime, 'contains() should be fast for large data');
    }

    // Type safety tests

    public function testContainsReturnType(): void
    {
        $ok = Ok::of('test');
        $err = Err::of('error');

        // Confirm that the return value behaves correctly as a bool
        $this->assertTrue($ok->contains('test'));
        $this->assertFalse($ok->containsErr('error'));
        $this->assertFalse($err->contains('test'));
        $this->assertTrue($err->containsErr('error'));
    }
}
