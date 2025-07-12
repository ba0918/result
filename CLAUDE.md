# CLAUDE.md

このファイルはこのリポジトリのコードで作業する際のClaude Codeのガイダンスを提供します。

## 開発コマンド

```bash
# テスト実行
composer exec phpunit

# 単一テスト実行
composer exec phpunit tests/SpecificTest.php

# PHPStanでの静的解析（最大レベル）
composer exec phpstan analyse

# Composerの依存関係をインストール
composer install
```

## アーキテクチャ

このライブラリはRustのResult型を模倣したPHP実装です：

- `Mizumi\Result\Result` - 成功/失敗を表現するためのインターフェース（Generics対応）
- `Mizumi\Result\Ok` - 成功値を格納するimmutableクラス
- `Mizumi\Result\Err` - エラー値を格納するimmutableクラス
- `Mizumi\Result\Exception\UnwrapException` - unwrap系メソッドの失敗時にスローされる例外

## 技術スタック

- PHP: ^8.4
- PHPStan: ^2.1
- PHPUnit: ^12.2
- Composer: ^2.8

### 主要メソッド

- `isOk()` / `isErr()` - 成功/失敗判定
- `map()` / `mapErr()` - 値/エラーの変換
- `andThen()` - モナド的チェーン処理
- `unwrap()` / `unwrapErr()` - 値/エラーの取り出し（例外あり）
- `unwrapOr()` / `unwrapOrElse()` - デフォルト値付きの安全な取り出し
- `expect()` - カスタムメッセージ付きの取り出し

### コード規約

- すべてのクラスは`final`で継承不可
- プロパティは`readonly`でimmutable
- PHPDoc アノテーションでGenericsを表現
- 日本語コメントを使用

### 詳細仕様

`@docs/spec/specification.md` を参照してください

### プロジェクトメモリの更新検討について

TODOを完了したタイミングで、更新内容などからプロジェクトメモリの更新検討を行ってください
