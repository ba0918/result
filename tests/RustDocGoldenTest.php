<?php

declare(strict_types=1);

namespace ba0918\Result\Tests;

use ba0918\Result\Err;
use ba0918\Result\None;
use ba0918\Result\Ok;
use ba0918\Result\Option;
use ba0918\Result\Result;
use ba0918\Result\Some;
use PHPUnit\Framework\TestCase;

/**
 * Rust標準ライブラリのdoc exampleを正準仕様として移植したゴールデンテスト
 *
 * 各テストはRustのResult/Option型の公式ドキュメント（std::result / std::option）に
 * 記載されている実行例をPHPに翻訳したもの。自己参照的なテスト記述から脱却し、
 * 実装がRustの仕様と一致することを独立に検証する。
 *
 * @see https://doc.rust-lang.org/std/result/
 * @see https://doc.rust-lang.org/std/option/
 */
final class RustDocGoldenTest extends TestCase
{
    // Result型のdoc example

    public function testResultIsOkAndIsErr(): void
    {
        // Rust: let x: Result<i32, &str> = Ok(-3);
        //       assert_eq!(x.is_ok(), true); assert_eq!(x.is_err(), false);
        $x = Ok::of(-3);
        $this->assertTrue($x->isOk());
        $this->assertFalse($x->isErr());

        // Rust: let x: Result<i32, &str> = Err("Some error message");
        //       assert_eq!(x.is_ok(), false); assert_eq!(x.is_err(), true);
        $x = Err::of('Some error message');
        $this->assertFalse($x->isOk());
        $this->assertTrue($x->isErr());
    }

    public function testResultMap(): void
    {
        // Rust: let x = Ok("hello");
        //       assert_eq!(x.map(|s| s.len()), Ok(5));
        $x = Ok::of('hello');
        $mapped = $x->map(function ($s) {
            assert(is_string($s));

            return strlen($s);
        });

        $this->assertTrue($mapped->isOk());
        $this->assertSame(5, $mapped->unwrap());
    }

    public function testResultMapErr(): void
    {
        // Rust: let x = Err("hello");
        //       assert_eq!(x.map_err(|s| s.len()), Err(5));
        $x = Err::of('hello');
        $mapped = $x->mapErr(function ($s) {
            assert(is_string($s));

            return strlen($s);
        });

        $this->assertTrue($mapped->isErr());
        $this->assertSame(5, $mapped->unwrapErr());

        // Rust: let x = Ok("hello");
        //       assert_eq!(x.map_err(|s| s.len()), Ok("hello"));
        $x = Ok::of('hello');
        $mapped = $x->mapErr(function ($s) {
            return strlen($s);
        });

        $this->assertTrue($mapped->isOk());
        $this->assertSame('hello', $mapped->unwrap());
    }

    public function testResultMapOr(): void
    {
        // Rust: let x = Ok("foo");
        //       assert_eq!(x.map_or(42, |v| v.len()), 3);
        $x = Ok::of('foo');
        $this->assertSame(3, $x->mapOr(function ($v) {
            assert(is_string($v));

            return strlen($v);
        }, 42));

        // Rust: let x: Result<&str, &str> = Err("bar");
        //       assert_eq!(x.map_or(42, |v| v.len()), 42);
        $x = Err::of('bar');
        $this->assertSame(42, $x->mapOr(function ($v) {
            return strlen($v);
        }, 42));
    }

    public function testResultMapOrElse(): void
    {
        // Rust: let k = 21;
        //       let x = Ok("foo");
        //       assert_eq!(x.map_or_else(|e| k * 2, |v| v.len()), 3);
        $k = 21;
        $x = Ok::of('foo');
        $this->assertSame(3, $x->mapOrElse(function ($v) {
            assert(is_string($v));

            return strlen($v);
        }, function () use ($k): int {
            return $k * 2;
        }));

        // Rust: let x: Result<&str, &str> = Err("bar");
        //       assert_eq!(x.map_or_else(|e| k * 2, |v| v.len()), 42);
        $x = Err::of('bar');
        $this->assertSame(42, $x->mapOrElse(function ($v) {
            return strlen($v);
        }, function () use ($k): int {
            return $k * 2;
        }));
    }

    public function testResultAndThen(): void
    {
        // Rust: fn sq_then_to_string(x: u32) -> Result<String, Never> { ... }
        //       assert_eq!(Ok(2).and_then(sq_then_to_string), Ok(4.to_string()));
        $result = (Ok::of(2))
            ->andThen(function ($x) {
                assert(is_int($x));

                return Ok::of($x * $x);
            });

        $this->assertTrue($result->isOk());
        $this->assertSame(4, $result->unwrap());

        // Rust: assert_eq!(Err(10).and_then(sq_then_to_string), Err(10));
        $result = (Err::of(10))
            ->andThen(function ($x) {
                return Ok::of($x * $x);
            });

        $this->assertTrue($result->isErr());
        $this->assertSame(10, $result->unwrapErr());
    }

    public function testResultUnwrapOr(): void
    {
        // Rust: let x = Ok(9);
        //       assert_eq!(x.unwrap_or(2), 9);
        $x = Ok::of(9);
        $this->assertSame(9, $x->unwrapOr(2));

        // Rust: let x = Err(9);
        //       assert_eq!(x.unwrap_or(2), 2);
        $x = Err::of(9);
        $this->assertSame(2, $x->unwrapOr(2));
    }

    public function testResultUnwrapOrElse(): void
    {
        // Rust: fn count(x: &String) -> usize { x.len() }
        //       assert_eq!(Ok(2).unwrap_or_else(count), 2);
        $x = Ok::of(2);
        $this->assertSame(2, $x->unwrapOrElse(function ($s) {
            return strlen($s);
        }));

        // Rust: assert_eq!(Err("foo").unwrap_or_else(count), 3);
        $x = Err::of('foo');
        $this->assertSame(3, $x->unwrapOrElse(function ($s) {
            assert(is_string($s));

            return strlen($s);
        }));
    }

    public function testResultExpectAndExpectErr(): void
    {
        // Rust: let x = Ok("value");
        //       assert_eq!(x.expect("the world is ending"), "value");
        $x = Ok::of('value');
        $this->assertSame('value', $x->expect('the world is ending'));

        // Rust: let x = Err("emergency");
        //       assert_eq!(x.expect_err("testing expect_err"), "emergency");
        $x = Err::of('emergency');
        $this->assertSame('emergency', $x->expectErr('testing expect_err'));
    }

    public function testResultUnwrapErr(): void
    {
        // Rust: let x = Err("emergency");
        //       assert_eq!(x.unwrap_err(), "emergency");
        $x = Err::of('emergency');
        $this->assertSame('emergency', $x->unwrapErr());
    }

    public function testResultAnd(): void
    {
        // Rust: let x: Result<u32, &str> = Ok(2);
        //       let y: Result<&str, &str> = Err("late error");
        //       assert_eq!(x.and(y), Err("late error"));
        $x = Ok::of(2);
        $y = Err::of('late error');
        $result = $x->and($y);

        $this->assertTrue($result->isErr());
        $this->assertSame('late error', $result->unwrapErr());

        // Rust: let x: Result<u32, &str> = Err("early error");
        //       let y: Result<&str, &str> = Ok("foo");
        //       assert_eq!(x.and(y), Err("early error"));
        $x = Err::of('early error');
        $y = Ok::of('foo');
        $result = $x->and($y);

        $this->assertTrue($result->isErr());
        $this->assertSame('early error', $result->unwrapErr());
    }

    public function testResultOr(): void
    {
        // Rust: let x: Result<u32, &str> = Err(2);
        //       let y: Result<u32, &str> = Ok(100);
        //       assert_eq!(x.or(y), Ok(100));
        $x = Err::of(2);
        $y = Ok::of(100);
        $result = $x->or($y);

        $this->assertTrue($result->isOk());
        $this->assertSame(100, $result->unwrap());

        // Rust: let x: Result<u32, &str> = Ok(2);
        //       let y: Result<u32, &str> = Err(100);
        //       assert_eq!(x.or(y), Ok(2));
        $x = Ok::of(2);
        $y = Err::of(100);
        $result = $x->or($y);

        $this->assertTrue($result->isOk());
        $this->assertSame(2, $result->unwrap());
    }

    public function testResultOrElse(): void
    {
        // Rust: fn sq(x: u32) -> Result<u32, u32> { Ok(x * x) }
        //       fn err(x: u32) -> Result<u32, u32> { Err(x) }
        //       assert_eq!(Ok(2).or_else(sq).or_else(sq), Ok(2));
        $result = (Ok::of(2))
            ->orElse(function ($x) {
                return Ok::of($x * $x);
            })
            ->orElse(function ($x) {
                return Ok::of($x * $x);
            });

        $this->assertTrue($result->isOk());
        $this->assertSame(2, $result->unwrap());

        // Rust: assert_eq!(Err(2).or_else(sq).or_else(sq), Ok(4));
        $result = (Err::of(2))
            ->orElse(function ($x) {
                assert(is_int($x));

                return Ok::of($x * $x);
            })
            ->orElse(function ($x) {
                // 1段目でOkに戻るため、このクロージャの引数は型上 never
                return Ok::of($x * $x);
            });

        $this->assertTrue($result->isOk());
        $this->assertSame(4, $result->unwrap());

        // Rust: assert_eq!(Err(2).or_else(err).or_else(err), Err(2));
        $result = (Err::of(2))
            ->orElse(function ($x) {
                return Err::of($x);
            })
            ->orElse(function ($x) {
                return Err::of($x);
            });

        $this->assertTrue($result->isErr());
        $this->assertSame(2, $result->unwrapErr());
    }

    public function testResultContains(): void
    {
        // Rust: let x: Result<u32, &str> = Ok(2);
        //       assert_eq!(x.contains(&2), true);
        //       assert_eq!(x.contains(&3), false);
        $x = Ok::of(2);
        $this->assertTrue($x->contains(2));
        $this->assertFalse($x->contains(3));

        // Rust: let x: Result<u32, &str> = Err("Some error message");
        //       assert_eq!(x.contains(&2), false);
        $x = Err::of('Some error message');
        $this->assertFalse($x->contains(2));
    }

    public function testResultInspectAndInspectErr(): void
    {
        // Rust: let x = Ok(2); x.inspect(|x| println!("got: {x}"));
        $inspected = [];
        $x = Ok::of(2);
        $returned = $x->inspect(function ($value) use (&$inspected): void {
            $inspected[] = $value;
        });

        $this->assertSame([2], $inspected);
        $this->assertSame($x, $returned);

        // Rust: let x = Err(2); x.inspect_err(|x| println!("got: {x}"));
        $inspected = [];
        $x = Err::of(2);
        $returned = $x->inspectErr(function ($error) use (&$inspected): void {
            assert(is_int($error));
            $inspected[] = $error;
        });

        $this->assertSame([2], $inspected);
        $this->assertSame($x, $returned);
    }

    // Option型のdoc example

    public function testOptionIsSomeAndIsNone(): void
    {
        // Rust: let x = Some(2);
        //       assert_eq!(x.is_some(), true); assert_eq!(x.is_none(), false);
        $x = Some::of(2);
        $this->assertTrue($x->isSome());
        $this->assertFalse($x->isNone());

        // Rust: let x: Option<i32> = None;
        //       assert_eq!(x.is_some(), false); assert_eq!(x.is_none(), true);
        $x = None::instance();
        $this->assertFalse($x->isSome());
        $this->assertTrue($x->isNone());
    }

    public function testOptionMap(): void
    {
        // Rust: let x = Some("Hello, world!");
        //       assert_eq!(x.map(|s| s.len()), Some(13));
        $x = Some::of('Hello, world!');
        $mapped = $x->map(function ($s) {
            assert(is_string($s));

            return strlen($s);
        });

        $this->assertTrue($mapped->isSome());
        $this->assertSame(13, $mapped->unwrap());

        // Rust: let x: Option<&str> = None;
        //       assert_eq!(x.map(|s| s.len()), None);
        $x = None::instance();
        $mapped = $x->map(function ($s) {
            return strlen($s);
        });

        $this->assertTrue($mapped->isNone());
    }

    public function testOptionMapOr(): void
    {
        // Rust: let x = Some("foo");
        //       assert_eq!(x.map_or(42, |v| v.len()), 3);
        $x = Some::of('foo');
        $this->assertSame(3, $x->mapOr(function ($v) {
            assert(is_string($v));

            return strlen($v);
        }, 42));

        // Rust: let x: Option<&str> = None;
        //       assert_eq!(x.map_or(42, |v| v.len()), 42);
        $x = None::instance();
        $this->assertSame(42, $x->mapOr(function ($v) {
            return strlen($v);
        }, 42));
    }

    public function testOptionMapOrElse(): void
    {
        // Rust: let k = 21;
        //       let x = Some("foo");
        //       assert_eq!(x.map_or_else(|| 2 * k, |v| v.len()), 3);
        $k = 21;
        $x = Some::of('foo');
        $this->assertSame(3, $x->mapOrElse(function ($v) {
            assert(is_string($v));

            return strlen($v);
        }, function () use ($k): int {
            return 2 * $k;
        }));

        // Rust: let x: Option<&str> = None;
        //       assert_eq!(x.map_or_else(|| 2 * k, |v| v.len()), 42);
        $x = None::instance();
        $this->assertSame(42, $x->mapOrElse(function ($v) {
            return strlen($v);
        }, function () use ($k): int {
            return 2 * $k;
        }));
    }

    public function testOptionAndThen(): void
    {
        // Rust: fn sq(x: u32) -> Option<u32> { Some(x * x) }
        //       fn nope(_: u32) -> Option<u32> { None }
        //       assert_eq!(Some(2).and_then(sq).and_then(sq), Some(16));
        $result = Some::of(2)
            ->andThen(function ($x) {
                assert(is_int($x));

                return Some::of($x * $x);
            })
            ->andThen(function ($x) {
                assert(is_int($x));

                return Some::of($x * $x);
            });

        $this->assertTrue($result->isSome());
        $this->assertSame(16, $result->unwrap());

        // Rust: assert_eq!(Some(2).and_then(nope), None);
        $result = Some::of(2)->andThen(function ($x) {
            assert(is_int($x));

            return None::instance();
        });
        $this->assertTrue($result->isNone());

        // Rust: assert_eq!(None.and_then(sq), None);
        $result = None::instance()->andThen(function ($x) {
            return Some::of($x * $x);
        });
        $this->assertTrue($result->isNone());
    }

    public function testOptionFilter(): void
    {
        // Rust: fn is_even(n: &i32) -> bool { n % 2 == 0 }
        //       assert_eq!(Some(4).filter(is_even), Some(4));
        $result = Some::of(4)->filter(function ($n) {
            assert(is_int($n));

            return $n % 2 === 0;
        });
        $this->assertTrue($result->isSome());
        $this->assertSame(4, $result->unwrap());

        // Rust: assert_eq!(Some(3).filter(is_even), None);
        $result = Some::of(3)->filter(function ($n) {
            assert(is_int($n));

            return $n % 2 === 0;
        });
        $this->assertTrue($result->isNone());

        // Rust: assert_eq!(None.filter(is_even), None);
        $result = None::instance()->filter(function ($n) {
            // Noneでは述語は呼ばれないため、is_even相当の判定は型上 unreachable
            // @phpstan-ignore identical.alwaysFalse
            return $n % 2 === 0;
        });
        $this->assertTrue($result->isNone());
    }

    public function testOptionUnwrapOrAndUnwrapOrElse(): void
    {
        // Rust: assert_eq!(Some(9).unwrap_or(2), 9);
        //       assert_eq!(None.unwrap_or(2), 2);
        $this->assertSame(9, Some::of(9)->unwrapOr(2));
        $this->assertSame(2, None::instance()->unwrapOr(2));

        // Rust: assert_eq!(Some(9).unwrap_or_else(|| 2), 9);
        //       assert_eq!(None.unwrap_or_else(|| 2), 2);
        $this->assertSame(9, Some::of(9)->unwrapOrElse(fn (): int => 2));
        $this->assertSame(2, None::instance()->unwrapOrElse(fn (): int => 2));
    }

    public function testOptionOrAndOrElse(): void
    {
        // Rust: let x = Some(2); let y = None;
        //       assert_eq!(x.or(y), Some(2));
        $x = Some::of(2);
        $y = None::instance();
        $result = $x->or($y);
        $this->assertTrue($result->isSome());
        $this->assertSame(2, $result->unwrap());

        // Rust: let x = None; let y = Some(100);
        //       assert_eq!(x.or(y), Some(100));
        $x = None::instance();
        $y = Some::of(100);
        $result = $x->or($y);
        $this->assertTrue($result->isSome());
        $this->assertSame(100, $result->unwrap());

        // Rust: fn nobody() -> Option<&'static str> { None }
        //       fn vikings() -> Option<&'static str> { Some("vikings") }
        //       assert_eq!(None.or_else(vikings), Some("vikings"));
        //       assert_eq!(None.or_else(nobody), None);
        $result = None::instance()->orElse(fn (): Option => Some::of('vikings'));
        $this->assertTrue($result->isSome());
        $this->assertSame('vikings', $result->unwrap());

        $result = None::instance()->orElse(fn (): Option => None::instance());
        $this->assertTrue($result->isNone());
    }

    public function testOptionAnd(): void
    {
        // Rust: let x = Some(2); let y: Option<&str> = None;
        //       assert_eq!(x.and(y), None);
        $x = Some::of(2);
        $y = None::instance();
        $result = $x->and($y);
        $this->assertTrue($result->isNone());

        // Rust: let x: Option<u32> = None; let y = Some("foo");
        //       assert_eq!(x.and(y), None);
        $x = None::instance();
        $y = Some::of('foo');
        $result = $x->and($y);
        $this->assertTrue($result->isNone());

        // Rust: let x = Some(2); let y = Some(40);
        //       assert_eq!(x.and(y), Some(40));
        $x = Some::of(2);
        $y = Some::of(40);
        $result = $x->and($y);
        $this->assertTrue($result->isSome());
        $this->assertSame(40, $result->unwrap());
    }

    public function testOptionXor(): void
    {
        // Rust: let x = Some(2); let y: Option<u32> = None;
        //       assert_eq!(x.xor(y), Some(2));
        $result = Some::of(2)->xor(None::instance());
        $this->assertTrue($result->isSome());
        $this->assertSame(2, $result->unwrap());

        // Rust: let x: Option<u32> = None; let y = Some(2);
        //       assert_eq!(x.xor(y), Some(2));
        $result = None::instance()->xor(Some::of(2));
        $this->assertTrue($result->isSome());
        $this->assertSame(2, $result->unwrap());

        // Rust: let x = Some(2); let y = Some(2);
        //       assert_eq!(x.xor(y), None);
        $result = Some::of(2)->xor(Some::of(2));
        $this->assertTrue($result->isNone());

        // Rust: let x: Option<u32> = None; let y: Option<u32> = None;
        //       assert_eq!(x.xor(y), None);
        $result = None::instance()->xor(None::instance());
        $this->assertTrue($result->isNone());
    }

    public function testOptionZip(): void
    {
        // Rust: let x = Some(1); let y = Some("hi");
        //       assert_eq!(x.zip(y), Some((1, "hi")));
        $result = Some::of(1)->zip(Some::of('hi'));

        $this->assertTrue($result->isSome());
        $this->assertSame([1, 'hi'], $result->unwrap());

        // Rust: let z = None::<u8>;
        //       assert_eq!(x.zip(z), None);
        $result = Some::of(1)->zip(None::instance());
        $this->assertTrue($result->isNone());
    }

    public function testOptionContains(): void
    {
        // Rust: let x = Some(2);
        //       assert_eq!(x.contains(&2), true);
        //       assert_eq!(x.contains(&3), false);
        $x = Some::of(2);
        $this->assertTrue($x->contains(2));
        $this->assertFalse($x->contains(3));

        // Rust: let x: Option<u32> = None;
        //       assert_eq!(x.contains(&2), false);
        $x = None::instance();
        // @phpstan-ignore method.impossibleType
        $this->assertFalse($x->contains(2));
    }

    public function testOptionOkOrAndOkOrElse(): void
    {
        // Rust: let x = Some("foo");
        //       assert_eq!(x.ok_or(0), Ok("foo"));
        $x = Some::of('foo');
        $result = $x->okOr(0);
        $this->assertTrue($result->isOk());
        $this->assertSame('foo', $result->unwrap());

        // Rust: let x: Option<&str> = None;
        //       assert_eq!(x.ok_or(0), Err(0));
        $x = None::instance();
        $result = $x->okOr(0);
        $this->assertTrue($result->isErr());
        $this->assertSame(0, $result->unwrapErr());

        // Rust: let x = Some("foo");
        //       assert_eq!(x.ok_or_else(|| 0), Ok("foo"));
        $x = Some::of('foo');
        $result = $x->okOrElse(fn (): int => 0);
        $this->assertTrue($result->isOk());
        $this->assertSame('foo', $result->unwrap());

        // Rust: let x: Option<&str> = None;
        //       assert_eq!(x.ok_or_else(|| 0), Err(0));
        $x = None::instance();
        $result = $x->okOrElse(fn (): int => 0);
        $this->assertTrue($result->isErr());
        $this->assertSame(0, $result->unwrapErr());
    }

    public function testOptionFlatten(): void
    {
        // Rust: let x: Option<Option<u32>> = Some(Some(6));
        //       assert_eq!(x.flatten(), Some(6));
        $x = Some::of(Some::of(6));
        $result = $x->flatten();
        $this->assertTrue($result->isSome());
        $this->assertSame(6, $result->unwrap());

        // Rust: let x: Option<Option<u32>> = Some(None);
        //       assert_eq!(x.flatten(), None);
        $x = Some::of(None::instance());
        $result = $x->flatten();
        $this->assertTrue($result->isNone());

        // Rust: let x: Option<Option<u32>> = None;
        //       assert_eq!(x.flatten(), None);
        $x = None::instance();
        $result = $x->flatten();
        $this->assertTrue($result->isNone());
    }

    public function testOptionInspect(): void
    {
        // Rust: let x = Some(2); x.inspect(|x| println!("got: {x}"));
        $inspected = [];
        $x = Some::of(2);
        $returned = $x->inspect(function ($value) use (&$inspected): void {
            $inspected[] = $value;
        });

        $this->assertSame([2], $inspected);
        $this->assertSame($x, $returned);

        // Rust: let x: Option<i32> = None; x.inspect(|x| println!("got: {x}"));
        $inspected = [];
        $x = None::instance();
        $returned = $x->inspect(function ($value) use (&$inspected): void {
            $inspected[] = $value;
        });

        $this->assertSame([], $inspected);
        $this->assertSame($x, $returned);
    }
}
