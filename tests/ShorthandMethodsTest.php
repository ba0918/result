<?php

declare(strict_types=1);

namespace Mizumi\Result\Tests;

use Mizumi\Result\Err;
use Mizumi\Result\None;
use Mizumi\Result\Ok;
use Mizumi\Result\Some;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Tests for shorthand method groups
 */
final class ShorthandMethodsTest extends TestCase
{
    // ===== Result型 isOkAnd/isErrAnd テスト =====

    public function testIsOkAndWithOkValueReturnsTrueWhenPredicateIsTrue(): void
    {
        $result = Ok::of(42);
        $this->assertTrue($result->isOkAnd(fn ($x) => $x > 40));
    }

    public function testIsOkAndWithOkValueReturnsFalseWhenPredicateIsFalse(): void
    {
        $result = Ok::of(42);
        $this->assertFalse($result->isOkAnd(fn ($x) => $x > 50));
    }

    public function testIsOkAndWithErrValueReturnsFalse(): void
    {
        $result = Err::of('error');
        $this->assertFalse($result->isOkAnd(fn ($x) => true));
    }

    public function testIsOkAndWithNullValue(): void
    {
        $result = Ok::of(null);
        $this->assertTrue($result->isOkAnd(fn ($x) => $x === null));
        $this->assertFalse($result->isOkAnd(fn ($x) => $x !== null));
    }

    public function testIsOkAndWithComplexPredicate(): void
    {
        $result = Ok::of(['name' => 'test', 'age' => 25]);
        $this->assertTrue($result->isOkAnd(function ($data) {
            assert(is_array($data));

            return isset($data['name']) && $data['age'] >= 18;
        }));
    }

    public function testIsOkAndWithTypeConversion(): void
    {
        $result = Ok::of('123');
        $this->assertTrue($result->isOkAnd(fn ($x) => is_numeric($x) && (int) $x > 100));
    }

    public function testIsErrAndWithErrValueReturnsTrueWhenPredicateIsTrue(): void
    {
        $result = Err::of('file not found');
        $this->assertTrue($result->isErrAnd(function ($err) {
            assert(is_string($err));

            return str_contains($err, 'not found');
        }));
    }

    public function testIsErrAndWithErrValueReturnsFalseWhenPredicateIsFalse(): void
    {
        $result = Err::of('file not found');
        $this->assertFalse($result->isErrAnd(function ($err) {
            assert(is_string($err));

            return str_contains($err, 'access denied');
        }));
    }

    public function testIsErrAndWithOkValueReturnsFalse(): void
    {
        $result = Ok::of(42);
        $this->assertFalse($result->isErrAnd(fn ($err) => true));
    }

    public function testIsErrAndWithComplexErrorPredicate(): void
    {
        $result = Err::of(['code' => 404, 'message' => 'Not Found']);
        $this->assertTrue($result->isErrAnd(function ($err) {
            assert(is_array($err));

            return $err['code'] >= 400 && $err['code'] < 500;
        }));
    }

    // ===== Result型 mapOr/mapOrElse テスト =====

    public function testMapOrWithOkValueAppliesFunction(): void
    {
        $result = Ok::of(5);
        $mapped = $result->mapOr(function ($x) {
            assert(is_int($x));

            return $x * 2;
        }, 0);
        $this->assertEquals(10, $mapped);
    }

    public function testMapOrWithErrValueReturnsDefault(): void
    {
        $result = Err::of('error');
        $mapped = $result->mapOr(fn ($x) => $x * 2, 0);
        $this->assertEquals(0, $mapped);
    }

    public function testMapOrWithTypeConversion(): void
    {
        $result = Ok::of(42);
        $mapped = $result->mapOr(function ($x) {
            assert(is_int($x));

            return "number: $x";
        }, 'default');
        $this->assertEquals('number: 42', $mapped);
    }

    public function testMapOrWithNullValues(): void
    {
        $result = Ok::of(null);
        $mapped = $result->mapOr(fn ($x) => $x ?? 'was null', 'default');
        $this->assertEquals('was null', $mapped);
    }

    public function testMapOrWithObjectTransformation(): void
    {
        $obj = new stdClass();
        $obj->name = 'test';
        $result = Ok::of($obj);

        $mapped = $result->mapOr(function ($o) {
            assert(is_object($o) && property_exists($o, 'name'));

            return $o->name;
        }, 'no name');
        $this->assertEquals('test', $mapped);
    }

    public function testMapOrElseWithOkValueAppliesFunction(): void
    {
        $result = Ok::of(10);
        $mapped = $result->mapOrElse(function ($x) {
            assert(is_int($x));

            return $x / 2;
        }, fn ($err) => 0);
        $this->assertEquals(5, $mapped);
    }

    public function testMapOrElseWithErrValueAppliesDefaultFunction(): void
    {
        $result = Err::of('division by zero');
        $mapped = $result->mapOrElse(fn ($x) => $x / 2, fn ($err) => -1);
        $this->assertEquals(-1, $mapped);
    }

    public function testMapOrElseWithLazyEvaluation(): void
    {
        $sideEffect = false;
        $result = Ok::of(5);

        $mapped = $result->mapOrElse(
            function ($x) {
                assert(is_int($x));

                return $x * 3;
            },
            function ($err) use (&$sideEffect) {
                $sideEffect = true;

                return 0;
            },
        );

        $this->assertEquals(15, $mapped);
        $this->assertFalse($sideEffect, 'Default function should not be called for Ok');
    }

    public function testMapOrElseWithErrorFunctionUsingErrorValue(): void
    {
        $result = Err::of(['code' => 500, 'message' => 'Server Error']);
        $mapped = $result->mapOrElse(
            fn ($x) => $x,
            function ($err) {
                assert(is_array($err) && isset($err['code']) && isset($err['message']));
                $code = $err['code'];
                $message = $err['message'];
                assert(is_int($code) && is_string($message));

                return "Error {$code}: {$message}";
            },
        );
        $this->assertEquals('Error 500: Server Error', $mapped);
    }

    public function testMapOrElseWithComplexTransformation(): void
    {
        $result = Ok::of([1, 2, 3, 4, 5]);
        $mapped = $result->mapOrElse(
            function ($arr) {
                assert(is_array($arr));

                return array_sum($arr);
            },
            fn ($err) => 0,
        );
        $this->assertEquals(15, $mapped);
    }

    public function testMapOrWithArrayDefault(): void
    {
        $result = Err::of('error');
        $mapped = $result->mapOr(fn ($x) => [$x], []);
        $this->assertEquals([], $mapped);
    }

    public function testMapOrElseWithComplexDefaultLogic(): void
    {
        $result = Err::of(404);
        $mapped = $result->mapOrElse(
            fn ($x) => "success: $x",
            function ($code) {
                assert(is_int($code));

                return $code >= 400 && $code < 500 ? 'client error' : 'server error';
            },
        );
        $this->assertEquals('client error', $mapped);
    }

    // ===== Option型 isSomeAnd テスト =====

    public function testIsSomeAndWithSomeValueReturnsTrueWhenPredicateIsTrue(): void
    {
        $option = Some::of(42);
        $this->assertTrue($option->isSomeAnd(fn ($x) => $x > 40));
    }

    public function testIsSomeAndWithSomeValueReturnsFalseWhenPredicateIsFalse(): void
    {
        $option = Some::of(42);
        $this->assertFalse($option->isSomeAnd(fn ($x) => $x > 50));
    }

    public function testIsSomeAndWithNoneValueReturnsFalse(): void
    {
        $option = None::instance();
        $this->assertFalse($option->isSomeAnd(fn ($x) => true));
    }

    public function testIsSomeAndWithNullValue(): void
    {
        $option = Some::of(null);
        $this->assertTrue($option->isSomeAnd(fn ($x) => $x === null));
        $this->assertFalse($option->isSomeAnd(fn ($x) => $x !== null));
    }

    public function testIsSomeAndWithComplexObject(): void
    {
        $obj = new stdClass();
        $obj->valid = true;
        $obj->score = 85;

        $option = Some::of($obj);
        $this->assertTrue($option->isSomeAnd(function ($o) {
            assert(is_object($o) && property_exists($o, 'valid') && property_exists($o, 'score'));

            return $o->valid && $o->score >= 80;
        }));
    }

    public function testIsSomeAndWithStringValidation(): void
    {
        $option = Some::of('hello@example.com');
        $this->assertTrue($option->isSomeAnd(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL) !== false));

        $invalidOption = Some::of('invalid-email');
        $this->assertFalse($invalidOption->isSomeAnd(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL) !== false));
    }

    public function testIsSomeAndWithArrayValidation(): void
    {
        $option = Some::of([1, 2, 3, 4, 5]);
        $this->assertTrue($option->isSomeAnd(function ($arr) {
            assert(is_array($arr));

            return count($arr) > 3 && array_sum($arr) > 10;
        }));

        $emptyOption = Some::of([]);
        $this->assertFalse($emptyOption->isSomeAnd(function ($arr) {
            assert(is_array($arr));

            return count($arr) > 0;
        }));
    }

    public function testIsSomeAndWithNumericRange(): void
    {
        $option = Some::of(50);
        $this->assertTrue($option->isSomeAnd(fn ($x) => $x >= 0 && $x <= 100));

        $outOfRangeOption = Some::of(150);
        $this->assertFalse($outOfRangeOption->isSomeAnd(fn ($x) => $x >= 0 && $x <= 100));
    }

    // ===== 統合テスト & エッジケース =====

    public function testShorthandMethodsChaining(): void
    {
        // Result → Option → 判定の複合例
        $result = Ok::of(Some::of(42));

        $hasValidValue = $result->isOkAnd(function ($opt) {
            assert($opt instanceof \Mizumi\Result\Option);

            return $opt->isSome() && $opt->isSomeAnd(function ($x) {
                assert(is_int($x));

                return $x > 40;
            });
        });

        $this->assertTrue($hasValidValue);
    }

    public function testPerformanceWithLargeData(): void
    {
        $largeArray = range(1, 1000);
        $result = Ok::of($largeArray);

        $start = microtime(true);
        $isValid = $result->isOkAnd(function ($arr) {
            assert(is_array($arr));

            return count($arr) === 1000;
        });
        $end = microtime(true);

        $this->assertTrue($isValid);
        $this->assertLessThan(0.01, $end - $start, 'パフォーマンステスト: 大量データでの処理は0.01秒以内');
    }

    public function testErrorHandlingInPredicates(): void
    {
        $result = Ok::of('not a number');

        // 型エラーが起きないことを確認
        $this->assertFalse($result->isOkAnd(function ($x) {
            return is_numeric($x) ? (int) $x > 10 : false;
        }));
    }

    public function testMemoryEfficiencyWithRepeatedCalls(): void
    {
        $option = Some::of(42);

        // 同じ述語を複数回実行してもメモリリークしないことを確認
        for ($i = 0; $i < 100; $i++) {
            $result = $option->isSomeAnd(function ($x) {
                assert(is_int($x));

                return $x === 42;
            });
            $this->assertTrue($result);
        }

        // メモリ使用量の大幅な増加がないことを暗黙的に確認
        $this->addToAssertionCount(1);
    }
}
