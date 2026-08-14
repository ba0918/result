<?php

declare(strict_types=1);

namespace ba0918\Result\Exception;

use Exception;

/**
 * Exception thrown by unwrap methods of Result/Option
 *
 * This exception is thrown when a value cannot be retrieved in situations where it is expected.
 *
 * ## Result type occurrence cases
 * - When unwrap() is called on an Err value
 * - When unwrapErr() is called on an Ok value
 * - When thrown with a message specified by expect()
 *
 * ## Option type occurrence cases
 * - When unwrap() is called on a None value
 * - When thrown with a message specified by expect()
 *
 * ## Error message format
 * - Result type: "Called unwrap() on an Err value: [error content]"
 * - Option type: "None value" or specified custom message
 *
 * ## Usage example
 * ```php
 * $result = Err::of("An error occurred");
 * try {
 *     $value = $result->unwrap(); // UnwrapException is thrown
 * } catch (UnwrapException $e) {
 *     echo $e->getMessage(); // "Called unwrap() on an Err value: An error occurred"
 * }
 *
 * $option = None::instance();
 * try {
 *     $value = $option->expect("Value is required"); // UnwrapException is thrown
 * } catch (UnwrapException $e) {
 *     echo $e->getMessage(); // "Value is required"
 * }
 * ```
 *
 * @package ba0918\Result\Exception
 */
final class UnwrapException extends Exception
{
}
