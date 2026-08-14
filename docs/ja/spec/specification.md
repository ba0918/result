# PHP Result/Option型ライブラリ 仕様書

## 概要

このライブラリは、RustのResult型とOption型をPHPで実装したものです。Result型は成功(`Ok`)と失敗(`Err`)を、Option型は値の有無(`Some`と`None`)を型安全に表現し、エラーハンドリングとnull安全性を関数型プログラミングのアプローチで実現します。

## アーキテクチャ

### クラス構成

```
ba0918\Result\
├── Result.php                     # Result型基底インターフェース
├── Ok.php                        # 成功値を表現するクラス
├── Err.php                       # エラー値を表現するクラス
├── Option.php                     # Option型基底インターフェース
├── Some.php                      # 値を持つOption実装クラス
├── None.php                      # 値を持たないOption実装クラス（シングルトン）
└── Exception\
    └── UnwrapException.php       # unwrap系メソッドで発生する例外
```

### 型パラメータ

**Result型:**
- `T`: 成功時の値の型
- `E`: 失敗時のエラーの型

**Option型:**
- `T`: 値の型（Someの場合）

## インターフェース定義

### Result&lt;T, E&gt;

```php
interface Result
{
    public function isOk(): bool;
    public function isErr(): bool;
    public function isOkAnd(callable $predicate): bool;
    public function isErrAnd(callable $predicate): bool;
    public function map(callable $fn): Result;
    public function mapErr(callable $fn): Result;
    public function mapOr(callable $fn, mixed $default): mixed;
    public function mapOrElse(callable $fn, callable $defaultFn): mixed;
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
    public function and(Result $res): Result;
    public function contains(mixed $value): bool;
    public function containsErr(mixed $error): bool;
    public function flatten(): Result;
    public function transpose(): Option;
    public function ok(): Option;
    public function err(): Option;
    public function expectErr(string $message): mixed;
}
```

### Option&lt;T&gt;

```php
interface Option
{
    public function isSome(): bool;
    public function isNone(): bool;
    public function isSomeAnd(callable $predicate): bool;
    public function map(callable $fn): Option;
    public function mapOr(callable $fn, mixed $default): mixed;
    public function mapOrElse(callable $fn, callable $defaultFn): mixed;
    public function andThen(callable $fn): Option;
    public function filter(callable $predicate): Option;
    public function unwrap(): mixed;
    public function unwrapOr(mixed $default): mixed;
    public function unwrapOrElse(callable $fn): mixed;
    public function expect(string $message): mixed;
    public function inspect(callable $fn): Option;
    public function or(Option $opt): Option;
    public function orElse(callable $fn): Option;
    public function and(Option $opt): Option;
    public function contains(mixed $value): bool;
    public function transpose(): Result;
    public function okOr(mixed $err): Result;
    public function okOrElse(callable $fn): Result;
    public function flatten(): Option;
    public function xor(Option $opt): Option;
    public function zip(Option $opt): Option;
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
- `transpose()`: 値がOptionなら相互変換し、非Optionなら`Some(Ok(value))`を返す
- `ok()`: 格納値を`Some`でラップして返す
- `err()`: `None`を返す（Okは常にエラーを持たない）
- `expectErr(string $message)`: カスタムメッセージ付きで`UnwrapException`をスロー

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
- `transpose()`: `Some(Err(error))`を返す
- `ok()`: `None`を返す（Errは常に成功値を持たない）
- `err()`: エラー値を`Some`でラップして返す
- `expectErr(string $message)`: 格納されているエラー値を返す

### Some&lt;T&gt; クラス

値を持つOptionを表現するimmutableなクラス。

**コンストラクタ:**
- `__construct(mixed $value)` - 値を受け取り初期化
- `static of(mixed $value): self` - 静的ファクトリーメソッド

**主要メソッドの動作:**
- `isSome()`: 常に `true` を返す
- `isNone()`: 常に `false` を返す
- `map(callable $fn)`: 値に関数を適用した新しい`Some`を返す
- `mapOr(callable $fn, mixed $default)`: 値に関数を適用した結果を返す
- `mapOrElse(callable $fn, callable $defaultFn)`: 値に関数を適用した結果を返す
- `andThen(callable $fn)`: 値に関数を適用し、その結果を返す
- `filter(callable $predicate)`: 述語を満たす場合は自身、満たさない場合は`None`を返す
- `unwrap()`: 格納されている値を返す
- `unwrapOr(mixed $default)`: 格納されている値を返す（デフォルト値は無視）
- `unwrapOrElse(callable $fn)`: 格納されている値を返す（関数は実行されない）
- `expect(string $message)`: 格納されている値を返す
- `inspect(callable $fn)`: 値に関数を適用して副作用を実行し、自身を返す
- `or(Option $opt)`: 自身をそのまま返す（代替Optionは無視）
- `orElse(callable $fn)`: 自身をそのまま返す（関数は実行されない）
- `and(Option $opt)`: 引数のOptionを返す
- `contains(mixed $value)`: 格納値と厳密比較（`===`）し、結果を返す
- `transpose()`: 値がResultなら相互変換し、非Resultなら`Ok(Some(value))`を返す
- `okOr(mixed $err)`: `Ok(value)`を返す
- `okOrElse(callable $fn)`: `Ok(value)`を返す（関数は実行されない）
- `flatten()`: 値がOptionなら内部のOptionを返し、非Optionなら自身を返す
- `xor(Option $opt)`: 引数がNoneなら自身、引数がSomeなら`None`を返す（排他的OR）
- `zip(Option $opt)`: 引数がSomeなら`[自身の値, 引数の値]`の配列を持つ`Some`、引数がNoneなら`None`を返す

### None クラス

値を持たないOptionを表現するimmutableなシングルトンクラス。

**インスタンス取得:**
- `static instance(): self` - シングルトンインスタンスを取得

**主要メソッドの動作:**
- `isSome()`: 常に `false` を返す
- `isNone()`: 常に `true` を返す
- `map(callable $fn)`: 自身をそのまま返す（何もしない）
- `mapOr(callable $fn, mixed $default)`: デフォルト値を返す
- `mapOrElse(callable $fn, callable $defaultFn)`: デフォルト関数の結果を返す
- `andThen(callable $fn)`: 自身をそのまま返す（何もしない）
- `filter(callable $predicate)`: 自身をそのまま返す（何もしない）
- `unwrap()`: `UnwrapException`をスロー
- `unwrapOr(mixed $default)`: デフォルト値を返す
- `unwrapOrElse(callable $fn)`: 関数の結果を返す
- `expect(string $message)`: カスタムメッセージ付きで`UnwrapException`をスロー
- `inspect(callable $fn)`: 何もせず自身をそのまま返す
- `or(Option $opt)`: 引数の代替Optionを返す（即座評価）
- `orElse(callable $fn)`: 関数を実行し、その結果のOptionを返す（遅延評価）
- `and(Option $opt)`: 自身をそのまま返す（何もしない）
- `contains(mixed $value)`: 常に `false` を返す
- `transpose()`: `Ok(None)`を返す
- `okOr(mixed $err)`: `Err(err)`を返す
- `okOrElse(callable $fn)`: 関数を実行し、`Err(result)`を返す
- `flatten()`: 自身をそのまま返す（何もしない）
- `xor(Option $opt)`: 引数のOptionを返す（引数に関係なく）
- `zip(Option $opt)`: 自身をそのまま返す（Noneは常にNone）

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

## サポート対象PHPバージョン

- **対応バージョン**: `^8.3`
- **理由**:
  - 8.3のセキュリティサポートは2027年12月末まで継続している
  - 8.4固有機能（property hooks等）はreadonly設計と排他で、本ライブラリに適用余地がない
  - コードは8.1のreadonlyと8.3の`#[Override]`のみを使用している
- **再評価条件**: 8.3のEOL（2027年12月）時点で対応バージョンの見直しを検討する

## 使用例

### 基本的な使用法

**Result型:**
```php
use ba0918\Result\Ok;
use ba0918\Result\Err;

// 成功ケース
$result = new Ok(42);
echo $result->unwrap(); // 42

// 失敗ケース
$result = new Err("エラーメッセージ");
echo $result->unwrapOr(0); // 0
```

**Option型:**
```php
use ba0918\Result\Some;
use ba0918\Result\None;

// 値を持つケース
$option = Some::of("Hello World");
echo $option->unwrap(); // "Hello World"

// 値を持たないケース
$option = None::instance();
echo $option->unwrapOr("デフォルト値"); // "デフォルト値"
```

### チェーン処理

**Result型:**
```php
$result = new Ok(10)
    ->map(fn($x) => $x * 2)
    ->inspect(fn($value) => echo "中間値: $value\n") // デバッグ出力
    ->andThen(fn($x) => $x > 15 ? new Ok($x) : new Err("値が小さすぎます"))
    ->unwrapOr(0);
```

**Option型:**
```php
$result = Some::of("hello")
    ->map(fn($s) => strtoupper($s))
    ->filter(fn($s) => strlen($s) > 3)
    ->inspect(fn($value) => echo "処理中: $value\n")
    ->andThen(fn($s) => Some::of($s . " WORLD"))
    ->unwrapOr("デフォルト");
echo $result; // "HELLO WORLD"
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
```

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
function validateAndParse(string $input): \ba0918\Result\Result {
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

### Option型の使用例

```php
// filter()での条件フィルタリング
$age = Some::of(25)
    ->filter(fn($age) => $age >= 18)
    ->map(fn($age) => "成人（$age歳）")
    ->unwrapOr("未成年");
echo $age; // "成人（25歳）"

// Noneでのor()による代替値提供
$userName = None::instance()
    ->or(Some::of("guest"))
    ->unwrap(); // "guest"

// orElse()での動的な代替値生成
$config = None::instance()
    ->orElse(fn() => Some::of(loadDefaultConfig()))
    ->unwrap();

// contains()での値確認
$data = Some::of([1, 2, 3]);
if ($data->contains([1, 2, 3])) {
    echo "期待した配列です";
}
```

### transpose()とOption-Result相互変換

```php
use ba0918\Result\{Ok, Err, Some, None};

// Option<Result> → Result<Option> への変換
$optionResult = Some::of(Ok::of("成功データ"));
$resultOption = $optionResult->transpose(); // Ok(Some("成功データ"))

$errorCase = Some::of(Err::of("エラー発生"));
$errorResult = $errorCase->transpose(); // Err("エラー発生")

$noneCase = None::instance();
$noneResult = $noneCase->transpose(); // Ok(None)

// Result<Option> → Option<Result> への変換
$resultSome = Ok::of(Some::of("値"));
$optionResult = $resultSome->transpose(); // Some(Ok("値"))

$resultNone = Ok::of(None::instance());
$optionEmpty = $resultNone->transpose(); // None

$resultErr = Err::of("エラー");
$optionErr = $resultErr->transpose(); // Some(Err("エラー"))

// Option → Result 変換
$someValue = Some::of("データ");
$result = $someValue->okOr("エラーメッセージ"); // Ok("データ")

$noneValue = None::instance();
$result = $noneValue->okOr("値がありません"); // Err("値がありません")

$result = $noneValue->okOrElse(fn() => "動的エラー:" . time()); // Err("動的エラー:...")

// 実用例：データベース検索とバリデーション
function findUser(int $id): Option {
    // データベース検索のシミュレーション
    return $id > 0 ? Some::of(["id" => $id, "name" => "User$id"]) : None::instance();
}

function validateUser(array $user): Result {
    return empty($user['name']) ? Err::of("名前が空です") : Ok::of($user);
}

$result = findUser(123)                                    // Some(user) or None
    ->okOr("ユーザーが見つかりません")                      // Ok(user) or Err("...")
    ->andThen(fn($user) => validateUser($user))            // Ok(user) or Err("...")
    ->map(fn($user) => $user['name'])                      // Ok(name) or Err("...")
    ->unwrapOr("ゲスト");

echo $result; // "User123" or "ゲスト"
```

### Result型の新変換メソッドの使用例

```php
use ba0918\Result\{Ok, Err};

// ok()メソッド: 成功値をOptionとして取得
$success = Ok::of("データ");
$okOption = $success->ok(); // Some("データ")
echo $okOption->unwrap(); // "データ"

$failure = Err::of("エラー");
$okOption = $failure->ok(); // None
echo $okOption->unwrapOr("デフォルト"); // "デフォルト"

// err()メソッド: エラー値をOptionとして取得
$success = Ok::of("データ");
$errOption = $success->err(); // None
echo $errOption->unwrapOr("エラーなし"); // "エラーなし"

$failure = Err::of("ネットワークエラー");
$errOption = $failure->err(); // Some("ネットワークエラー")
echo $errOption->unwrap(); // "ネットワークエラー"

// expectErr()メソッド: エラー値のカスタムメッセージ付き取り出し
$error = Err::of("認証失敗");
echo $error->expectErr("エラー詳細が必要"); // "認証失敗"

$success = Ok::of("成功データ");
try {
    $success->expectErr("エラーのはずが成功");
} catch (UnwrapException $e) {
    echo $e->getMessage(); // "エラーのはずが成功: 成功データ"
}

// 実用例：API呼び出し結果の詳細分析
function analyzeApiResult(Result $apiResult): array {
    return [
        'has_data' => $apiResult->ok()->isSome(),
        'data' => $apiResult->ok()->unwrapOr(null),
        'has_error' => $apiResult->err()->isSome(),
        'error_type' => $apiResult->err()
            ->map(fn($err) => $err instanceof \Exception ? get_class($err) : 'string')
            ->unwrapOr('none'),
        'error_message' => $apiResult->err()->unwrapOr('no error')
    ];
}

$successResult = Ok::of(['user_id' => 123, 'name' => 'Alice']);
$analysis = analyzeApiResult($successResult);
// ['has_data' => true, 'data' => [...], 'has_error' => false, ...]

$errorResult = Err::of(new \RuntimeException("Database connection failed"));
$analysis = analyzeApiResult($errorResult);
// ['has_data' => false, 'data' => null, 'has_error' => true, ...]
```

### Option型の新結合メソッドの使用例

```php
use ba0918\Result\{Some, None};

// xor()メソッド: 排他的OR操作
$user = Some::of("Alice");
$guest = None::instance();
$both = Some::of("Bob");

// 片方のみSomeの場合にSome
$result1 = $user->xor($guest); // Some("Alice")
$result2 = $guest->xor($user); // Some("Alice")

// 両方Some/両方Noneの場合にNone
$result3 = $user->xor($both); // None
$result4 = $guest->xor(None::instance()); // None

// zip()メソッド: 2つのOptionを結合
$firstName = Some::of("Alice");
$lastName = Some::of("Smith");
$age = Some::of(30);

// 両方Someの場合にタプル
$fullName = $firstName->zip($lastName); // Some(["Alice", "Smith"])
$nameAndAge = $firstName->zip($age); // Some(["Alice", 30])

// 片方でもNoneの場合にNone
$incomplete = $firstName->zip(None::instance()); // None

// 実用例：フォームデータの結合
function combineFormData(Option $name, Option $email, Option $phone): Option {
    return $name
        ->zip($email)                    // Some([name, email]) or None
        ->andThen(fn($pair) => 
            $phone->map(fn($p) => [...$pair, $p])  // Some([name, email, phone]) or None
        );
}

$validForm = combineFormData(
    Some::of("Alice Smith"),
    Some::of("alice@example.com"),
    Some::of("123-456-7890")
); // Some(["Alice Smith", "alice@example.com", "123-456-7890"])

$incompleteForm = combineFormData(
    Some::of("Bob Jones"),
    None::instance(),
    Some::of("987-654-3210")
); // None

// xorとzipの組み合わせ使用例
function selectUserInput(Option $primaryInput, Option $fallbackInput): Option {
    // どちらか一方のみが入力されている場合を期待
    return $primaryInput
        ->xor($fallbackInput)                           // Some(input) or None
        ->zip(Some::of("validated"))                    // Some([input, "validated"]) or None
        ->map(fn($pair) => ['input' => $pair[0], 'status' => $pair[1]]);
}

// 有効なケース（片方のみ入力）
$result1 = selectUserInput(Some::of("primary"), None::instance());
// Some(['input' => 'primary', 'status' => 'validated'])

// 無効なケース（両方入力または両方空）
$result2 = selectUserInput(Some::of("primary"), Some::of("fallback")); // None
$result3 = selectUserInput(None::instance(), None::instance()); // None

// 複雑なオプション処理：設定の優先度管理
function mergeConfigs(Option $userConfig, Option $defaultConfig): Option {
    return $userConfig
        ->or($defaultConfig)                            // ユーザー設定を優先
        ->zip(Some::of(time()))                         // タイムスタンプを追加
        ->map(fn($pair) => [
            'config' => $pair[0],
            'loaded_at' => $pair[1],
            'source' => $userConfig->isSome() ? 'user' : 'default'
        ]);
}
```

## 型注釈の詳細

### Genericsの表現方法

PHPDocを使用したGenerics表現：

```php
// Result型の実装
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

// Option型の実装
/**
 * @template T
 * @implements Option<T>
 */
final class Some implements Option { }

/**
 * @implements Option<never>
 */
final class None implements Option { }
```

### never型の使用

**Result型:**
- `Ok<T>`は`Result<T, never>`を実装（エラー型は存在しない）
- `Err<E>`は`Result<never, E>`を実装（成功型は存在しない）

**Option型:**
- `Some<T>`は`Option<T>`を実装
- `None`は`Option<never>`を実装（値型は存在しない）

## パフォーマンス特性

### メモリ使用量
- 最小限のオーバーヘッド
- 値のコピーは行わず参照を保持

### 実行速度
- インライン化可能な単純なメソッド
- 例外処理は例外的な場合のみ

## Rust標準ライブラリとの比較

### Result型 - 実装済み機能
- ✅ `is_ok()` / `is_err()` - 成功/失敗の判定
- 🔄 `is_ok_and()` / `is_err_and()` - 成功/失敗判定+条件チェック（実装予定）
- ✅ `map()` / `map_err()` - 値/エラーの変換
- 🔄 `map_or()` / `map_or_else()` - 成功時変換+失敗時デフォルト値（実装予定）
- ✅ `and_then()` - モナド的チェーン処理
- ✅ `unwrap()` / `unwrap_err()` - 値/エラーの取り出し（例外あり）
- ✅ `unwrap_or()` / `unwrap_or_else()` - 安全な値取り出し
- ✅ `expect()` / `expect_err()` - カスタムメッセージ付き値/エラー取り出し
- ✅ `inspect()` / `inspect_err()` - デバッグ用副作用実行
- ✅ `or()` / `or_else()` - 代替Resultの提供
- ✅ `and()` - 連続的な成功チェック
- ✅ `contains()` / `contains_err()` - 値の存在確認（PHP独自実装）
- ✅ `flatten()` - ネストしたResultの平坦化
- ✅ `transpose()` - Option型との相互変換
- ✅ `ok()` / `err()` - Result → Option変換

### Option型 - 実装済み機能
- ✅ `is_some()` / `is_none()` - 値の有無判定
- 🔄 `is_some_and()` - 値の有無判定+条件チェック（実装予定）
- ✅ `map()` / `map_or()` / `map_or_else()` - 値の変換
- ✅ `and_then()` - モナド的チェーン処理
- ✅ `filter()` - 条件によるフィルタリング
- ✅ `unwrap()` - 値の取り出し（例外あり）
- ✅ `unwrap_or()` / `unwrap_or_else()` - 安全な値取り出し
- ✅ `expect()` - カスタムメッセージ付き値取り出し
- ✅ `inspect()` - デバッグ用副作用実行
- ✅ `or()` / `or_else()` - 代替Optionの提供
- ✅ `and()` - 連続的な値チェック
- ✅ `contains()` - 値の存在確認
- ✅ `transpose()` - Result型との相互変換
- ✅ `ok_or()` / `ok_or_else()` - Result型への変換
- ✅ `flatten()` - ネストしたOptionの平坦化
- ✅ `xor()` - 排他的OR操作
- ✅ `zip()` - 複数Optionの組み合わせ

### Option型 - 非対応機能
- ❌ `replace()` - 値の置換（設計判断により非対応）
  - Rustの `replace()` は `&mut self` による破壊的代入が前提
  - このライブラリはイミュータブル設計（readonly）のため構造的に実装不可能
  - PHPでは変数への再代入（`$opt = Some::of($new)`）が等価操作

### 違いと制約
- PHPの型システムの制約により、コンパイル時型チェックは限定的
- `never`型は完全にはサポートされていない
- パターンマッチングは利用できない
- Noneはシングルトンパターンで実装（Rustは値型）

## エラーメッセージ仕様

### UnwrapExceptionのメッセージ形式

**Result型:**
- Ok値でunwrapErr()を呼んだ場合:
  ```
  Called unwrapErr() on an Ok value: [値のprint_r表現]
  ```
- Err値でunwrap()を呼んだ場合:
  ```
  Called unwrap() on an Err value: [エラーのprint_r表現]
  ```
- expect()でのカスタムメッセージ:
  ```
  [カスタムメッセージ]: [エラーのprint_r表現]
  ```

**Option型:**
- None値でunwrap()を呼んだ場合:
  ```
  None value
  ```
- None値でexpect()を呼んだ場合:
  ```
  [カスタムメッセージ]
  ```

## 拡張可能性

### 今後の拡張予定
1. **Option型の追加メソッド**: 新規メソッドの追加（`replace()`は設計判断により非対応、詳細は上記参照）
2. **より良いエラー表現**: 構造化エラー情報、詳細なスタックトレース
3. **デバッグ支援機能の強化**: より詳細な inspect 機能
4. **パフォーマンス最適化**: メモリ使用量の削減、実行速度の向上
5. **相互運用性**: 既存のPHPライブラリとの統合サポート

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

### Option型完全実装 (2025-07-13 実装)
- **アーキテクチャ**: Rust互換のOption<T>型をPHPで実現
- **実装クラス**: Option(interface), Some(final), None(final singleton)
- **主要メソッド**: isSome/isNone, map系, unwrap系, andThen, filter, inspect, 結合操作, contains
- **None設計**: シングルトンパターンでメモリ効率化
- **テスト**: 59テストケース（基本33 + transpose15 + 変換11）で網羅的検証
- **特徴**: Rustの仕様に忠実、既存のResult型との完全互換性

### transpose() メソッド (2025-07-13 実装)
- **機能**: Option/Result間の相互変換（Rust互換）
- **変換ルール**: 
  - Option側: Some(Ok(v))→Ok(Some(v)), Some(Err(e))→Err(e), None→Ok(None)
  - Result側: Ok(Some(v))→Some(Ok(v)), Ok(None)→None, Err(e)→Some(Err(e))
- **型安全性**: PHPStan対応のため戻り値型を`Result<mixed,mixed>`/`Option<mixed>`で明示
- **実装場所**: Result/Option両インターフェースとすべての実装クラス
- **テスト戦略**: 相互変換の完全性、エラー伝播、複合ケースを15テストで検証

### Option-Result相互変換 (2025-07-13 実装)
- **okOr()**: Option→Result変換（Noneを指定エラーでErr化）
- **okOrElse()**: Option→Result変換（Noneをクロージャ結果でErr化、遅延評価）
- **実装注意**: Some値は常にOkに、Noneは常にErrに変換
- **型安全性**: 戻り値型Result<mixed,mixed>でPHPStan対応

### flatten() メソッド (2025-07-13 実装)

#### Result型flatten()
- **機能**: ネストしたResultの一段階平坦化
- **Rust対応**: `Result<Result<T, E>, E>` → `Result<T, E>` の変換
- **動作**: Ok(Result) → Result、Ok(non-Result) → Ok、Err → Err
- **特徴**: 一段階のみ平坦化、多重ネストは段階的処理
- **型チェック**: `instanceof Result`による実行時判定
- **テスト**: 25テストケース、エッジケース・パフォーマンステスト含む

#### Option型flatten() (2025-07-13 実装)
- **機能**: ネストしたOptionの一段階平坦化（Rust互換）
- **Rust対応**: `Option<Option<T>>` → `Option<T>` の変換
- **動作ルール**:
  - Some(Some(value)) → Some(value)
  - Some(None) → None
  - Some(非Option値) → Some(非Option値) (自身を返す)
  - None → None (自身を返す)
- **型チェック**: `instanceof Option`による実行時判定
- **実装場所**: Option(interface), Some(final), None(final)
- **テスト**: 15テストケース（基本4 + エッジ7 + 型安全2 + パフォーマンス1 + 実用1）、1038アサーション

### contains() / containsErr() メソッド (2025-07-13 実装)
- **機能**: 値の存在確認（PHP独自実装、Rustには存在しない）
- **比較方法**: 厳密比較（`===`）を採用
- **動作**: Ok値での`containsErr()`、Err値での`contains()`は常に`false`
- **テスト**: 包括的エッジケーステスト実装済み（null、オブジェクト、配列、型変換）
- **Option型**: Some/Noneでも同様の動作、Noneでのcontains()は常にfalse

### and() メソッド (2025-07-13 実装)
- **機能**: 連続的な成功チェック（即座評価）
- **動作**: Okの場合は引数のResult、Errの場合は自身を返す
- **チェーン**: 複数のResultを順次結合可能
- **Option型**: Someの場合は引数のOption、Noneの場合は自身を返す

### Result型の高度変換メソッド (2025-07-13 実装)
- **ok()**: 成功値をOption<T>として取得（Rust互換）
  - Ok(value) → Some(value)
  - Err(error) → None
- **err()**: エラー値をOption<E>として取得（Rust互換）
  - Ok(value) → None
  - Err(error) → Some(error)
- **expectErr()**: エラー値のカスタムメッセージ付き取り出し（Rust互換）
  - Ok(value) → UnwrapException(message + value情報)
  - Err(error) → error
- **用途**: エラーハンドリングパターンの多様化、デバッグ体験向上
- **型安全性**: PHPStan対応の型アノテーション
- **テスト**: ResultConversionTest.php で15テストケース実装

### Option型の高度結合メソッド (2025-07-13 実装)
- **xor()**: 排他的OR操作（Rust互換）
  - Some(a).xor(None) → Some(a)
  - None.xor(Some(b)) → Some(b)
  - Some(a).xor(Some(b)) → None
  - None.xor(None) → None
- **zip()**: 2つのOptionの結合（Rust互換）
  - Some(a).zip(Some(b)) → Some([a, b])
  - Some(a).zip(None) → None
  - None.zip(Some(b)) → None
  - None.zip(None) → None
- **用途**: 条件分岐ロジックの簡潔化、複数値の同期処理
- **型安全性**: PHPDoc Genericsでarray{T, U}を表現
- **テスト**: OptionAdvancedTest.php で20テストケース実装

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
- Noneのシングルトンパターンによる制約（スレッドセーフティ）

### Option型固有の制限
- `replace()`メソッドは設計判断により非対応（readonly設計と破壊的代入の衝突）
- パフォーマンスクリティカルな処理では native null チェックの方が高速
- デバッグ時の値確認がResult型より複雑

## ショートハンドメソッド群（実装予定）

### 概要
コードの簡潔性と可読性を向上させるためのショートハンドメソッド群を実装予定です。これらのメソッドは既存メソッドの組み合わせを一つのメソッドで実現し、一般的なパターンを効率化します。

### Result型ショートハンドメソッド

#### isOkAnd(callable $predicate): bool
成功時のみ述語関数を実行し、その結果を返します。

```php
// 従来の書き方
if ($result->isOk() && $predicate($result->unwrap())) {
    // ...
}

// ショートハンドメソッド
if ($result->isOkAnd($predicate)) {
    // ...
}
```

**動作:**
- `Ok`の場合: 格納値に述語関数を適用し、その結果を返す
- `Err`の場合: 常に`false`を返す（述語関数は実行されない）

#### isErrAnd(callable $predicate): bool
失敗時のみ述語関数を実行し、その結果を返します。

```php
// 従来の書き方
if ($result->isErr() && $predicate($result->unwrapErr())) {
    // ...
}

// ショートハンドメソッド
if ($result->isErrAnd($predicate)) {
    // ...
}
```

**動作:**
- `Err`の場合: 格納エラーに述語関数を適用し、その結果を返す
- `Ok`の場合: 常に`false`を返す（述語関数は実行されない）

#### mapOr(callable $fn, mixed $default): mixed
成功時は値を変換し、失敗時はデフォルト値を返します。

```php
// 従来の書き方
$value = $result->isOk() 
    ? $fn($result->unwrap()) 
    : $default;

// ショートハンドメソッド
$value = $result->mapOr($fn, $default);
```

**動作:**
- `Ok`の場合: 格納値に関数を適用した結果を返す
- `Err`の場合: デフォルト値をそのまま返す

#### mapOrElse(callable $fn, callable $defaultFn): mixed
成功時は値を変換し、失敗時はエラー値を使ってデフォルト関数を実行します。

```php
// 従来の書き方
$value = $result->isOk() 
    ? $fn($result->unwrap()) 
    : $defaultFn($result->unwrapErr());

// ショートハンドメソッド
$value = $result->mapOrElse($fn, $defaultFn);
```

**動作:**
- `Ok`の場合: 格納値に変換関数を適用した結果を返す
- `Err`の場合: エラー値をデフォルト関数に渡した結果を返す（遅延評価）

### Option型ショートハンドメソッド

#### isSomeAnd(callable $predicate): bool
値を持ち、かつ述語関数を満たす場合のみtrueを返します。

```php
// 従来の書き方
if ($option->isSome() && $predicate($option->unwrap())) {
    // ...
}

// ショートハンドメソッド
if ($option->isSomeAnd($predicate)) {
    // ...
}
```

**動作:**
- `Some`の場合: 格納値に述語関数を適用し、その結果を返す
- `None`の場合: 常に`false`を返す（述語関数は実行されない）

### 使用例

#### 数値範囲チェック
```php
// Result型での使用
$parseResult = parseInteger($input);
$isValidRange = $parseResult->isOkAnd(fn($n) => $n >= 1 && $n <= 100);

// Option型での使用
$maybeAge = findUserAge($userId);
$isAdult = $maybeAge->isSomeAnd(fn($age) => $age >= 18);
```

#### エラータイプ判定
```php
$apiResult = callExternalAPI();
$isNetworkError = $apiResult->isErrAnd(fn($err) => $err instanceof NetworkException);
```

#### 変換とデフォルト値
```php
// Result型でのmapOr
$displayValue = $parseResult->mapOr(
    fn($num) => "値: $num",
    "無効な入力"
);

// Result型でのmapOrElse（動的デフォルト値）
$result = $apiCall->mapOrElse(
    fn($data) => processData($data),
    fn($error) => "エラー[{$error->getCode()}]: {$error->getMessage()}"
);
```

### 型安全性
全てのショートハンドメソッドは適切なPHPDoc型注釈を持ち、PHPStanレベルMAXでの型安全性を保証します。

```php
/**
 * @template T
 * @param callable(T): bool $predicate
 * @return bool
 */
public function isOkAnd(callable $predicate): bool;

/**
 * @template U
 * @param callable(T): U $fn
 * @param U $default
 * @return U
 */
public function mapOr(callable $fn, mixed $default): mixed;
```

### パフォーマンス特性
- 既存メソッドと同等のパフォーマンス
- 条件によっては関数実行がスキップされ、効率的
- メモリオーバーヘッドなし

この仕様書は、現在の実装状況と将来の拡張計画を含む、PHP Result/Option型ライブラリの完全な技術仕様を提供します。
