<?php

declare(strict_types=1);

namespace Mizumi\Result\Tests;

use Mizumi\Result\Exception\UnwrapException;
use Mizumi\Result\None;
use Mizumi\Result\Some;
use PHPUnit\Framework\TestCase;

/**
 * Basic functionality tests for Option type
 */
final class OptionBasicTest extends TestCase
{
    public function testSomeIsSome(): void
    {
        $option = Some::of(42);
        $this->assertTrue($option->isSome());
        $this->assertFalse($option->isNone());
    }

    public function testNoneIsNone(): void
    {
        $option = None::instance();
        $this->assertFalse($option->isSome());
        $this->assertTrue($option->isNone());
    }

    public function testSomeUnwrap(): void
    {
        $option = Some::of('hello');
        $this->assertSame('hello', $option->unwrap());
    }

    public function testNoneUnwrapThrows(): void
    {
        $option = None::instance();
        $this->expectException(UnwrapException::class);
        $option->unwrap();
    }

    public function testSomeExpect(): void
    {
        $option = Some::of(123);
        $this->assertSame(123, $option->expect('Should not throw'));
    }

    public function testNoneExpectThrows(): void
    {
        $option = None::instance();
        $this->expectException(UnwrapException::class);
        $this->expectExceptionMessage('Value not found');
        $option->expect('Value not found');
    }

    public function testSomeUnwrapOr(): void
    {
        $option = Some::of('value');
        $this->assertSame('value', $option->unwrapOr('default'));
    }

    public function testNoneUnwrapOr(): void
    {
        $option = None::instance();
        $this->assertSame('default', $option->unwrapOr('default'));
    }

    public function testSomeUnwrapOrElse(): void
    {
        $option = Some::of(100);
        $this->assertSame(100, $option->unwrapOrElse(fn () => 200));
    }

    public function testNoneUnwrapOrElse(): void
    {
        $option = None::instance();
        $this->assertSame(200, $option->unwrapOrElse(fn () => 200));
    }

    public function testSomeMap(): void
    {
        $option = Some::of(5);
        $mapped = $option->map(function (mixed $x): int {
            assert(is_int($x));

            return $x * 2;
        });

        $this->assertTrue($mapped->isSome());
        $this->assertSame(10, $mapped->unwrap());
    }

    public function testNoneMap(): void
    {
        $option = None::instance();
        $mapped = $option->map(fn ($x) => $x * 2);

        $this->assertTrue($mapped->isNone());
    }

    public function testSomeMapOr(): void
    {
        $option = Some::of(3);
        $result = $option->mapOr(function (mixed $x): int {
            assert(is_int($x));

            return $x * 3;
        }, 'default');

        $this->assertSame(9, $result);
    }

    public function testNoneMapOr(): void
    {
        $option = None::instance();
        $result = $option->mapOr(fn ($x) => $x * 3, 'default');

        $this->assertSame('default', $result);
    }

    public function testSomeMapOrElse(): void
    {
        $option = Some::of(4);
        $result = $option->mapOrElse(function (mixed $x): int {
            assert(is_int($x));

            return $x + 1;
        }, fn () => 'fallback');

        $this->assertSame(5, $result);
    }

    public function testNoneMapOrElse(): void
    {
        $option = None::instance();
        $result = $option->mapOrElse(fn ($x) => $x + 1, fn () => 'fallback');

        $this->assertSame('fallback', $result);
    }

    public function testSomeAndThen(): void
    {
        $option = Some::of(10);
        $result = $option->andThen(function (mixed $x) {
            assert(is_int($x));

            return Some::of((float) $x / 2);
        });

        $this->assertTrue($result->isSome());
        $this->assertSame(5.0, $result->unwrap());
    }

    public function testSomeAndThenToNone(): void
    {
        $option = Some::of(10);
        $result = $option->andThen(fn ($x) => None::instance());

        $this->assertTrue($result->isNone());
    }

    public function testNoneAndThen(): void
    {
        $option = None::instance();
        $result = $option->andThen(fn ($x) => Some::of($x * 2));

        $this->assertTrue($result->isNone());
    }

    public function testSomeFilterTrue(): void
    {
        $option = Some::of(15);
        $result = $option->filter(fn ($x) => $x > 10);

        $this->assertTrue($result->isSome());
        $this->assertSame(15, $result->unwrap());
    }

    public function testSomeFilterFalse(): void
    {
        $option = Some::of(5);
        $result = $option->filter(fn ($x) => $x > 10);

        $this->assertTrue($result->isNone());
    }

    public function testNoneFilter(): void
    {
        $option = None::instance();
        $result = $option->filter(fn ($x) => true);

        $this->assertTrue($result->isNone());
    }

    public function testSomeInspect(): void
    {
        $inspected = null;
        $option = Some::of('test');
        $result = $option->inspect(function ($value) use (&$inspected): void {
            $inspected = $value;
        });

        $this->assertSame('test', $inspected);
        $this->assertSame($option, $result);
    }

    public function testNoneInspect(): void
    {
        $called = false;
        $option = None::instance();
        $result = $option->inspect(function ($value) use (&$called): void {
            $called = true;
        });

        $this->assertFalse($called);
        $this->assertSame($option, $result);
    }

    public function testSomeOr(): void
    {
        $option1 = Some::of('first');
        $option2 = Some::of('second');
        $result = $option1->or($option2);

        $this->assertTrue($result->isSome());
        $this->assertSame('first', $result->unwrap());
    }

    public function testNoneOr(): void
    {
        $option1 = None::instance();
        $option2 = Some::of('second');
        $result = $option1->or($option2);

        $this->assertTrue($result->isSome());
        $this->assertSame('second', $result->unwrap());
    }

    public function testSomeOrElse(): void
    {
        $option = Some::of('value');
        $result = $option->orElse(fn () => Some::of('fallback'));

        $this->assertTrue($result->isSome());
        $this->assertSame('value', $result->unwrap());
    }

    public function testNoneOrElse(): void
    {
        $option = None::instance();
        $result = $option->orElse(fn () => Some::of('fallback'));

        $this->assertTrue($result->isSome());
        $this->assertSame('fallback', $result->unwrap());
    }

    public function testSomeAnd(): void
    {
        $option1 = Some::of('first');
        $option2 = Some::of('second');
        $result = $option1->and($option2);

        $this->assertTrue($result->isSome());
        $this->assertSame('second', $result->unwrap());
    }

    public function testNoneAnd(): void
    {
        $option1 = None::instance();
        $option2 = Some::of('second');
        $result = $option1->and($option2);

        $this->assertTrue($result->isNone());
    }

    public function testSomeContains(): void
    {
        $option = Some::of(42);

        $this->assertTrue($option->contains(42));
        $this->assertFalse($option->contains(43));
        $this->assertFalse($option->contains('42'));
    }

    public function testNoneContains(): void
    {
        $option = None::instance();

        // @phpstan-ignore method.impossibleType
        $this->assertFalse($option->contains(42));
        // @phpstan-ignore method.impossibleType
        $this->assertFalse($option->contains(null));
    }

    public function testNoneInstance(): void
    {
        $none1 = None::instance();
        $none2 = None::instance();

        $this->assertSame($none1, $none2);
    }
}
