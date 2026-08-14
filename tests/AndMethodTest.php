<?php

declare(strict_types=1);

use ba0918\Result\Err;
use ba0918\Result\Ok;
use PHPUnit\Framework\TestCase;

class AndMethodTest extends TestCase
{
    // Basic tests for the and() method

    public function testAndWithOkAndOk(): void
    {
        $first = Ok::of(10);
        $second = Ok::of(20);

        $result = $first->and($second);

        $this->assertTrue($result->isOk());
        $this->assertSame(20, $result->unwrap());
    }

    public function testAndWithOkAndErr(): void
    {
        $ok = Ok::of(10);
        $err = Err::of('error');

        $result = $ok->and($err);

        $this->assertTrue($result->isErr());
        $this->assertSame('error', $result->unwrapErr());
    }

    public function testAndWithErrAndOk(): void
    {
        $err = Err::of('error');
        $ok = Ok::of(20);

        $result = $err->and($ok);

        $this->assertTrue($result->isErr());
        $this->assertSame('error', $result->unwrapErr());
    }

    public function testAndWithErrAndErr(): void
    {
        $firstErr = Err::of('first error');
        $secondErr = Err::of('second error');

        $result = $firstErr->and($secondErr);

        $this->assertTrue($result->isErr());
        $this->assertSame('first error', $result->unwrapErr());
    }

    // Tests between Results of different types

    public function testAndWithDifferentTypes(): void
    {
        $intOk = Ok::of(123);
        $stringOk = Ok::of('text');

        $result = $intOk->and($stringOk);

        $this->assertTrue($result->isOk());
        $this->assertSame('text', $result->unwrap());
    }

    public function testAndWithDifferentErrorTypes(): void
    {
        $stringErr = Err::of('text error');
        $intErr = Err::of(404);

        $result = $stringErr->and($intErr);

        $this->assertTrue($result->isErr());
        $this->assertSame('text error', $result->unwrapErr());
    }

    // Chain operation tests

    public function testAndChaining(): void
    {
        $result = (Ok::of('start'))
            ->and(Ok::of('middle'))
            ->and(Ok::of('end'));

        $this->assertTrue($result->isOk());
        $this->assertSame('end', $result->unwrap());
    }

    public function testAndChainingWithError(): void
    {
        $result = (Ok::of('start'))
            ->and(Err::of('failed'))
            ->and(Ok::of('never reached'));

        $this->assertTrue($result->isErr());
        $this->assertSame('failed', $result->unwrapErr());
    }

    public function testAndWithOtherMethods(): void
    {
        $result = (Ok::of(10))
            ->map(fn ($x) => $x * 2)  // Ok(20)
            ->and(Ok::of('success')) // Ok('success')
            ->or(Ok::of('fallback')); // Ok('success')

        $this->assertTrue($result->isOk());
        $this->assertSame('success', $result->unwrap());
    }

    public function testAndWithOtherMethodsError(): void
    {
        $result = (Err::of('initial error'))
            ->mapErr(fn ($e) => 'mapped: ' . $e)  // Err('mapped: initial error')
            ->and(Ok::of('never used'))          // Err('mapped: initial error')
            ->or(Ok::of('recovered'));           // Ok('recovered')

        $this->assertTrue($result->isOk());
        $this->assertSame('recovered', $result->unwrap());
    }

    // Complex type conversion tests

    public function testAndWithComplexTypes(): void
    {
        $arrayOk = Ok::of(['key' => 'value']);
        $objectValue = (object) ['prop' => 'data'];
        $objectOk = Ok::of($objectValue);

        $result = $arrayOk->and($objectOk);

        $this->assertTrue($result->isOk());
        $this->assertSame($objectValue, $result->unwrap());
    }

    public function testAndWithComplexErrorTypes(): void
    {
        $arrayErr = Err::of(['code' => 404, 'message' => 'Not found']);
        $exceptionErr = Err::of(new \Exception('Exception error'));

        $result = $arrayErr->and($exceptionErr);

        $this->assertTrue($result->isErr());
        $this->assertSame(['code' => 404, 'message' => 'Not found'], $result->unwrapErr());
    }

    // Edge case tests

    public function testAndWithNullValues(): void
    {
        $nullOk = Ok::of(null);
        $valueOk = Ok::of('value');

        $result = $nullOk->and($valueOk);

        $this->assertTrue($result->isOk());
        $this->assertSame('value', $result->unwrap());
    }

    public function testAndWithNullErrors(): void
    {
        $nullErr = Err::of(null);
        $valueErr = Err::of('error');

        $result = $nullErr->and($valueErr);

        $this->assertTrue($result->isErr());
        $this->assertNull($result->unwrapErr());
    }
}
