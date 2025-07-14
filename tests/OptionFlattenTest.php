<?php

declare(strict_types=1);

namespace Mizumi\Result\Tests;

use Mizumi\Result\None;
use Mizumi\Result\Some;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Option型flatten()メソッドのテスト
 */
final class OptionFlattenTest extends TestCase
{
    // 基本動作テスト

    public function testSomeSomeFlattensToSome(): void
    {
        // Some(Some(value)) → Some(value)
        $inner = Some::of(42);
        $outer = Some::of($inner);
        $flattened = $outer->flatten();

        $this->assertInstanceOf(Some::class, $flattened);
        $this->assertSame($inner, $flattened);
        $this->assertEquals(42, $flattened->unwrap());
    }

    public function testSomeNoneFlattensToNone(): void
    {
        // Some(None) → None
        $someNone = Some::of(None::instance());
        $flattened = $someNone->flatten();

        $this->assertInstanceOf(None::class, $flattened);
        $this->assertSame(None::instance(), $flattened);
    }

    public function testNoneFlattensToSelf(): void
    {
        // None → None
        $none = None::instance();
        $flattened = $none->flatten();

        $this->assertSame($none, $flattened);
        $this->assertTrue($flattened->isNone());
    }

    public function testSomeWithNonOptionFlattensToSelf(): void
    {
        // Some(non_option_value) → Some(non_option_value)
        $someString = Some::of('string_value');
        $flattened = $someString->flatten();

        $this->assertSame($someString, $flattened);
        $this->assertEquals('string_value', $flattened->unwrap());
    }

    // エッジケーステスト

    public function testDeepNesting(): void
    {
        // 深いネスト: Some(Some(Some(value))) は一段階のみ平坦化
        $deepest = Some::of(42);
        $middle = Some::of($deepest);
        $outer = Some::of($middle);
        $flattened = $outer->flatten();

        $this->assertInstanceOf(Some::class, $flattened);
        $this->assertSame($middle, $flattened);
        // さらにflatten()すると最深層まで到達
        $this->assertSame($deepest, $flattened->flatten());
        $this->assertEquals(42, $flattened->flatten()->unwrap());
    }

    public function testSomeWithNullValue(): void
    {
        // null値の処理: Some(null)
        $someNull = Some::of(null);
        $flattened = $someNull->flatten();

        $this->assertSame($someNull, $flattened);
        $this->assertNull($flattened->unwrap());
    }

    public function testSomeWithZeroValue(): void
    {
        // ゼロ値の処理: Some(0)
        $someZero = Some::of(0);
        $flattened = $someZero->flatten();

        $this->assertSame($someZero, $flattened);
        $this->assertEquals(0, $flattened->unwrap());
    }

    public function testSomeWithEmptyString(): void
    {
        // 空文字列の処理: Some("")
        $someEmpty = Some::of('');
        $flattened = $someEmpty->flatten();

        $this->assertSame($someEmpty, $flattened);
        $this->assertEquals('', $flattened->unwrap());
    }

    public function testSomeWithFalseValue(): void
    {
        // false値の処理: Some(false)
        $someFalse = Some::of(false);
        $flattened = $someFalse->flatten();

        $this->assertSame($someFalse, $flattened);
        $this->assertFalse($flattened->unwrap());
    }

    public function testSomeWithArrayValue(): void
    {
        // 配列値の処理: Some([1, 2, 3])
        $array = [1, 2, 3];
        $someArray = Some::of($array);
        $flattened = $someArray->flatten();

        $this->assertSame($someArray, $flattened);
        $this->assertEquals($array, $flattened->unwrap());
    }

    public function testSomeWithObjectValue(): void
    {
        // オブジェクト値の処理
        $object = new stdClass();
        $object->name = 'test';
        $someObject = Some::of($object);
        $flattened = $someObject->flatten();

        $this->assertSame($someObject, $flattened);
        $this->assertSame($object, $flattened->unwrap());
    }

    // 型安全性テスト

    public function testFlattenReturnType(): void
    {
        // 戻り値の型確認
        $someOption = Some::of(Some::of('test'));
        $result = $someOption->flatten();

        $this->assertInstanceOf(Some::class, $result);
        $this->assertTrue($result->isSome());
        $this->assertEquals('test', $result->unwrap());
    }

    public function testChainedFlatten(): void
    {
        // flatten()の連鎖テスト
        $triplyNested = Some::of(Some::of(Some::of('value')));

        // 1回目のflatten
        $onceFlattened = $triplyNested->flatten();
        $this->assertInstanceOf(Some::class, $onceFlattened);
        $this->assertEquals('value', $onceFlattened->flatten()->unwrap());

        // 2回目のflatten
        $twiceFlattened = $onceFlattened->flatten();
        $this->assertInstanceOf(Some::class, $twiceFlattened);
        $this->assertEquals('value', $twiceFlattened->unwrap());

        // 3回目のflatten（効果なし）
        $thriceFlattened = $twiceFlattened->flatten();
        $this->assertSame($twiceFlattened, $thriceFlattened);
    }

    // パフォーマンステスト（基本的なケース）

    public function testFlattenPerformance(): void
    {
        // 大量のネストでもパフォーマンスが安定していることを確認
        $start = microtime(true);

        for ($i = 0; $i < 1000; $i++) {
            $some = Some::of(Some::of($i));
            $flattened = $some->flatten();
            $this->assertEquals($i, $flattened->unwrap());
        }

        $end = microtime(true);
        $this->assertLessThan(1.0, $end - $start, 'flatten操作は1秒以内に完了する必要があります');
    }

    // 実用的なユースケーステスト

    public function testPracticalUseCaseWithMapAndFlatten(): void
    {
        // map操作でOptionが二重にネストした場合のflatten
        $option = Some::of(5);

        // mapでSome(Some(value))を作成
        $mapped = $option->map(function ($x) {
            assert(is_int($x));

            return Some::of($x * 2);
        });
        $this->assertTrue($mapped->isSome());
        $this->assertInstanceOf(Some::class, $mapped->unwrap());

        // flattenで平坦化
        $flattened = $mapped->flatten();
        $this->assertTrue($flattened->isSome());
        $this->assertEquals(10, $flattened->unwrap());
    }
}
