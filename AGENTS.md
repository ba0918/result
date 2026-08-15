# AGENTS.md

このファイルはこのリポジトリのコードで作業する際のエージェント向けガイダンスです。

## 開発コマンド

```bash
# テスト実行
composer test

# 単一テスト実行
composer exec phpunit tests/SpecificTest.php

# テスト（カバレッジ付き）
composer test-coverage

# PHPStanでの静的解析（最大レベル）
composer phpstan

# PHPStanで|>を含むtests/Pipe85もPHP 8.5文法で解析（phpstan-php85.neon.dist）
composer phpstan:php85

# コードフォーマット（修正）
composer cs-fix

# コードフォーマット（チェック）
composer cs-check

# すべての品質チェック実行（cs-check → phpstan → test）
composer check

# Mutation Testing（MSI 100%を回帰ガードとして検証）
composer infection

# コード自動修正
composer fix

# Composerの依存関係をインストール
composer install
```

## テスト戦略

- `tests/RustDocGoldenTest.php`はRust標準ライブラリのdoc exampleを正準仕様として移植したゴールデンテスト
- 新規メソッド追加時は、自作のテストを先に書くのではなくRustのdoc exampleの移植を優先する
- Mutation Testing（Infection）はMSI 100%を回帰ガードとして使用する（`composer infection`）

## アーキテクチャ

このライブラリはRustのResult型とOption型を模倣したPHP実装です：

**Result型（成功/失敗の表現）**
- `ba0918\Result\Result` - 成功/失敗を表現するためのインターフェース（Generics対応）
- `ba0918\Result\Ok` - 成功値を格納するimmutableクラス
- `ba0918\Result\Err` - エラー値を格納するimmutableクラス

**Option型（値の有無の表現）**
- `ba0918\Result\Option` - 値の有無を表現するためのインターフェース（Generics対応）
- `ba0918\Result\Some` - 値を持つimmutableクラス
- `ba0918\Result\None` - 値を持たないimmutableクラス（シングルトン）

**Pipe Operator対応API（PHP 8.5の`|>`用）**
- `ba0918\Result\Pipe\functions.php` - 既存Resultメソッドへ委譲する関数アダプター群（`map`/`mapErr`/`andThen`/`orElse`/`inspect`/`inspectErr`）。composerの`autoload.files`で読み込まれる。`|>`を使うテストは`tests/Pipe85/`に置き、`composer test:php85`（phpunit-php85.xml.dist）で実行する（PHP 8.3/8.4ではパース不可のためデフォルトスイートから除外）

**共通**
- `ba0918\Result\Exception\UnwrapException` - unwrap系メソッドの失敗時にスローされる例外

## 技術スタック

- PHP: ^8.3
- PHPStan: ^2.1
- PHPUnit: ^12.2
- Composer: ^2.8

### 主要メソッド

**Result型メソッド**
- `isOk()` / `isErr()` - 成功/失敗判定
- `isOkAnd()` / `isErrAnd()` - 条件付き成功/失敗判定（ショートハンド）
- `map()` / `mapErr()` - 値/エラーの変換
- `mapOr()` / `mapOrElse()` - デフォルト値付き変換（ショートハンド）
- `andThen()` - モナド的チェーン処理
- `unwrap()` / `unwrapErr()` - 値/エラーの取り出し（例外あり）
- `unwrapOr()` / `unwrapOrElse()` - デフォルト値付きの安全な取り出し
- `expect()` / `expectErr()` - カスタムメッセージ付きの取り出し
- `inspect()` / `inspectErr()` - デバッグ用副作用実行
- `or()` / `orElse()` - 代替Resultの提供
- `and()` - 連続的な成功チェック（即座評価）
- `contains()` / `containsErr()` - 値の存在確認（厳密比較）
- `flatten()` - ネストしたResultの一段階平坦化
- `transpose()` - Result<Option<T>, E> → Option<Result<T, E>>への変換
- `ok()` / `err()` - Result → Option変換

**Option型メソッド**
- `isSome()` / `isNone()` - 値の有無判定
- `isSomeAnd()` - 条件付き値存在判定（ショートハンド）
- `map()` / `mapOr()` / `mapOrElse()` - 値の変換
- `andThen()` - モナド的チェーン処理
- `filter()` - 条件による値のフィルタリング
- `unwrap()` / `unwrapOr()` / `unwrapOrElse()` / `expect()` - 値の取り出し
- `inspect()` - デバッグ用副作用実行
- `or()` / `orElse()` / `and()` - Option間の結合操作
- `contains()` - 値の存在確認（厳密比較）
- `flatten()` - ネストしたOptionの一段階平坦化
- `transpose()` - Option<Result<T, E>> → Result<Option<T>, E>への変換
- `okOr()` / `okOrElse()` - Option → Result変換
- `xor()` - 排他的OR操作
- `zip()` - 2つのOptionの結合

### コード規約

- すべてのクラスは`final`で継承不可
- プロパティは`readonly`でimmutable
- PHPDoc アノテーションでGenericsを表現
- コメントは英語を使用（i18n方針、Rust標準ライブラリとの用語統一）
- `#[Override]`属性は`use Override;`のインポートを追加して使用
- `declare(strict_types=1);`はファイル先頭に別行で配置
- 文字列はシングルクォートで統一
- 詳細は `docs/en/spec/coding_guideline.md`（日本語版: `docs/ja/spec/coding_guideline.md`）を参照

### 開発時の注意点

- mixed型のキャストは危険。PHPStanエラーへの対処は「型チェック → アサーション（`assert()`）→ PHPDocアノテーション」の優先順位で行う
- 実行時型チェックが必要な箇所（`flatten()`等）では`instanceof`による判定を明示する
- 既知安全な警告のサプレッションには`@phpstan-ignore`を使用する
- 仕様変更は`docs/en/spec/`と`docs/ja/spec/`の両方を同時に更新する
- ドキュメント間の相互参照は相対パスで記述する（`/docs/...`の絶対パスはGitHub上で壊れる）

### 詳細仕様

- 英語: `docs/en/spec/specification.md`
- 日本語: `docs/ja/spec/specification.md`
