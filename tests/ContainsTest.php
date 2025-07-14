<?php

declare(strict_types=1);

use Mizumi\Result\Err;
use Mizumi\Result\Ok;
use PHPUnit\Framework\TestCase;

class ContainsTest extends TestCase
{
    // contains()メソッドの基本動作テスト

    public function testOkContainsWithEqualValue(): void
    {
        $ok = new Ok(42);

        $this->assertTrue($ok->contains(42));
    }

    public function testOkContainsWithDifferentValue(): void
    {
        $ok = new Ok(42);

        $this->assertFalse($ok->contains(24));
    }

    public function testOkContainsWithDifferentType(): void
    {
        $ok = new Ok(42);

        $this->assertFalse($ok->contains('42'));
        $this->assertFalse($ok->contains(42.0));
    }

    public function testOkContainsWithString(): void
    {
        $ok = new Ok('hello');

        $this->assertTrue($ok->contains('hello'));
        $this->assertFalse($ok->contains('world'));
        $this->assertFalse($ok->contains(null));
    }

    // containsErr()メソッドの基本動作テスト

    public function testErrContainsErrWithEqualError(): void
    {
        $err = new Err('database error');

        $this->assertTrue($err->containsErr('database error'));
    }

    public function testErrContainsErrWithDifferentError(): void
    {
        $err = new Err('database error');

        $this->assertFalse($err->containsErr('network error'));
    }

    public function testErrContainsErrWithDifferentType(): void
    {
        $err = new Err(404);

        $this->assertTrue($err->containsErr(404));
        $this->assertFalse($err->containsErr('404'));
    }

    // 交差テスト

    public function testOkContainsErrAlwaysFalse(): void
    {
        $ok = new Ok('success');

        $this->assertFalse($ok->containsErr('success'));
        $this->assertFalse($ok->containsErr('error'));
        $this->assertFalse($ok->containsErr(null));
    }

    public function testErrContainsAlwaysFalse(): void
    {
        $err = new Err('error');

        $this->assertFalse($err->contains('error'));
        $this->assertFalse($err->contains('success'));
        $this->assertFalse($err->contains(null));
    }

    // エッジケーステスト: null値

    public function testOkContainsWithNull(): void
    {
        $ok = new Ok(null);

        $this->assertTrue($ok->contains(null));
        $this->assertFalse($ok->contains(0));
        $this->assertFalse($ok->contains(''));
        $this->assertFalse($ok->contains(false));
    }

    public function testErrContainsErrWithNull(): void
    {
        $err = new Err(null);

        $this->assertTrue($err->containsErr(null));
        $this->assertFalse($err->containsErr(0));
        $this->assertFalse($err->containsErr(''));
        $this->assertFalse($err->containsErr(false));
    }

    // エッジケーステスト: オブジェクト参照比較

    public function testOkContainsWithObjectReference(): void
    {
        $obj = new \stdClass();
        $obj->value = 'test';
        $ok = new Ok($obj);

        $this->assertTrue($ok->contains($obj));

        $differentObj = new \stdClass();
        $differentObj->value = 'test';
        $this->assertFalse($ok->contains($differentObj));
    }

    public function testErrContainsErrWithObjectReference(): void
    {
        $errorObj = new \stdClass();
        $errorObj->message = 'error';
        $err = new Err($errorObj);

        $this->assertTrue($err->containsErr($errorObj));

        $differentErrorObj = new \stdClass();
        $differentErrorObj->message = 'error';
        $this->assertFalse($err->containsErr($differentErrorObj));
    }

    // エッジケーステスト: 配列の厳密比較

    public function testOkContainsWithArray(): void
    {
        $array = [1, 2, 3];
        $ok = new Ok($array);

        $this->assertTrue($ok->contains([1, 2, 3]));
        $this->assertFalse($ok->contains(['1', '2', '3']));
        $this->assertFalse($ok->contains([1, 2, 3, 4]));
        $this->assertFalse($ok->contains([3, 2, 1]));
    }

    public function testErrContainsErrWithArray(): void
    {
        $errorArray = ['code' => 500, 'message' => 'server error'];
        $err = new Err($errorArray);

        $this->assertTrue($err->containsErr(['code' => 500, 'message' => 'server error']));
        $this->assertFalse($err->containsErr(['code' => '500', 'message' => 'server error']));
        $this->assertFalse($err->containsErr(['message' => 'server error', 'code' => 500]));
    }

    // エッジケーステスト: 数値型の厳密比較

    public function testOkContainsWithNumericTypes(): void
    {
        $intOk = new Ok(42);
        $this->assertTrue($intOk->contains(42));
        $this->assertFalse($intOk->contains(42.0));
        $this->assertFalse($intOk->contains('42'));

        $floatOk = new Ok(42.5);
        $this->assertTrue($floatOk->contains(42.5));
        $this->assertFalse($floatOk->contains(42));
        $this->assertFalse($floatOk->contains('42.5'));
    }

    // エッジケーステスト: bool値の厳密比較

    public function testOkContainsWithBooleanTypes(): void
    {
        $trueOk = new Ok(true);
        $this->assertTrue($trueOk->contains(true));
        $this->assertFalse($trueOk->contains(1));
        $this->assertFalse($trueOk->contains('true'));

        $falseOk = new Ok(false);
        $this->assertTrue($falseOk->contains(false));
        $this->assertFalse($falseOk->contains(0));
        $this->assertFalse($falseOk->contains(''));
        $this->assertFalse($falseOk->contains(null));
    }

    // 複合テスト: 複雑なデータ構造

    public function testOkContainsWithComplexData(): void
    {
        $complexData = [
            'user' => ['id' => 1, 'name' => 'Alice'],
            'permissions' => ['read', 'write'],
            'active' => true,
        ];
        $ok = new Ok($complexData);

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

    // パフォーマンステスト: 大きなデータ構造

    public function testContainsPerformanceWithLargeData(): void
    {
        $largeArray = range(1, 1000);
        $ok = new Ok($largeArray);

        $startTime = microtime(true);
        $result = $ok->contains($largeArray);
        $endTime = microtime(true);

        $this->assertTrue($result);
        $this->assertLessThan(0.01, $endTime - $startTime, 'contains() should be fast for large data');
    }

    // 型安全性テスト

    public function testContainsReturnType(): void
    {
        $ok = new Ok('test');
        $err = new Err('error');

        // 戻り値の型が適切に bool として動作することを確認
        $this->assertTrue($ok->contains('test'));
        $this->assertFalse($ok->containsErr('error'));
        $this->assertFalse($err->contains('test'));
        $this->assertTrue($err->containsErr('error'));
    }
}
