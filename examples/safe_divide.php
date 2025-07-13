<?php declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Mizumi\Result\Err;
use Mizumi\Result\Ok;
use Mizumi\Result\Result;
use Mizumi\Result\Option;
use Mizumi\Result\Some;
use Mizumi\Result\None;

/**
 * 基本的な安全な割り算
 * 
 * @param float $a 被除数
 * @param float $b 除数
 * @return Result<float, string> 成功すればfloat、失敗すればエラーメッセージ
 */
function safe_divide(float $a, float $b): Result
{
    if ($b === 0.0) {
        return Err::of("Cannot divide by zero");
    }
    return Ok::of($a / $b);
}

/**
 * より詳細なエラー情報を持つ割り算
 * 
 * @param float $a 被除数
 * @param float $b 除数
 * @return Result<float, array> 成功すればfloat、失敗すれば詳細エラー情報
 */
function safe_divide_detailed(float $a, float $b): Result
{
    if ($b === 0.0) {
        return Err::of([
            'error' => 'division_by_zero',
            'message' => 'Cannot divide by zero',
            'dividend' => $a,
            'divisor' => $b,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
    if (!is_finite($a) || !is_finite($b)) {
        return Err::of([
            'error' => 'invalid_number',
            'message' => 'Cannot divide infinite or NaN values',
            'dividend' => $a,
            'divisor' => $b
        ]);
    }
    
    return Ok::of($a / $b);
}

/**
 * 文字列入力からの安全な割り算（型変換含む）
 * 
 * @param string $a 被除数（文字列）
 * @param string $b 除数（文字列）
 * @return Result<float, string> パース・計算結果
 */
function safe_divide_from_string(string $a, string $b): Result
{
    return parse_number($a)
        ->okOr("Invalid dividend: '$a'")
        ->andThen(function($dividend) use ($b) {
            return parse_number($b)
                ->okOr("Invalid divisor: '$b'")
                ->andThen(fn($divisor) => safe_divide($dividend, $divisor));
        });
}

/**
 * 数値文字列をパースしてOptionで返す
 * 
 * @param string $str 数値文字列
 * @return Option<float> 成功すれば数値、失敗すればNone
 */
function parse_number(string $str): Option
{
    $trimmed = trim($str);
    if (!is_numeric($trimmed)) {
        return None::instance();
    }
    return Some::of((float)$trimmed);
}

/**
 * 複数の割り算を連続実行（エラー時は最初のエラーで停止）
 * 
 * @param float $initial 初期値
 * @param array<float> $divisors 除数の配列
 * @return Result<float, string> 最終結果またはエラー
 */
function chain_divide(float $initial, array $divisors): Result
{
    return array_reduce(
        $divisors,
        fn(Result $acc, float $divisor) => $acc->andThen(fn($value) => safe_divide($value, $divisor)),
        Ok::of($initial)
    );
}

/**
 * 割り算の結果を文字列でフォーマット
 * 
 * @param Result $result 割り算の結果
 * @param int $precision 小数点以下の桁数
 * @return string フォーマットされた結果
 */
function format_division_result(Result $result, int $precision = 2): string
{
    return $result
        ->map(fn($value) => number_format($value, $precision))
        ->map(fn($formatted) => "Result: $formatted")
        ->unwrapOr("Error: " . $result->unwrapErr());
}

/**
 * 統計計算：平均値の安全な計算
 * 
 * @param array<float> $numbers 数値の配列
 * @return Result<float, string> 平均値またはエラー
 */
function safe_average(array $numbers): Result
{
    if (empty($numbers)) {
        return Err::of("Cannot calculate average of empty array");
    }
    
    $sum = array_sum($numbers);
    $count = count($numbers);
    
    return safe_divide($sum, $count);
}

/**
 * 実用例：価格計算（税込み価格の計算）
 * 
 * @param float $basePrice 基本価格
 * @param float $taxRate 税率（例：0.10 = 10%）
 * @return Result<array, string> 税込み価格情報またはエラー
 */
function calculate_tax_inclusive_price(float $basePrice, float $taxRate): Result
{
    if ($basePrice < 0) {
        return Err::of("Base price cannot be negative");
    }
    
    if ($taxRate < 0) {
        return Err::of("Tax rate cannot be negative");
    }
    
    $taxAmount = $basePrice * $taxRate;
    $totalPrice = $basePrice + $taxAmount;
    
    return Ok::of([
        'base_price' => $basePrice,
        'tax_rate' => $taxRate,
        'tax_amount' => $taxAmount,
        'total_price' => $totalPrice,
        'effective_rate' => safe_divide($totalPrice, $basePrice)->unwrapOr(1.0)
    ]);
}

/**
 * パフォーマンス測定：実行時間の計算
 * 
 * @param callable $operation 実行する処理
 * @return Result<array, string> 実行結果と時間情報
 */
function measure_performance(callable $operation): Result
{
    try {
        $startTime = microtime(true);
        $result = $operation();
        $endTime = microtime(true);
        
        $executionTime = $endTime - $startTime;
        
        return Ok::of([
            'result' => $result,
            'execution_time_ms' => $executionTime * 1000,
            'execution_time_readable' => number_format($executionTime * 1000, 2) . ' ms'
        ]);
    } catch (Exception $e) {
        return Err::of("Performance measurement failed: " . $e->getMessage());
    }
}

/**
 * 結果の表示用関数
 * 
 * @param Result $result 表示するResult
 * @param string $label ラベル
 */
function display_result(Result $result, string $label = "Result"): void
{
    echo "\n=== $label ===\n";
    
    if ($result->isOk()) {
        $value = $result->unwrap();
        if (is_array($value)) {
            echo "Success:\n";
            foreach ($value as $key => $val) {
                echo "  $key: " . (is_float($val) ? number_format($val, 4) : $val) . "\n";
            }
        } else {
            echo "Success: " . (is_float($value) ? number_format($value, 4) : $value) . "\n";
        }
    } else {
        $error = $result->unwrapErr();
        if (is_array($error)) {
            echo "Error:\n";
            foreach ($error as $key => $val) {
                echo "  $key: $val\n";
            }
        } else {
            echo "Error: $error\n";
        }
    }
}

// ===== 実行例 =====

echo "PHP Result/Option型ライブラリ - 安全な計算の実例\n";
echo str_repeat("=", 60) . "\n";

// 1. 基本的な割り算
display_result(safe_divide(100, 5), "基本的な割り算 (100 ÷ 5)");
display_result(safe_divide(100, 0), "ゼロ除算エラー (100 ÷ 0)");

// 2. 詳細エラー情報付き割り算
display_result(safe_divide_detailed(100, 0), "詳細エラー情報付き");
display_result(safe_divide_detailed(INF, 5), "無限大の処理");

// 3. 文字列からの変換
display_result(safe_divide_from_string("100", "5"), "文字列入力 ('100' ÷ '5')");
display_result(safe_divide_from_string("100", "abc"), "無効な文字列 ('100' ÷ 'abc')");

// 4. 連続割り算
display_result(chain_divide(1000, [2, 5, 10]), "連続割り算 (1000 ÷ 2 ÷ 5 ÷ 10)");
display_result(chain_divide(1000, [2, 0, 10]), "連続割り算（途中でエラー）");

// 5. フォーマット付き結果表示
echo "\n=== フォーマット済み結果 ===\n";
echo format_division_result(safe_divide(22, 7), 6) . "\n";
echo format_division_result(safe_divide(100, 0)) . "\n";

// 6. 統計計算
display_result(safe_average([10, 20, 30, 40, 50]), "平均値計算");
display_result(safe_average([]), "空配列の平均値");

// 7. 実用例：税込み価格計算
display_result(
    calculate_tax_inclusive_price(1000, 0.10), 
    "税込み価格計算 (基本価格:1000円, 税率:10%)"
);
display_result(
    calculate_tax_inclusive_price(-100, 0.10), 
    "負の価格エラー"
);

// 8. パフォーマンス測定
$performanceResult = measure_performance(function() {
    $result = 0;
    for ($i = 1; $i <= 1000; $i++) {
        $divisionResult = safe_divide(100000, $i);
        if ($divisionResult->isOk()) {
            $result += $divisionResult->unwrap();
        }
    }
    return $result;
});

display_result($performanceResult, "パフォーマンス測定 (1000回の安全な割り算)");

// 9. メソッドチェーンの活用例
echo "\n=== メソッドチェーンの例 ===\n";

$chainResult = safe_divide(100, 4)
    ->map(fn($x) => $x * 2)                    // 50 (25 * 2)
    ->andThen(fn($x) => safe_divide($x, 10))   // 5 (50 ÷ 10)
    ->map(fn($x) => "最終結果: " . number_format($x, 1));

echo $chainResult->unwrapOr("計算エラー") . "\n";

// 10. エラーハンドリングパターンの比較
echo "\n=== エラーハンドリングパターン ===\n";

// パターン1: 早期リターン
function early_return_pattern($a, $b, $c): string {
    $step1 = safe_divide($a, $b);
    if ($step1->isErr()) {
        return "Step 1 failed: " . $step1->unwrapErr();
    }
    
    $step2 = safe_divide($step1->unwrap(), $c);
    if ($step2->isErr()) {
        return "Step 2 failed: " . $step2->unwrapErr();
    }
    
    return "Success: " . number_format($step2->unwrap(), 2);
}

// パターン2: メソッドチェーン
function method_chain_pattern($a, $b, $c): string {
    return safe_divide($a, $b)
        ->andThen(fn($result) => safe_divide($result, $c))
        ->map(fn($final) => "Success: " . number_format($final, 2))
        ->unwrapOr("Calculation failed");
}

echo "早期リターンパターン: " . early_return_pattern(100, 5, 2) . "\n";
echo "メソッドチェーンパターン: " . method_chain_pattern(100, 5, 2) . "\n";

echo "\n" . str_repeat("=", 60) . "\n";
echo "安全な計算の実例が完了しました。\n";
echo "エラーハンドリング、型安全性、関数型プログラミングの利点をご確認ください。\n";


