<?php

use Mizumi\Result\Ok;
use Mizumi\Result\Err;
use PHPUnit\Framework\TestCase;

class AndMethodTest extends TestCase
{
    // and()メソッドの基本動作テスト

    public function testAndWithOkAndOk(): void
    {
        $first = new Ok(10);
        $second = new Ok(20);
        
        $result = $first->and($second);
        
        $this->assertTrue($result->isOk());
        $this->assertEquals(20, $result->unwrap());
    }

    public function testAndWithOkAndErr(): void
    {
        $ok = new Ok(10);
        $err = new Err('error');
        
        $result = $ok->and($err);
        
        $this->assertTrue($result->isErr());
        $this->assertEquals('error', $result->unwrapErr());
    }

    public function testAndWithErrAndOk(): void
    {
        $err = new Err('error');
        $ok = new Ok(20);
        
        $result = $err->and($ok);
        
        $this->assertTrue($result->isErr());
        $this->assertEquals('error', $result->unwrapErr());
    }

    public function testAndWithErrAndErr(): void
    {
        $firstErr = new Err('first error');
        $secondErr = new Err('second error');
        
        $result = $firstErr->and($secondErr);
        
        $this->assertTrue($result->isErr());
        $this->assertEquals('first error', $result->unwrapErr());
    }

    // 型の異なるResult間でのテスト

    public function testAndWithDifferentTypes(): void
    {
        $intOk = new Ok(123);
        $stringOk = new Ok('text');
        
        $result = $intOk->and($stringOk);
        
        $this->assertTrue($result->isOk());
        $this->assertEquals('text', $result->unwrap());
    }

    public function testAndWithDifferentErrorTypes(): void
    {
        $stringErr = new Err('text error');
        $intErr = new Err(404);
        
        $result = $stringErr->and($intErr);
        
        $this->assertTrue($result->isErr());
        $this->assertEquals('text error', $result->unwrapErr());
    }

    // チェーン操作テスト

    public function testAndChaining(): void
    {
        $result = (new Ok('start'))
            ->and(new Ok('middle'))
            ->and(new Ok('end'));
            
        $this->assertTrue($result->isOk());
        $this->assertEquals('end', $result->unwrap());
    }

    public function testAndChainingWithError(): void
    {
        $result = (new Ok('start'))
            ->and(new Err('failed'))
            ->and(new Ok('never reached'));
            
        $this->assertTrue($result->isErr());
        $this->assertEquals('failed', $result->unwrapErr());
    }

    public function testAndWithOtherMethods(): void
    {
        $result = (new Ok(10))
            ->map(fn($x) => $x * 2)  // Ok(20)
            ->and(new Ok('success')) // Ok('success')
            ->or(new Ok('fallback')); // Ok('success')
            
        $this->assertTrue($result->isOk());
        $this->assertEquals('success', $result->unwrap());
    }

    public function testAndWithOtherMethodsError(): void
    {
        $result = (new Err('initial error'))
            ->mapErr(fn($e) => 'mapped: ' . $e)  // Err('mapped: initial error')
            ->and(new Ok('never used'))          // Err('mapped: initial error')
            ->or(new Ok('recovered'));           // Ok('recovered')
            
        $this->assertTrue($result->isOk());
        $this->assertEquals('recovered', $result->unwrap());
    }

    // 複雑な型変換テスト

    public function testAndWithComplexTypes(): void
    {
        $arrayOk = new Ok(['key' => 'value']);
        $objectOk = new Ok((object)['prop' => 'data']);
        
        $result = $arrayOk->and($objectOk);
        
        $this->assertTrue($result->isOk());
        $this->assertEquals((object)['prop' => 'data'], $result->unwrap());
    }

    public function testAndWithComplexErrorTypes(): void
    {
        $arrayErr = new Err(['code' => 404, 'message' => 'Not found']);
        $exceptionErr = new Err(new \Exception('Exception error'));
        
        $result = $arrayErr->and($exceptionErr);
        
        $this->assertTrue($result->isErr());
        $this->assertEquals(['code' => 404, 'message' => 'Not found'], $result->unwrapErr());
    }

    // エッジケースのテスト

    public function testAndWithNullValues(): void
    {
        $nullOk = new Ok(null);
        $valueOk = new Ok('value');
        
        $result = $nullOk->and($valueOk);
        
        $this->assertTrue($result->isOk());
        $this->assertEquals('value', $result->unwrap());
    }

    public function testAndWithNullErrors(): void
    {
        $nullErr = new Err(null);
        $valueErr = new Err('error');
        
        $result = $nullErr->and($valueErr);
        
        $this->assertTrue($result->isErr());
        $this->assertNull($result->unwrapErr());
    }
}