<?php

declare(strict_types=1);

use ba0918\Result\None;
use ba0918\Result\Some;
use PHPUnit\Framework\TestCase;

class OptionAdvancedTest extends TestCase
{
    // Tests for the xor() method
    public function testXorSomeSome(): void
    {
        $some1 = Some::of(1);
        $some2 = Some::of(2);
        $result = $some1->xor($some2);

        $this->assertInstanceOf(None::class, $result);
        $this->assertTrue($result->isNone());
    }

    public function testXorSomeNone(): void
    {
        $some = Some::of(42);
        $none = None::instance();
        $result = $some->xor($none);

        $this->assertInstanceOf(Some::class, $result);
        $this->assertTrue($result->isSome());
        $this->assertSame(42, $result->unwrap());
    }

    public function testXorNoneSome(): void
    {
        $none = None::instance();
        $some = Some::of(100);
        $result = $none->xor($some);

        $this->assertInstanceOf(Some::class, $result);
        $this->assertTrue($result->isSome());
        $this->assertSame(100, $result->unwrap());
    }

    public function testXorNoneNone(): void
    {
        $none1 = None::instance();
        $none2 = None::instance();
        $result = $none1->xor($none2);

        $this->assertInstanceOf(None::class, $result);
        $this->assertTrue($result->isNone());
    }

    public function testXorWithDifferentTypes(): void
    {
        $stringOption = Some::of('hello');
        $intOption = Some::of(123);
        $result = $stringOption->xor($intOption);

        $this->assertInstanceOf(None::class, $result);
        $this->assertTrue($result->isNone());
    }

    public function testXorWithComplexValues(): void
    {
        $arrayOption = Some::of(['key' => 'value']);
        $none = None::instance();
        $result = $arrayOption->xor($none);

        $this->assertInstanceOf(Some::class, $result);
        $this->assertSame(['key' => 'value'], $result->unwrap());
    }

    public function testXorWithNullValue(): void
    {
        $nullOption = Some::of(null);
        $none = None::instance();
        $result = $nullOption->xor($none);

        $this->assertInstanceOf(Some::class, $result);
        $this->assertNull($result->unwrap());
    }

    // Tests for the zip() method
    public function testZipSomeSome(): void
    {
        $some1 = Some::of(1);
        $some2 = Some::of('hello');
        $result = $some1->zip($some2);

        $this->assertInstanceOf(Some::class, $result);
        $this->assertTrue($result->isSome());
        $this->assertSame([1, 'hello'], $result->unwrap());
    }

    public function testZipSomeNone(): void
    {
        $some = Some::of(42);
        $none = None::instance();
        $result = $some->zip($none);

        $this->assertInstanceOf(None::class, $result);
        $this->assertTrue($result->isNone());
    }

    public function testZipNoneSome(): void
    {
        $none = None::instance();
        $some = Some::of(100);
        $result = $none->zip($some);

        $this->assertInstanceOf(None::class, $result);
        $this->assertTrue($result->isNone());
    }

    public function testZipNoneNone(): void
    {
        $none1 = None::instance();
        $none2 = None::instance();
        $result = $none1->zip($none2);

        $this->assertInstanceOf(None::class, $result);
        $this->assertTrue($result->isNone());
    }

    public function testZipWithComplexValues(): void
    {
        $arrayValue = ['a' => 1, 'b' => 2];
        $objectValue = (object) ['x' => 10, 'y' => 20];
        $arrayOption = Some::of($arrayValue);
        $objectOption = Some::of($objectValue);
        $result = $arrayOption->zip($objectOption);

        $this->assertTrue($result->isSome());
        $resultValue = $result->unwrap();
        /** @phpstan-ignore method.alreadyNarrowedType */
        $this->assertIsArray($resultValue);
        $this->assertCount(2, $resultValue);
        $this->assertSame($arrayValue, $resultValue[0]);
        $this->assertSame($objectValue, $resultValue[1]);
    }

    public function testZipWithNullValues(): void
    {
        $nullOption1 = Some::of(null);
        $nullOption2 = Some::of(null);
        $result = $nullOption1->zip($nullOption2);

        $this->assertTrue($result->isSome());
        $this->assertSame([null, null], $result->unwrap());
    }

    public function testZipWithMixedTypes(): void
    {
        $intOption = Some::of(123);
        $boolOption = Some::of(true);
        $result = $intOption->zip($boolOption);

        $this->assertTrue($result->isSome());
        $this->assertSame([123, true], $result->unwrap());
    }

    // Integration tests
    public function testXorChaining(): void
    {
        $some1 = Some::of(1);
        $some2 = Some::of(2);
        $none = None::instance();

        // Some.xor(Some) -> None
        $result1 = $some1->xor($some2);
        $this->assertTrue($result1->isNone());

        // None.xor(Some) -> Some
        $result2 = $result1->xor($some1);
        $this->assertTrue($result2->isSome());
        $this->assertSame(1, $result2->unwrap());
    }

    public function testZipChaining(): void
    {
        $some1 = Some::of(1);
        $some2 = Some::of(2);
        $some3 = Some::of(3);

        // zip creates tuple, then zip with another value
        $result1 = $some1->zip($some2);
        $this->assertSame([1, 2], $result1->unwrap());

        $result2 = $result1->zip($some3);
        $this->assertSame([[1, 2], 3], $result2->unwrap());
    }

    public function testXorZipCombination(): void
    {
        $some1 = Some::of(10);
        $some2 = Some::of(20);
        $some3 = Some::of(30);
        $none = None::instance();

        // xor with Some results in None, then zip should be None
        $xorResult = $some1->xor($some2);
        $this->assertTrue($xorResult->isNone());

        $zipResult = $xorResult->zip($some3);
        $this->assertTrue($zipResult->isNone());

        // xor with None results in Some, then zip works
        $xorResult2 = $some1->xor($none);
        $this->assertTrue($xorResult2->isSome());

        $zipResult2 = $xorResult2->zip($some3);
        $this->assertTrue($zipResult2->isSome());
        $this->assertSame([10, 30], $zipResult2->unwrap());
    }

    public function testComplexScenario(): void
    {
        // Complex scenario: operations between Options holding array values
        $users = Some::of([
            ['name' => 'Alice', 'age' => 25],
            ['name' => 'Bob', 'age' => 30],
        ]);

        $settings = Some::of([
            'theme' => 'dark',
            'language' => 'en',
        ]);

        $none = None::instance();

        // zip creates a combined data structure
        $combined = $users->zip($settings);
        $this->assertTrue($combined->isSome());

        $result = $combined->unwrap();
        $this->assertCount(2, $result);
        $this->assertArrayHasKey('name', $result[0][0]);
        $this->assertArrayHasKey('theme', $result[1]);

        // xor with None keeps the data
        $xorResult = $combined->xor($none);
        $this->assertTrue($xorResult->isSome());
        $this->assertSame($result, $xorResult->unwrap());
    }
}
