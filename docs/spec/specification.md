# PHP Result型ライブラリ 仕様書

## 概要

このライブラリは、RustのResult型をPHPで実装したものです。成功(`Ok`)と失敗(`Err`)を型安全に表現し、エラーハンドリングを関数型プログラミングのアプローチで行うことができます。

## アーキテクチャ

### クラス構成

```
Mizumi\Result\
├── Result.php                     # 基底インターフェース
├── Ok.php                        # 成功値を表現するクラス
├── Err.php                       # エラー値を表現するクラス
└── Exception\
    └── UnwrapException.php       # unwrap系メソッドで発生する例外
```

### 型パラメータ

- `T`: 成功時の値の型
- `E`: 失敗時のエラーの型

## インターフェース定義

### Result&lt;T, E&gt;

```php
interface Result
{
    public function isOk(): bool;
    public function isErr(): bool;
    public function map(callable $fn): Result;
    public function mapErr(callable $fn): Result;
    public function andThen(callable $fn): Result;
    public function unwrap(): mixed;
    public function unwrapErr(): mixed;
    public function unwrapOr(mixed $default): mixed;
    public function unwrapOrElse(callable $fn): mixed;
    public function expect(string $message): mixed;
    public function inspect(callable $fn): Result;
    public function inspectErr(callable $fn): Result;
    public function or(Result $res): Result;
    public function orElse(callable $fn): Result;
}
```

## 実装クラス詳細

### Ok&lt;T&gt; クラス

成功値を格納するimmutableなクラス。

**コンストラクタ:**
- `__construct(mixed $value)` - 値を受け取り初期化
- `static of(mixed $value): self` - 静的ファクトリーメソッド

**主要メソッドの動作:**
- `isOk()`: 常に `true` を返す
- `isErr()`: 常に `false` を返す
- `map(callable $fn)`: 値に関数を適用した新しい`Ok`を返す
- `mapErr(callable $fn)`: 自身をそのまま返す（何もしない）
- `andThen(callable $fn)`: 値に関数を適用し、その結果を返す
- `unwrap()`: 格納されている値を返す
- `unwrapErr()`: `UnwrapException`をスロー
- `unwrapOr(mixed $default)`: 格納されている値を返す（デフォルト値は無視）
- `unwrapOrElse(callable $fn)`: 格納されている値を返す（関数は実行されない）
- `expect(string $message)`: 格納されている値を返す
- `inspect(callable $fn)`: 値に関数を適用して副作用を実行し、自身を返す
- `inspectErr(callable $fn)`: 何もせず自身をそのまま返す
- `or(Result $res)`: 自身をそのまま返す（代替Resultは無視）
- `orElse(callable $fn)`: 自身をそのまま返す（関数は実行されない）
- `flatten()`: 値がResultなら内部のResultを返し、非Resultなら自身を返す

### Err&lt;E&gt; クラス

エラー値を格納するimmutableなクラス。

**コンストラクタ:**
- `__construct(mixed $error)` - エラー値を受け取り初期化
- `static of(mixed $error): self` - 静的ファクトリーメソッド

**主要メソッドの動作:**
- `isOk()`: 常に `false` を返す
- `isErr()`: 常に `true` を返す
- `map(callable $fn)`: 自身をそのまま返す（何もしない）
- `mapErr(callable $fn)`: エラーに関数を適用した新しい`Err`を返す
- `andThen(callable $fn)`: 自身をそのまま返す（何もしない）
- `unwrap()`: `UnwrapException`をスロー
- `unwrapErr()`: 格納されているエラー値を返す
- `unwrapOr(mixed $default)`: デフォルト値を返す
- `unwrapOrElse(callable $fn)`: エラー値を引数として関数を実行し、その結果を返す
- `expect(string $message)`: カスタムメッセージ付きで`UnwrapException`をスロー
- `inspect(callable $fn)`: 何もせず自身をそのまま返す
- `inspectErr(callable $fn)`: エラー値に関数を適用して副作用を実行し、自身を返す
- `or(Result $res)`: 引数の代替Resultを返す（即座評価）
- `orElse(callable $fn)`: エラー値を引数として関数を実行し、その結果のResultを返す（遅延評価）
- `flatten()`: 自身をそのまま返す（何もしない）

## 設計原則

### 1. Immutability (不変性)
- すべてのプロパティは`readonly`
- メソッド呼び出しは新しいインスタンスを返すか、既存のインスタンスをそのまま返す
- 状態の変更は行わない

### 2. Type Safety (型安全性)
- PHPDocでGenericsを表現
- 適切な型制約を設定
- 実行時型チェックは最小限に抑制

### 3. 継承の禁止
- すべてのクラスは`final`で継承不可
- インターフェースの実装のみ許可

### 4. エラーハンドリング
- 例外的な状況でのみ例外をスロー
- 通常のエラーは`Err`インスタンスで表現
- `UnwrapException`は予期しない操作時のみ発生

## 使用例

### 基本的な使用法

```php
use Mizumi\Result\Ok;
use Mizumi\Result\Err;

// 成功ケース
$result = new Ok(42);
echo $result->unwrap(); // 42

// 失敗ケース
$result = new Err("エラーメッセージ");
echo $result->unwrapOr(0); // 0
```

### チェーン処理

```php
$result = new Ok(10)
    ->map(fn($x) => $x * 2)
    ->inspect(fn($value) => echo "中間値: $value\n") // デバッグ出力
    ->andThen(fn($x) => $x > 15 ? new Ok($x) : new Err("値が小さすぎます"))
    ->unwrapOr(0);
```

### inspect/inspectErrメソッドの使用例

```php
// デバッグ用途での値確認
$result = new Ok("重要なデータ")
    ->inspect(fn($value) => error_log("処理中のデータ: $value"))
    ->map(fn($value) => strtoupper($value));

// エラー時のログ出力
$result = new Err("ネットワークエラー")
    ->inspectErr(fn($error) => error_log("エラー発生: $error"))
    ->or(new Ok("デフォルト値"));

// メソッドチェーンでの段階的デバッグ
$result = new Ok(100)
    ->map(fn($x) => $x / 2)
    ->inspect(fn($value) => echo "Step 1: $value\n")
    ->map(fn($x) => $x - 10)
    ->inspect(fn($value) => echo "Step 2: $value\n")
    ->andThen(fn($x) => $x > 0 ? new Ok($x) : new Err("負の値"))
    ->inspectErr(fn($error) => echo "エラー: $error\n");
```

### or/orElseメソッドの使用例

```php
// or(): 即座評価での代替値提供
$primaryResult = new Err("データベース接続失敗");
$fallbackResult = new Ok("キャッシュからのデータ");

$result = $primaryResult->or($fallbackResult);
echo $result->unwrap(); // "キャッシュからのデータ"

// orElse(): 遅延評価での動的な代替値生成
function createFallback(string $error): Result {
    error_log("代替処理実行: $error");
    return new Ok("代替データ: " . date('Y-m-d H:i:s'));
}

$result = new Err("API呼び出し失敗")
    ->orElse(fn($error) => createFallback($error));

// 複数の代替戦略の組み合わせ
$result = new Err("主処理失敗")
    ->or(new Err("代替処理1も失敗"))
    ->orElse(fn($error) => new Ok("最終的な代替値"))
    ->unwrap(); // "最終的な代替値"
```

### contains/containsErrメソッドの使用例

```php
// Ok値での値確認
$ok = new Ok("success");
var_dump($ok->contains("success")); // true
var_dump($ok->contains("failure")); // false
var_dump($ok->containsErr("error")); // false (Okは常にエラーを含まない)

// Err値でのエラー確認
$err = new Err("network error");
var_dump($err->containsErr("network error")); // true
var_dump($err->containsErr("database error")); // false
var_dump($err->contains("success")); // false (Errは常に値を含まない)

// 厳密比較の動作
$intOk = new Ok(42);
var_dump($intOk->contains(42)); // true
var_dump($intOk->contains("42")); // false (型が異なる)
var_dump($intOk->contains(42.0)); // false (型が異なる)

// 複雑なデータ構造での確認
$userData = ["id" => 123, "name" => "Alice"];
$ok = new Ok($userData);
var_dump($ok->contains(["id" => 123, "name" => "Alice"])); // true
var_dump($ok->contains(["id" => 123, "name" => "Bob"])); // false

// オブジェクト参照の確認
$obj = new stdClass();
$ok = new Ok($obj);
var_dump($ok->contains($obj)); // true (同じ参照)
var_dump($ok->contains(new stdClass())); // false (異なる参照)

### and()メソッドの使用例

```php
// and(): 即座評価での連続的な成功チェック
$validation = new Ok("ユーザー認証成功");
$authorization = new Ok("権限確認完了");

$result = $validation->and($authorization);
echo $result->unwrap(); // "権限確認完了"

// 一つでも失敗すると最初のエラーが返される
$authOk = new Ok("認証成功");
$authErr = new Err("権限不足");

$result = $authOk->and($authErr);
echo $result->unwrapErr(); // "権限不足"

// エラーが最初にあると後続は評価されない
$firstErr = new Err("最初のエラー");
$secondResult = new Ok("到達しない値");

$result = $firstErr->and($secondResult);
echo $result->unwrapErr(); // "最初のエラー"

// 複数のチェックポイント
$userValidation = new Ok("ユーザー有効");
$sessionValidation = new Ok("セッション有効");  
$permissionValidation = new Ok("権限有効");

$result = $userValidation
    ->and($sessionValidation)
    ->and($permissionValidation);
echo $result->unwrap(); // "権限有効"

// 型の異なるResult間での使用
$intResult = new Ok(42);
$stringResult = new Ok("処理完了");

$final = $intResult->and($stringResult);
echo $final->unwrap(); // "処理完了"
```

### flatten()メソッドの使用例

```php
// flatten(): ネストしたResultを一段階平坦化
$okOk = new Ok(new Ok(42));
$flattened = $okOk->flatten();
echo $flattened->unwrap(); // 42

// ネストしたエラーの平坦化
$okErr = new Ok(new Err("内部エラー"));
$flattened = $okErr->flatten();
echo $flattened->unwrapErr(); // "内部エラー"

// Errは自身をそのまま返す
$err = new Err("外部エラー");
$flattened = $err->flatten();
echo $flattened->unwrapErr(); // "外部エラー"

// 非Resultの値はそのまま
$simple = new Ok("単純な値");
$flattened = $simple->flatten();
echo $flattened->unwrap(); // "単純な値"

// 多重ネストの段階的平坦化
$tripleNested = new Ok(new Ok(new Ok("深い値")));
$firstFlatten = $tripleNested->flatten();
$secondFlatten = $firstFlatten->flatten();
echo $secondFlatten->unwrap(); // "深い値"

// 実用例：バリデーション結果の平坦化
function validateAndParse(string $input): \Mizumi\Result\Result {
    if (empty($input)) {
        return new Ok(new Err("入力が空です"));
    }
    
    $parsed = intval($input);
    if ($parsed === 0 && $input !== "0") {
        return new Ok(new Err("数値変換に失敗しました"));
    }
    
    return new Ok(new Ok($parsed));
}

$result = validateAndParse("42")
    ->flatten()
    ->map(fn($x) => $x * 2);
echo $result->unwrap(); // 84

$errorResult = validateAndParse("")
    ->flatten()
    ->unwrapOr(0);
echo $errorResult; // 0
```

## 型注釈の詳細

### Genericsの表現方法

PHPDocを使用したGenerics表現：

```php
/**
 * @template T
 * @implements Result<T, never>
 */
final class Ok implements Result { }

/**
 * @template E
 * @implements Result<never, E>
 */
final class Err implements Result { }
```

### never型の使用

- `Ok<T>`は`Result<T, never>`を実装（エラー型は存在しない）
- `Err<E>`は`Result<never, E>`を実装（成功型は存在しない）

## パフォーマンス特性

### メモリ使用量
- 最小限のオーバーヘッド
- 値のコピーは行わず参照を保持

### 実行速度
- インライン化可能な単純なメソッド
- 例外処理は例外的な場合のみ

## Rust標準ライブラリとの比較

### 実装済み機能
- ✅ `is_ok()` / `is_err()` - 成功/失敗の判定
- ✅ `map()` / `map_err()` - 値/エラーの変換
- ✅ `and_then()` - モナド的チェーン処理
- ✅ `unwrap()` / `unwrap_err()` - 値/エラーの取り出し（例外あり）
- ✅ `unwrap_or()` / `unwrap_or_else()` - 安全な値取り出し
- ✅ `expect()` - カスタムメッセージ付き値取り出し
- ✅ `inspect()` / `inspect_err()` - デバッグ用副作用実行
- ✅ `or()` / `or_else()` - 代替Resultの提供
- ✅ `and()` - 連続的な成功チェック
- ✅ `contains()` / `containsErr()` - 値の存在確認（PHP独自実装）
- ✅ `flatten()` - ネストしたResultの平坦化

### 未実装機能
- ❌ `transpose()` - Option型との相互変換

### 違いと制約
- PHPの型システムの制約により、コンパイル時型チェックは限定的
- `never`型は完全にはサポートされていない
- パターンマッチングは利用できない

## エラーメッセージ仕様

### UnwrapExceptionのメッセージ形式

**Ok値でunwrapErr()を呼んだ場合:**
```
Called unwrapErr() on an Ok value: [値のprint_r表現]
```

**Err値でunwrap()を呼んだ場合:**
```
Called unwrap() on an Err value: [エラーのprint_r表現]
```

**expect()でのカスタムメッセージ:**
```
[カスタムメッセージ]: [エラーのprint_r表現]
```

## 拡張可能性

### 今後の拡張予定
1. 追加メソッドの実装（or系、inspect系）
2. より良いエラー表現（構造化エラー情報）
3. デバッグ支援機能の強化
4. Option型との連携機能

### カスタムエラー型の推奨パターン

```php
interface ErrorType {
    public function getMessage(): string;
    public function getCode(): int;
}

class ValidationError implements ErrorType {
    public function __construct(
        private readonly string $field,
        private readonly string $message
    ) {}
    
    public function getMessage(): string {
        return "Validation failed for {$this->field}: {$this->message}";
    }
    
    public function getCode(): int {
        return 400;
    }
}
```

## 実装履歴と注意点

### flatten() メソッド (2025-07-13 実装)
- **機能**: ネストしたResultの一段階平坦化
- **Rust対応**: `Result<Result<T, E>, E>` → `Result<T, E>` の変換
- **動作**: Ok(Result) → Result、Ok(non-Result) → Ok、Err → Err
- **特徴**: 一段階のみ平坦化、多重ネストは段階的処理
- **型チェック**: `instanceof Result`による実行時判定
- **テスト**: 25テストケース、エッジケース・パフォーマンステスト含む

### contains() / containsErr() メソッド (2025-07-13 実装)
- **機能**: 値の存在確認（PHP独自実装、Rustには存在しない）
- **比較方法**: 厳密比較（`===`）を採用
- **動作**: Ok値での`containsErr()`、Err値での`contains()`は常に`false`
- **テスト**: 包括的エッジケーステスト実装済み（null、オブジェクト、配列、型変換）

### and() メソッド (2025-07-13 実装)
- **機能**: 連続的な成功チェック（即座評価）
- **動作**: Okの場合は引数のResult、Errの場合は自身を返す
- **チェーン**: 複数のResultを順次結合可能

## 制限事項

### PHP言語固有の制限
- 真のGenericsサポートがない
- パターンマッチングが利用できない
- `never`型の完全なサポートがない
- コンパイル時型チェックの限界

### 設計上の制限
- `print_r()`を使った単純なエラー表示
- 例外スタックトレースの情報は限定的
- メモリ使用量の最適化余地

この仕様書は、現在の実装状況と将来の拡張計画を含む、PHP Result型ライブラリの完全な技術仕様を提供します。