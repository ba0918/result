<?php declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Mizumi\Result\Err;
use Mizumi\Result\Ok;
use Mizumi\Result\Result;

/**
 * 割り算を行い、結果をResult型で返す
 * @param float $a
 * @param float $b
 * @return Result<float, string> 成功すればfloat、失敗すればエラーメッセージ(string)を返す
 */
function safe_divide(float $a, float $b): Result
{
    if ($b === 0.0) {
        return new Err("Cannot divide by zero.");
    }
    return new Ok($a / $b);
}

function result_print(Result $ret)
{
    $message = match(true) {
        $ret->isOk() => "Success: " . $ret->unwrap(),
        $ret->isErr() => "Failure: " . $ret->unwrapOr("Unknown err"),
    };
    echo $message . PHP_EOL;
}

$result1 = safe_divide(100, 0);

result_print($result1);


