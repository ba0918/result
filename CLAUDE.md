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
- `inspect()` / `inspectErr()` - デバッグ用副作用実行
- `or()` / `orElse()` - 代替Resultの提供
- `and()` - 連続的な成功チェック（即座評価）
- `contains()` / `containsErr()` - 値の存在確認（厳密比較）
- `flatten()` - ネストしたResultの一段階平坦化

### コード規約

- すべてのクラスは`final`で継承不可
- プロパティは`readonly`でimmutable
- PHPDoc アノテーションでGenericsを表現
- 日本語コメントを使用
- 詳細は `docs/spec/coding_guideline.md` を参照してください

### 詳細仕様

`docs/spec/specification.md` を参照してください

### 実装履歴と注意点

#### contains() / containsErr() メソッド (2025-07-13 実装)
- **機能**: 値の存在確認（PHP独自実装、Rustには存在しない）
- **比較方法**: 厳密比較（`===`）を採用
- **動作**: Ok値での`containsErr()`、Err値での`contains()`は常に`false`
- **テスト**: 包括的エッジケーステスト実装済み（null、オブジェクト、配列、型変換）

#### and() メソッド (2025-07-13 実装)
- **機能**: 連続的な成功チェック（即座評価）
- **動作**: Okの場合は引数のResult、Errの場合は自身を返す
- **チェーン**: 複数のResultを順次結合可能

#### flatten() メソッド (2025-07-13 実装)
- **機能**: ネストしたResultの一段階平坦化（Rust互換）
- **型チェック**: `instanceof Result`による実行時判定が必要
- **PHPStan注意**: 型推論でmixed型の扱いに注意、キャスト時は安全な変換を使用
- **テスト戦略**: 25テストケースで包括的検証（基本動作、エッジケース、パフォーマンス）
- **実装パターン**: Ok(Result) → Result、Ok(non-Result) → self、Err → self

### プロジェクトメモリの更新検討について

TODOを完了したタイミングで、更新内容などからプロジェクトメモリの更新検討を行ってください
