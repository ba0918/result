# 初心者向けチュートリアル

関数型プログラミング未経験者でも30分でResult型・Option型の基本概念を理解できるチュートリアルです。

## 🎯 このチュートリアルで学べること

- Result型とOption型がなぜ必要なのか
- 従来のnull/例外処理との違い
- 基本的なメソッドの使い方
- 実際のコードでの使用例

## 📚 前提知識

- PHP 8.3+の基本的な文法
- クラスとインターフェースの概念
- 無名関数（クロージャ）の使い方

## 🤔 なぜResult型・Option型が必要なのか？

### 従来のPHPの問題点

#### 問題1: nullによる実行時エラー

```php
// 従来のPHPコード - 危険！
function findUser(int $id): ?array
{
    $users = [1 => ['name' => 'Alice'], 2 => ['name' => 'Bob']];
    return $users[$id] ?? null;
}

$user = findUser(999); // null が返される
echo $user['name'];    // Fatal Error: null に対する配列アクセス
```

#### 問題2: 例外の見落とし

```php
// 従来のPHPコード - 例外処理を忘れがち
function divide(float $a, float $b): float
{
    if ($b === 0.0) {
        throw new InvalidArgumentException("ゼロで割ることはできません");
    }
    return $a / $b;
}

// 例外処理を忘れるとクラッシュ
$result = divide(10, 0); // Exception: ゼロで割ることはできません
```

### Result型・Option型による解決

```php
use ba0918\Result\{Ok, Err, Some, None, Result, Option};

// Result型 - 成功/失敗を明示的に扱う
function safeDivide(float $a, float $b): Result
{
    if ($b === 0.0) {
        return new Err("ゼロで割ることはできません");
    }
    return new Ok($a / $b);
}

// Option型 - 値の有無を明示的に扱う
function findUser(int $id): Option
{
    $users = [1 => ['name' => 'Alice'], 2 => ['name' => 'Bob']];
    
    if (isset($users[$id])) {
        return new Some($users[$id]);
    }
    return None::instance();
}

// 型システムが安全性を保証
$result = safeDivide(10, 0);  // 必ずResult型
$user = findUser(999);        // 必ずOption型

// 実行時強制の型安全性（Result/Optionは常に明示的な処理を要求）
if ($result->isOk()) {
    echo "結果: " . $result->unwrap();
} else {
    echo "エラー: " . $result->unwrapErr();
}
```

## 📖 Result型の基本

### Result型とは

Result型は「成功」または「失敗」の2つの状態を持つ型です：

- **Ok(value)** - 成功を表し、成功時の値を保持
- **Err(error)** - 失敗を表し、エラー情報を保持

### 基本的な使い方

```php
<?php
use ba0918\Result\{Ok, Err, Result};

// 1. Result型を返す関数の作成
function validateAge(int $age): Result
{
    if ($age < 0) {
        return new Err("年齢は0以上である必要があります");
    }
    if ($age > 150) {
        return new Err("年齢は150以下である必要があります");
    }
    return new Ok($age);
}

// 2. 結果の確認
$result = validateAge(25);

// 成功かどうかの確認
if ($result->isOk()) {
    echo "有効な年齢: " . $result->unwrap();
} else {
    echo "エラー: " . $result->unwrapErr();
}
```

### 基本メソッド

#### isOk() / isErr() - 状態の確認

```php
$success = new Ok(42);
$failure = new Err("エラー");

var_dump($success->isOk());  // true
var_dump($success->isErr()); // false
var_dump($failure->isOk());  // false
var_dump($failure->isErr()); // true
```

#### unwrap() / unwrapErr() - 値の取り出し

```php
$success = new Ok(42);
$failure = new Err("エラー");

// 成功値の取り出し（失敗時は例外）
echo $success->unwrap();     // 42
// $failure->unwrap();       // UnwrapException が発生

// エラー値の取り出し（成功時は例外）
echo $failure->unwrapErr();  // "エラー"
// $success->unwrapErr();    // UnwrapException が発生
```

#### unwrapOr() - 安全な値の取り出し

```php
$success = new Ok(42);
$failure = new Err("エラー");

// デフォルト値を指定して安全に取り出し
echo $success->unwrapOr(0);  // 42（成功値）
echo $failure->unwrapOr(0);  // 0（デフォルト値）
```

## 📖 Option型の基本

### Option型とは

Option型は「値がある」または「値がない」の2つの状態を持つ型です：

- **Some(value)** - 値があることを表し、その値を保持
- **None** - 値がないことを表す（シングルトン）

### 基本的な使い方

```php
<?php
use ba0918\Result\{Some, None, Option};

// 1. Option型を返す関数の作成
function getConfig(string $key): Option
{
    $config = [
        'app_name' => 'MyApp',
        'debug' => true
    ];
    
    if (isset($config[$key])) {
        return new Some($config[$key]);
    }
    return None::instance();
}

// 2. 結果の確認
$value = getConfig('app_name');

if ($value->isSome()) {
    echo "設定値: " . $value->unwrap();
} else {
    echo "設定が見つかりませんでした";
}
```

### 基本メソッド

#### isSome() / isNone() - 状態の確認

```php
$some = new Some("値");
$none = None::instance();

var_dump($some->isSome());  // true
var_dump($some->isNone());  // false
var_dump($none->isSome());  // false
var_dump($none->isNone());  // true
```

#### unwrap() / unwrapOr() - 値の取り出し

```php
$some = new Some("値");
$none = None::instance();

// 値の取り出し（Noneの場合は例外）
echo $some->unwrap();       // "値"
// $none->unwrap();         // UnwrapException が発生

// デフォルト値を指定して安全に取り出し
echo $some->unwrapOr("デフォルト");  // "値"
echo $none->unwrapOr("デフォルト");  // "デフォルト"
```

## 🔄 基本的な変換操作

### map() - 値の変換

Result型やOption型の中身を変換するメソッドです。

```php
// Result型のmap
$result = new Ok(10);
$doubled = $result->map(fn($x) => $x * 2);
echo $doubled->unwrap(); // 20

// エラーの場合はそのまま
$error = new Err("エラー");
$mapped = $error->map(fn($x) => $x * 2);
echo $mapped->unwrapErr(); // "エラー"（変換されない）

// Option型のmap
$some = new Some("hello");
$upper = $some->map(fn($s) => strtoupper($s));
echo $upper->unwrap(); // "HELLO"

// Noneの場合はそのまま
$none = None::instance();
$mapped = $none->map(fn($s) => strtoupper($s));
var_dump($mapped->isNone()); // true（変換されない）
```

### andThen() - 連続的な処理

Result型やOption型を返す関数を連続して適用するメソッドです。

```php
function validatePositive(int $n): Result
{
    return $n > 0 ? new Ok($n) : new Err("正の数である必要があります");
}

function validateEven(int $n): Result
{
    return $n % 2 === 0 ? new Ok($n) : new Err("偶数である必要があります");
}

// 連続した検証
$result = new Ok(4)
    ->andThen('validatePositive')
    ->andThen('validateEven');

if ($result->isOk()) {
    echo "有効な値: " . $result->unwrap(); // "有効な値: 4"
}
```

## 🔄 実践的な例

### ファイル読み込みの安全な処理

```php
use ba0918\Result\{Ok, Err, Result};

function readConfigFile(string $path): Result
{
    if (!file_exists($path)) {
        return new Err("ファイルが存在しません: $path");
    }
    
    $content = file_get_contents($path);
    if ($content === false) {
        return new Err("ファイルの読み込みに失敗しました: $path");
    }
    
    $data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new Err("JSON解析エラー: " . json_last_error_msg());
    }
    
    return new Ok($data);
}

// 使用例
$config = readConfigFile('config.json')
    ->map(fn($data) => array_merge(['debug' => false], $data))
    ->unwrapOr(['debug' => false, 'app_name' => 'DefaultApp']);

echo "アプリ名: " . $config['app_name'];
```

### データベース検索の例

```php
use ba0918\Result\{Some, None, Option};

function findUserById(int $id): Option
{
    // 実際のDBアクセスをシミュレート
    $users = [
        1 => ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'],
        2 => ['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com'],
    ];
    
    if (isset($users[$id])) {
        return new Some($users[$id]);
    }
    return None::instance();
}

// 使用例
$userEmail = findUserById(1)
    ->map(fn($user) => $user['email'])
    ->unwrapOr('unknown@example.com');

echo "ユーザーメール: " . $userEmail; // alice@example.com
```

## ❓ よくある質問

### Q1: いつResult型を使うべきですか？

**A**: 失敗する可能性がある操作で、その失敗を適切にハンドリングしたい場合です。

- ファイル操作（読み込み、書き込み）
- ネットワーク通信（API呼び出し）
- データの検証・変換
- 外部サービスとの連携

### Q2: いつOption型を使うべきですか？

**A**: 値が存在しない可能性がある場合、nullの代わりに使います。

- データベースからの検索結果
- 配列・マップからの値取得
- 設定値の取得
- オプショナルなパラメータ

### Q3: 例外とResult型の使い分けは？

**A**: 以下のように使い分けます：

- **Result型**: 予期できる失敗、ビジネスロジックの一部
- **例外**: プログラムエラー、回復不可能な問題

```php
// Result型が適切な例
function divide(float $a, float $b): Result
{
    if ($b === 0.0) {
        return new Err("ゼロ除算は数学的に定義されていません");
    }
    return new Ok($a / $b);
}

// 例外が適切な例
function openFile(string $path): FileHandle
{
    $handle = fopen($path, 'r');
    if ($handle === false) {
        throw new RuntimeException("システムエラー: ファイルハンドルの作成に失敗");
    }
    return new FileHandle($handle);
}
```

### Q4: メソッドチェーンが複雑になりませんか？

**A**: 適切に改行とインデントを使うことで読みやすくなります：

```php
$result = readFile('data.json')
    ->andThen(fn($content) => parseJson($content))
    ->andThen(fn($data) => validateData($data))
    ->map(fn($data) => processData($data))
    ->unwrapOr(getDefaultData());
```

## 🚀 次のステップ

### 学習パス

1. **✅ 完了**: 基本概念の理解（このチュートリアル）
2. **➡️ 次**: [基本的な使用方法](basic_usage.md) - 実用的なパターンを学ぶ
3. **その後**: [高度なパターン](advanced_patterns.md) - より複雑な使用例

### より詳しく学ぶ

- **[ベストプラクティス](../guide/best_practices.md)** - プロジェクトでの実用的ガイドライン
- **[APIリファレンス](../api/result_api_reference.md)** - 全メソッドの詳細仕様
- **[実用例集](../../../examples/)** - コピー&ペーストで使える例

---

💡 **理解度チェック**: このチュートリアルの例を実際に動かしてみてください。実行可能なサンプルは[examples/](../../../examples/)にもあります。