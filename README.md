# PHP Result/Option型ライブラリ

![PHP Version](https://img.shields.io/badge/php-%5E8.4-blue.svg)
![PHPStan](https://img.shields.io/badge/PHPStan-Level%20MAX-brightgreen.svg)
![Tests](https://img.shields.io/badge/tests-passing-brightgreen.svg)
![Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen.svg)

RustのResult型とOption型をPHPで実装した、エラーハンドリングとnull安全性のためのライブラリです。

## 🚀 なぜこのライブラリを使うのか？

- **型安全なエラーハンドリング** - 例外やnullの代わりに明示的な成功/失敗を表現
- **関数型プログラミング** - メソッドチェーンによる宣言的なコード記述
- **Rust互換** - Rustエコシステムの知見を活用可能（97%の仕様適合率）
- **高品質** - PHPStan Level MAX、100%テストカバレッジ

## 📦 インストール

```bash
composer require mizumi/result
```

## ⚡ 5分でクイックスタート

### Result型 - エラーハンドリング

```php
<?php
use Mizumi\Result\{Ok, Err, Result};

// 割り算関数（ゼロ除算をエラーとして処理）
function safeDivide(float $a, float $b): Result
{
    if ($b === 0.0) {
        return new Err("ゼロで割ることはできません");
    }
    return new Ok($a / $b);
}

// エラーハンドリング
$result = safeDivide(10, 2);

if ($result->isOk()) {
    echo "結果: " . $result->unwrap(); // 結果: 5
} else {
    echo "エラー: " . $result->unwrapErr();
}

// メソッドチェーンでより簡潔に
$output = safeDivide(10, 2)
    ->map(fn($value) => $value * 2)        // 成功時は2倍
    ->unwrapOr(0);                         // 失敗時は0

echo $output; // 10
```

### Option型 - null安全性

```php
<?php
use Mizumi\Result\{Some, None, Option};

// 配列から値を安全に取得
function findUser(int $id): Option
{
    $users = [1 => 'Alice', 2 => 'Bob'];
    
    if (isset($users[$id])) {
        return new Some($users[$id]);
    }
    return None::instance();
}

// null安全な処理
$user = findUser(1)
    ->map(fn($name) => strtoupper($name))  // 見つかった場合は大文字化
    ->unwrapOr('Unknown');                 // 見つからない場合はデフォルト値

echo $user; // ALICE
```

## 📚 ドキュメント

### 📖 学習リソース
- **[初心者向けチュートリアル](docs/tutorial/getting_started.md)** - 30分で基本概念を理解
- **[基本的な使用方法](docs/tutorial/basic_usage.md)** - 実用的なパターンと実例
- **[高度なパターン](docs/tutorial/advanced_patterns.md)** - 上級者向けテクニック

### 📋 実用ガイド
- **[ベストプラクティス](docs/guide/best_practices.md)** - プロジェクトでの実用的ガイドライン
- **[移行ガイド](docs/guide/migration_guide.md)** - 既存コードからの段階的移行
- **[パフォーマンスガイド](docs/guide/performance_guide.md)** - 最適化とベンチマーク

### 🔧 リファレンス
- **[Result API](docs/api/result_api_reference.md)** - Result型の全メソッド詳細
- **[Option API](docs/api/option_api_reference.md)** - Option型の全メソッド詳細
- **[仕様書](docs/spec/specification.md)** - 技術仕様の完全版

### 🆚 比較・統合
- **[Rust比較](docs/comparison/rust_comparison.md)** - Rust標準ライブラリとの対応
- **[他ライブラリ比較](docs/comparison/other_libraries.md)** - 技術選定の参考資料

## 🔥 主な機能

### Result型メソッド
```php
// 基本メソッド
$result->isOk() / $result->isErr()           // 成功/失敗判定
$result->map($fn) / $result->mapErr($fn)     // 値/エラーの変換
$result->andThen($fn)                        // モナド的チェーン
$result->unwrap() / $result->unwrapOr($def)  // 値の取り出し

// ショートハンドメソッド
$result->isOkAnd($predicate)                 // 条件付き成功判定
$result->mapOr($fn, $default)                // デフォルト値付き変換
```

### Option型メソッド
```php
// 基本メソッド
$option->isSome() / $option->isNone()        // 値の有無判定
$option->map($fn)                            // 値の変換
$option->andThen($fn)                        // モナド的チェーン
$option->filter($predicate)                  // 条件フィルタリング

// 結合操作
$option->zip($other)                         // 2つのOptionを結合
$option->xor($other)                         // 排他的OR
```

## 🎯 実世界での使用例

```php
// API レスポンスの処理
function callApi(string $url): Result
{
    $response = file_get_contents($url);
    
    if ($response === false) {
        return new Err("API呼び出しに失敗しました");
    }
    
    return new Ok(json_decode($response, true));
}

$result = callApi('https://api.example.com/users')
    ->andThen(fn($data) => isset($data['users']) ? 
        new Ok($data['users']) : 
        new Err("不正なレスポンス形式"))
    ->map(fn($users) => array_filter($users, fn($user) => $user['active']))
    ->unwrapOr([]);

// 設定ファイルの読み込み
function loadConfig(string $path): Option
{
    if (!file_exists($path)) {
        return None::instance();
    }
    
    $content = file_get_contents($path);
    return new Some(json_decode($content, true));
}

$config = loadConfig('config.json')
    ->map(fn($cfg) => array_merge($defaultConfig, $cfg))
    ->unwrapOr($defaultConfig);
```

## ✅ 要件

- **PHP 8.4+**
- **Composer**

## 🛠️ 開発

```bash
# 依存関係インストール
composer install

# テスト実行
composer exec phpunit

# 静的解析
composer exec phpstan analyse
```

## 📈 品質指標

- **テストカバレッジ**: 100%
- **PHPStan**: Level MAX（最高レベル）
- **テスト件数**: 231+
- **Rust仕様適合率**: 97%+

## 📄 ライセンス

MIT License

## 🤝 コントリビューション

Issue、Pull Requestを歓迎します。開発に参加される場合は[コーディングガイドライン](docs/spec/coding_guideline.md)をご確認ください。

---

**Get started**: [初心者向けチュートリアル](docs/tutorial/getting_started.md) | **Examples**: [実用例集](examples/) | **API**: [リファレンス](docs/api/)