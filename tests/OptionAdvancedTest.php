<?php

use Mizumi\Result\Some;
use Mizumi\Result\None;
use PHPUnit\Framework\TestCase;

class OptionAdvancedTest extends TestCase
{
    // xor()メソッドのテスト
    public function testXorSomeSome(): void
    {
        $some1 = new Some(1);
        $some2 = new Some(2);
        $result = $some1->xor($some2);
        
        $this->assertInstanceOf(None::class, $result);
        $this->assertTrue($result->isNone());
    }

    public function testXorSomeNone(): void
    {
        $some = new Some(42);
        $none = None::instance();
        $result = $some->xor($none);
        
        $this->assertInstanceOf(Some::class, $result);
        $this->assertTrue($result->isSome());
        $this->assertEquals(42, $result->unwrap());
    }

    public function testXorNoneSome(): void
    {
        $none = None::instance();
        $some = new Some(100);
        $result = $none->xor($some);
        
        $this->assertInstanceOf(Some::class, $result);
        $this->assertTrue($result->isSome());
        $this->assertEquals(100, $result->unwrap());
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
        $stringOption = new Some("hello");
        $intOption = new Some(123);
        $result = $stringOption->xor($intOption);
        
        $this->assertInstanceOf(None::class, $result);
        $this->assertTrue($result->isNone());
    }

    public function testXorWithComplexValues(): void
    {
        $arrayOption = new Some(['key' => 'value']);
        $none = None::instance();
        $result = $arrayOption->xor($none);
        
        $this->assertInstanceOf(Some::class, $result);
        $this->assertEquals(['key' => 'value'], $result->unwrap());
    }

    public function testXorWithNullValue(): void
    {
        $nullOption = new Some(null);
        $none = None::instance();
        $result = $nullOption->xor($none);
        
        $this->assertInstanceOf(Some::class, $result);
        $this->assertNull($result->unwrap());
    }

    // zip()メソッドのテスト
    public function testZipSomeSome(): void
    {
        $some1 = new Some(1);
        $some2 = new Some("hello");
        $result = $some1->zip($some2);
        
        $this->assertInstanceOf(Some::class, $result);
        $this->assertTrue($result->isSome());
        $this->assertEquals([1, "hello"], $result->unwrap());
    }

    public function testZipSomeNone(): void
    {
        $some = new Some(42);
        $none = None::instance();
        $result = $some->zip($none);
        
        $this->assertInstanceOf(None::class, $result);
        $this->assertTrue($result->isNone());
    }

    public function testZipNoneSome(): void
    {
        $none = None::instance();
        $some = new Some(100);
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
        $arrayOption = new Some(['a' => 1, 'b' => 2]);
        $objectOption = new Some((object)['x' => 10, 'y' => 20]);
        $result = $arrayOption->zip($objectOption);
        
        $this->assertTrue($result->isSome());
        $resultValue = $result->unwrap();
        /** @phpstan-ignore method.alreadyNarrowedType */
        $this->assertIsArray($resultValue);
        $this->assertCount(2, $resultValue);
        $this->assertEquals(['a' => 1, 'b' => 2], $resultValue[0]);
        $this->assertEquals((object)['x' => 10, 'y' => 20], $resultValue[1]);
    }

    public function testZipWithNullValues(): void
    {
        $nullOption1 = new Some(null);
        $nullOption2 = new Some(null);
        $result = $nullOption1->zip($nullOption2);
        
        $this->assertTrue($result->isSome());
        $this->assertEquals([null, null], $result->unwrap());
    }

    public function testZipWithMixedTypes(): void
    {
        $intOption = new Some(123);
        $boolOption = new Some(true);
        $result = $intOption->zip($boolOption);
        
        $this->assertTrue($result->isSome());
        $this->assertEquals([123, true], $result->unwrap());
    }

    // 統合テスト
    public function testXorChaining(): void
    {
        $some1 = new Some(1);
        $some2 = new Some(2);
        $none = None::instance();
        
        // Some.xor(Some) -> None
        $result1 = $some1->xor($some2);
        $this->assertTrue($result1->isNone());
        
        // None.xor(Some) -> Some
        $result2 = $result1->xor($some1);
        $this->assertTrue($result2->isSome());
        $this->assertEquals(1, $result2->unwrap());
    }

    public function testZipChaining(): void
    {
        $some1 = new Some(1);
        $some2 = new Some(2);
        $some3 = new Some(3);
        
        // zip creates tuple, then zip with another value
        $result1 = $some1->zip($some2);
        $this->assertEquals([1, 2], $result1->unwrap());
        
        $result2 = $result1->zip($some3);
        $this->assertEquals([[1, 2], 3], $result2->unwrap());
    }

    public function testXorZipCombination(): void
    {
        $some1 = new Some(10);
        $some2 = new Some(20);
        $some3 = new Some(30);
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
        $this->assertEquals([10, 30], $zipResult2->unwrap());
    }

    public function testComplexScenario(): void
    {
        // 複雑なシナリオ：配列の値を持つOption同士の操作
        $users = new Some([
            ['name' => 'Alice', 'age' => 25],
            ['name' => 'Bob', 'age' => 30]
        ]);
        
        $settings = new Some([
            'theme' => 'dark',
            'language' => 'en'
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
        $this->assertEquals($result, $xorResult->unwrap());
    }
}