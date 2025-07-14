<?php declare(strict_types=1);

namespace Mizumi\Result\Exception;

/**
 * Result/Optionのunwrap系メソッドで発生する例外
 * 
 * この例外は、値が期待される状況で値が取得できない場合にスローされます。
 * 
 * ## Result型での発生ケース
 * - Err値に対してunwrap()を呼んだ場合
 * - Ok値に対してunwrapErr()を呼んだ場合  
 * - expect()で指定したメッセージと共にスローされる場合
 * 
 * ## Option型での発生ケース
 * - None値に対してunwrap()を呼んだ場合
 * - expect()で指定したメッセージと共にスローされる場合
 * 
 * ## エラーメッセージ形式
 * - Result型: "Called unwrap() on an Err value: [エラー内容]"
 * - Option型: "None value" または指定されたカスタムメッセージ
 * 
 * ## 使用例
 * ```php
 * $result = Err::of("エラーが発生しました");
 * try {
 *     $value = $result->unwrap(); // UnwrapExceptionがスローされる
 * } catch (UnwrapException $e) {
 *     echo $e->getMessage(); // "Called unwrap() on an Err value: エラーが発生しました"
 * }
 * 
 * $option = None::instance();
 * try {
 *     $value = $option->expect("値が必要です"); // UnwrapExceptionがスローされる
 * } catch (UnwrapException $e) {
 *     echo $e->getMessage(); // "値が必要です"
 * }
 * ```
 * 
 * @package Mizumi\Result\Exception
 */
final class UnwrapException extends \Exception
{
}
