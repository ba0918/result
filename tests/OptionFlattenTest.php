<?php

declare(strict_types=1);

namespace ba0918\Result\Tests;

use ba0918\Result\None;
use ba0918\Result\Some;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Tests for Option type flatten() method
 */
final class OptionFlattenTest extends TestCase
{
    // Basic behavior tests

    public function testSomeSomeFlattensToSome(): void
    {
        // Some(Some(value)) → Some(value)
        $inner = Some::of(42);
        $outer = Some::of($inner);
        $flattened = $outer->flatten();

        $this->assertInstanceOf(Some::class, $flattened);
        $this->assertSame($inner, $flattened);
        $this->assertSame(42, $flattened->unwrap());
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
        $this->assertSame('string_value', $flattened->unwrap());
    }

    // Edge case tests

    public function testDeepNesting(): void
    {
        // Deep nesting: Some(Some(Some(value))) flattens only one level
        $deepest = Some::of(42);
        $middle = Some::of($deepest);
        $outer = Some::of($middle);
        $flattened = $outer->flatten();

        $this->assertInstanceOf(Some::class, $flattened);
        $this->assertSame($middle, $flattened);
        // Further flatten() calls reach the deepest level
        $this->assertSame($deepest, $flattened->flatten());
        $this->assertSame(42, $flattened->flatten()->unwrap());
    }

    public function testSomeWithNullValue(): void
    {
        // Handling a null value: Some(null)
        $someNull = Some::of(null);
        $flattened = $someNull->flatten();

        $this->assertSame($someNull, $flattened);
        $this->assertNull($flattened->unwrap()); /** @phpstan-ignore method.alreadyNarrowedType (unwrap of Some(null) is expected to be null) */
    }

    public function testSomeWithZeroValue(): void
    {
        // Handling a zero value: Some(0)
        $someZero = Some::of(0);
        $flattened = $someZero->flatten();

        $this->assertSame($someZero, $flattened);
        $this->assertSame(0, $flattened->unwrap());
    }

    public function testSomeWithEmptyString(): void
    {
        // Handling an empty string: Some("")
        $someEmpty = Some::of('');
        $flattened = $someEmpty->flatten();

        $this->assertSame($someEmpty, $flattened);
        $this->assertSame('', $flattened->unwrap());
    }

    public function testSomeWithFalseValue(): void
    {
        // Handling a false value: Some(false)
        $someFalse = Some::of(false);
        $flattened = $someFalse->flatten();

        $this->assertSame($someFalse, $flattened);
        $this->assertFalse($flattened->unwrap());
    }

    public function testSomeWithArrayValue(): void
    {
        // Handling an array value: Some([1, 2, 3])
        $array = [1, 2, 3];
        $someArray = Some::of($array);
        $flattened = $someArray->flatten();

        $this->assertSame($someArray, $flattened);
        $this->assertSame($array, $flattened->unwrap());
    }

    public function testSomeWithObjectValue(): void
    {
        // Handling an object value
        $object = new stdClass();
        $object->name = 'test';
        $someObject = Some::of($object);
        $flattened = $someObject->flatten();

        $this->assertSame($someObject, $flattened);
        $this->assertSame($object, $flattened->unwrap());
    }

    // Type safety tests

    public function testFlattenReturnType(): void
    {
        // Confirm the return type
        $someOption = Some::of(Some::of('test'));
        $result = $someOption->flatten();

        $this->assertInstanceOf(Some::class, $result);
        $this->assertTrue($result->isSome());
        $this->assertSame('test', $result->unwrap());
    }

    public function testChainedFlatten(): void
    {
        // Chained flatten() tests
        $triplyNested = Some::of(Some::of(Some::of('value')));

        // First flatten
        $onceFlattened = $triplyNested->flatten();
        $this->assertInstanceOf(Some::class, $onceFlattened);
        $this->assertSame('value', $onceFlattened->flatten()->unwrap());

        // Second flatten
        $twiceFlattened = $onceFlattened->flatten();
        $this->assertInstanceOf(Some::class, $twiceFlattened);
        $this->assertSame('value', $twiceFlattened->unwrap());

        // Third flatten (no effect)
        $thriceFlattened = $twiceFlattened->flatten();
        $this->assertSame($twiceFlattened, $thriceFlattened);
    }

    // Performance tests (basic case)

    public function testFlattenPerformance(): void
    {
        // Confirm performance stays stable even with heavy nesting
        $start = microtime(true);

        for ($i = 0; $i < 1000; $i++) {
            $some = Some::of(Some::of($i));
            $flattened = $some->flatten();
            $this->assertSame($i, $flattened->unwrap());
        }

        $end = microtime(true);
        $this->assertLessThan(1.0, $end - $start, 'flatten operation must complete within 1 second');
    }

    // Practical use case tests

    public function testPracticalUseCaseWithMapAndFlatten(): void
    {
        // Flattening when map produces a doubly nested Option
        $option = Some::of(5);

        // map creates Some(Some(value))
        $mapped = $option->map(function ($x) {
            return Some::of($x * 2);
        });
        $this->assertTrue($mapped->isSome());
        $this->assertInstanceOf(Some::class, $mapped->unwrap());

        // Flatten with flatten()
        $flattened = $mapped->flatten();
        $this->assertTrue($flattened->isSome());
        $this->assertSame(10, $flattened->unwrap());
    }
}
