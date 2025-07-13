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

このライブラリはRustのResult型とOption型を模倣したPHP実装です：

**Result型（成功/失敗の表現）**
- `Mizumi\Result\Result` - 成功/失敗を表現するためのインターフェース（Generics対応）
- `Mizumi\Result\Ok` - 成功値を格納するimmutableクラス
- `Mizumi\Result\Err` - エラー値を格納するimmutableクラス

**Option型（値の有無の表現）**
- `Mizumi\Result\Option` - 値の有無を表現するためのインターフェース（Generics対応）
- `Mizumi\Result\Some` - 値を持つimmutableクラス
- `Mizumi\Result\None` - 値を持たないimmutableクラス（シングルトン）

**共通**
- `Mizumi\Result\Exception\UnwrapException` - unwrap系メソッドの失敗時にスローされる例外

## 技術スタック

- PHP: ^8.4
- PHPStan: ^2.1
- PHPUnit: ^12.2
- Composer: ^2.8

### 主要メソッド

**Result型メソッド**
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
- `transpose()` - Result<Option<T>, E> → Option<Result<T, E>>への変換

**Option型メソッド**
- `isSome()` / `isNone()` - 値の有無判定
- `map()` / `mapOr()` / `mapOrElse()` - 値の変換
- `andThen()` - モナド的チェーン処理
- `filter()` - 条件による値のフィルタリング
- `unwrap()` / `unwrapOr()` / `unwrapOrElse()` / `expect()` - 値の取り出し
- `inspect()` - デバッグ用副作用実行
- `or()` / `orElse()` / `and()` - Option間の結合操作
- `contains()` - 値の存在確認（厳密比較）
- `transpose()` - Option<Result<T, E>> → Result<Option<T>, E>への変換
- `okOr()` / `okOrElse()` - Option → Result変換

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

#### Option型完全実装 (2025-07-13 実装)
- **アーキテクチャ**: Rust互換のOption<T>型をPHPで実現
- **実装クラス**: Option(interface), Some(final), None(final singleton)
- **主要メソッド**: isSome/isNone, map系, unwrap系, andThen, filter, inspect, 結合操作, contains
- **None設計**: シングルトンパターンでメモリ効率化
- **テスト**: 59テストケース（基本33 + transpose15 + 変換11）で網羅的検証

#### transpose() メソッド (2025-07-13 実装)
- **機能**: Option/Result間の相互変換（Rust互換）
- **変換ルール**: 
  - Option側: Some(Ok(v))→Ok(Some(v)), Some(Err(e))→Err(e), None→Ok(None)
  - Result側: Ok(Some(v))→Some(Ok(v)), Ok(None)→None, Err(e)→Some(Err(e))
- **型安全性**: PHPStan対応のため戻り値型を`Result<mixed,mixed>`/`Option<mixed>`で明示
- **実装場所**: Result/Option両インターフェースとすべての実装クラス
- **テスト戦略**: 相互変換の完全性、エラー伝播、複合ケースを15テストで検証

#### Option-Result相互変換 (2025-07-13 実装)
- **okOr()**: Option→Result変換（Noneを指定エラーでErr化）
- **okOrElse()**: Option→Result変換（Noneをクロージャ結果でErr化、遅延評価）
- **実装注意**: Some値は常にOkに、Noneは常にErrに変換
- **型安全性**: 戻り値型Result<mixed,mixed>でPHPStan対応

#### PHPStan型エラー修正 (2025-07-13 実装)
- **課題**: PHPStanレベルMAXで34個の型エラーが発生（mixed型関連）
- **解決手法**: 
  - 型アサーション: `assert(is_int($x))` による実行時型チェック
  - PHPDocアノテーション: `/** @var Type $var */` による静的型情報提供
  - `@phpstan-ignore` による既知安全警告のサプレッション
- **対象ファイル**: OptionBasicTest.php(6箇所), OptionResultConversionTest.php(2箇所), TransposeTest.php(26箇所)
- **効果**: 型安全性大幅向上、IDEサポート強化、隠れた型不整合の発見
- **注意事項**: mixed型キャストは危険、型チェック→アサーション→PHPDocの優先順位

### プロジェクトメモリの更新検討について

TODOを完了したタイミングで、更新内容などからプロジェクトメモリの更新検討を行ってください
