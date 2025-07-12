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
    ->andThen(fn($x) => $x > 15 ? new Ok($x) : new Err("値が小さすぎます"))
    ->unwrapOr(0);
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
- ✅ `is_ok()` / `is_err()`
- ✅ `map()` / `map_err()`
- ✅ `and_then()`
- ✅ `unwrap()` / `unwrap_err()`
- ✅ `unwrap_or()` / `unwrap_or_else()`
- ✅ `expect()`

### 未実装機能
- ❌ `or()` / `or_else()` - 代替Resultの提供
- ❌ `and()` - 連続的な成功チェック
- ❌ `contains()` / `contains_err()` - 値の存在確認
- ❌ `inspect()` / `inspect_err()` - デバッグ用副作用実行
- ❌ `transpose()` - Option型との相互変換
- ❌ `flatten()` - ネストしたResultの平坦化

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