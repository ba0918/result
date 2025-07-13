<?php

namespace Mizumi\Result\Tests;

use Mizumi\Result\Err;
use Mizumi\Result\None;
use Mizumi\Result\Ok;
use Mizumi\Result\Some;
use PHPUnit\Framework\TestCase;

/**
 * transpose()メソッドの専用テスト
 */
final class TransposeTest extends TestCase
{
    public function testOptionSomeOkTranspose(): void
    {
        // Some(Ok(value)) → Ok(Some(value))
        $optionResult = Some::of(Ok::of('test'));
        $result = $optionResult->transpose();
        
        $this->assertTrue($result->isOk());
        $unwrapped = $result->unwrap();
        $this->assertTrue($unwrapped->isSome());
        $this->assertSame('test', $unwrapped->unwrap());
    }

    public function testOptionSomeErrTranspose(): void
    {
        // Some(Err(error)) → Err(error)
        $optionResult = Some::of(Err::of('error'));
        $result = $optionResult->transpose();
        
        $this->assertTrue($result->isErr());
        $this->assertSame('error', $result->unwrapErr());
    }

    public function testOptionNoneTranspose(): void
    {
        // None → Ok(None)
        $option = None::instance();
        $result = $option->transpose();
        
        $this->assertTrue($result->isOk());
        $unwrapped = $result->unwrap();
        $this->assertTrue($unwrapped->isNone());
    }

    public function testOptionNonResultTranspose(): void
    {
        // Some(non-Result) → Ok(Some(value))
        $option = Some::of('plain_value');
        $result = $option->transpose();
        
        $this->assertTrue($result->isOk());
        $unwrapped = $result->unwrap();
        $this->assertTrue($unwrapped->isSome());
        $this->assertSame('plain_value', $unwrapped->unwrap());
    }

    public function testResultOkSomeTranspose(): void
    {
        // Ok(Some(value)) → Some(Ok(value))
        $resultOption = Ok::of(Some::of('test'));
        $option = $resultOption->transpose();
        
        $this->assertTrue($option->isSome());
        $unwrapped = $option->unwrap();
        $this->assertTrue($unwrapped->isOk());
        $this->assertSame('test', $unwrapped->unwrap());
    }

    public function testResultOkNoneTranspose(): void
    {
        // Ok(None) → None
        $resultOption = Ok::of(None::instance());
        $option = $resultOption->transpose();
        
        $this->assertTrue($option->isNone());
    }

    public function testResultErrTranspose(): void
    {
        // Err(error) → Some(Err(error))
        $result = Err::of('error');
        $option = $result->transpose();
        
        $this->assertTrue($option->isSome());
        $unwrapped = $option->unwrap();
        $this->assertTrue($unwrapped->isErr());
        $this->assertSame('error', $unwrapped->unwrapErr());
    }

    public function testResultOkNonOptionTranspose(): void
    {
        // Ok(non-Option) → Some(Ok(value))
        $result = Ok::of('plain_value');
        $option = $result->transpose();
        
        $this->assertTrue($option->isSome());
        $unwrapped = $option->unwrap();
        $this->assertTrue($unwrapped->isOk());
        $this->assertSame('plain_value', $unwrapped->unwrap());
    }

    public function testDoubleTranspose(): void
    {
        // transpose()の相互変換性をテスト
        $original = Some::of(Ok::of('value'));
        $transposed = $original->transpose(); // Ok(Some(value))
        $doubleTransposed = $transposed->transpose(); // Some(Ok(value))
        
        // Some(Ok(value)) → Ok(Some(value)) → Some(Ok(value))
        $this->assertTrue($doubleTransposed->isSome());
        $result = $doubleTransposed->unwrap();
        $this->assertTrue($result->isOk());
        $this->assertSame('value', $result->unwrap());
    }

    public function testComplexNestedTranspose(): void
    {
        // より複雑なケース
        $nested = Some::of(Ok::of(Some::of('nested')));
        $result = $nested->transpose(); // Ok(Some(Some('nested')))
        
        $this->assertTrue($result->isOk());
        $innerOption = $result->unwrap();
        $this->assertTrue($innerOption->isSome());
        $innerValue = $innerOption->unwrap();
        $this->assertTrue($innerValue->isSome());
        $this->assertSame('nested', $innerValue->unwrap());
    }

    public function testTransposeWithNullValue(): void
    {
        // null値のテスト
        $option = Some::of(Ok::of(null));
        $result = $option->transpose();
        
        $this->assertTrue($result->isOk());
        $innerOption = $result->unwrap();
        $this->assertTrue($innerOption->isSome());
        $this->assertNull($innerOption->unwrap());
    }

    public function testTransposeWithArrayValue(): void
    {
        // 配列値のテスト
        $array = ['a', 'b', 'c'];
        $option = Some::of(Ok::of($array));
        $result = $option->transpose();
        
        $this->assertTrue($result->isOk());
        $innerOption = $result->unwrap();
        $this->assertTrue($innerOption->isSome());
        $this->assertSame($array, $innerOption->unwrap());
    }

    public function testTransposeWithObjectValue(): void
    {
        // オブジェクト値のテスト
        $obj = new \stdClass();
        $obj->prop = 'value';
        
        $option = Some::of(Ok::of($obj));
        $result = $option->transpose();
        
        $this->assertTrue($result->isOk());
        $innerOption = $result->unwrap();
        $this->assertTrue($innerOption->isSome());
        $this->assertSame($obj, $innerOption->unwrap());
    }

    public function testTransposeErrorPropagation(): void
    {
        // エラーの伝播テスト
        $errors = ['error1', 'error2', new \Exception('exception')];
        
        foreach ($errors as $error) {
            $option = Some::of(Err::of($error));
            $result = $option->transpose();
            
            $this->assertTrue($result->isErr());
            $this->assertSame($error, $result->unwrapErr());
        }
    }

    public function testTransposeChaining(): void
    {
        // メソッドチェーンでのtranspose()テスト
        $value = Some::of(Ok::of(42))
            ->transpose()
            ->map(fn($opt) => $opt->map(fn($x) => $x * 2))
            ->unwrap()
            ->unwrap();
            
        $this->assertSame(84, $value);
    }
}