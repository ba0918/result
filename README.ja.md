# PHP Result/Option型ライブラリ

![PHP Version](https://img.shields.io/badge/php-%5E8.3-blue.svg)
![PHPStan](https://img.shields.io/badge/PHPStan-Level%20MAX-brightgreen.svg)
![Tests](https://github.com/ba0918/result/actions/workflows/quality-check.yml/badge.svg)
![Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen.svg)

[English](README.md)

RustのResult型とOption型をPHPで実装した、エラーハンドリングとnull安全性のためのライブラリです。

## 特徴

- **型安全なエラーハンドリング** - 例外やnullの代わりに明示的な成功/失敗を表現
- **関数型プログラミング** - メソッドチェーンによる宣言的なコード記述
- **Rust互換** - Rust標準ライブラリとの97%の仕様適合率
- **高品質** - PHPStan Level MAX、100%テストカバレッジ

## 目次

1. [インストール](#インストール)
2. [クイックスタート](#クイックスタート)
3. [ドキュメント](#ドキュメント)
4. [主な機能](#主な機能)
5. [要件](#要件)
6. [開発](#開発)
7. [ライセンス](#ライセンス)

## インストール

```bash
composer require ba0918/result
```

## クイックスタート

### Result型 - エラーハンドリング

```php
<?php
use ba0918\Result\{Ok, Err, Result};

// 割り算関数（ゼロ除算をエラーとして処理）
function safeDivide(float $a, float $b): Result
{
    if ($b === 0.0) {
        return Err::of("ゼロで割ることはできません");
    }
    return Ok::of($a / $b);
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
use ba0918\Result\{Some, None, Option};

// 配列から値を安全に取得
function findUser(int $id): Option
{
    $users = [1 => 'Alice', 2 => 'Bob'];
    
    if (isset($users[$id])) {
        return Some::of($users[$id]);
    }
    return None::instance();
}

// null安全な処理
$user = findUser(1)
    ->map(fn($name) => strtoupper($name))  // 見つかった場合は大文字化
    ->unwrapOr('Unknown');                 // 見つからない場合はデフォルト値

echo $user; // ALICE
```

## ドキュメント

### チュートリアル

- **[初心者向けチュートリアル](docs/ja/tutorial/getting_started.md)** - 30分で基本概念を理解
- **[基本的な使用方法](docs/ja/tutorial/basic_usage.md)** - 実用的なパターンと実例
- **[高度なパターン](docs/ja/tutorial/advanced_patterns.md)** - 上級者向けテクニック

### ガイド

- **[ベストプラクティス](docs/ja/guide/best_practices.md)** - プロジェクトでの実用的ガイドライン
- **[デバッグガイド](docs/ja/guide/debugging_guide.md)** - トラブルシューティングとデバッグ手法
- **[移行ガイド](docs/ja/guide/migration_guide.md)** - 既存コードからの段階的移行
- **[パフォーマンスガイド](docs/ja/guide/performance_guide.md)** - 最適化とベンチマーク
- **[IDE統合](docs/ja/guide/ide_integration.md)** - IDE設定とツール連携

### リファレンス

- **[Result APIリファレンス](docs/ja/api/result_api_reference.md)** - Result型の全メソッド詳細
- **[Option APIリファレンス](docs/ja/api/option_api_reference.md)** - Option型の全メソッド詳細
- **[仕様書](docs/ja/spec/specification.md)** - 技術仕様の完全版
- **[コーディングガイドライン](docs/ja/spec/coding_guideline.md)** - コントリビューター向け

### 比較

- **[Rust比較](docs/ja/comparison/rust_comparison.md)** - Rust標準ライブラリとの対応
- **[他ライブラリ比較](docs/ja/comparison/other_libraries.md)** - 技術選定の参考資料

### 実例

- **[実用例集](examples/ja/)** - コピー&ペーストで使える例

## 主な機能

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

### Pipe Operator対応API（PHP 8.5+）

```php
use function ba0918\Result\Pipe\{andThen, map, mapErr, orElse};

$response = $this->doSomething()
    |> andThen(fn ($value) => $this->transform($value))
    |> orElse(fn ($error) => $this->recover($error))
    |> andThen(fn ($value) => $this->respond($value));
```

`ba0918\Result\Pipe` の各関数は同名のResultメソッドへの薄いアダプターです。
各関数が返すClosureの入力型はcallableのパラメータ型に束縛され（`map(fn (int $v) ...)` は `Result<int, E>` を受け付ける）、
型の合わない`Result`をパイプに流すとPHPStanで静的エラーになります。
Pipe Operator自体は短絡評価をせず、短絡されるのは各演算子へ渡した業務callableのみです。
`|>` を使わず `andThen($op)($result)` のように直接呼び出せばPHP 8.3/8.4でも動作します。

## 要件

- PHP 8.3+（呼び出し側で`|>`構文を使う場合はPHP 8.5以降が必要）
- Composer

## 開発

```bash
# 依存関係のインストール
composer install

# テスト実行
composer test

# 静的解析
composer phpstan

# コードスタイルチェック
composer cs-check

# 全品質チェック
composer check
```

## ライセンス

MIT
