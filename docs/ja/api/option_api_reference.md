# Option型 API リファレンス

Option型は値の有無を表現する汎用的な型で、null安全性を提供するためのインターフェースです。

## 目次

1. [型定義](#型定義)
2. [状態確認メソッド](#状態確認メソッド)
3. [変換メソッド](#変換メソッド)
4. [値取得メソッド](#値取得メソッド)
5. [検査メソッド](#検査メソッド)
6. [フィルタリングメソッド](#フィルタリングメソッド)
7. [結合メソッド](#結合メソッド)
8. [ユーティリティメソッド](#ユーティリティメソッド)
9. [型間変換メソッド](#型間変換メソッド)
10. [高度な操作メソッド](#高度な操作メソッド)
11. [実装クラス](#実装クラス)

## 型定義

```php
/**
 * 値の有無を表現する為の型
 *
 * @template T 値の型
 */
interface Option
```

## 状態確認メソッド

### isSome(): bool

値を持っているかどうかを確認します。

**シグネチャ:**
```php
public function isSome(): bool
```

**戻り値:**
- `true`: Some状態（値あり）の場合
- `false`: None状態（値なし）の場合

**使用例:**
```php
$option = Some::of(42);
if ($option->isSome()) {
    echo "値あり: " . $option->unwrap();
}

$noneOption = None::instance();
var_dump($noneOption->isSome()); // false
```

**関連メソッド:** [isNone()](#isnone-bool), [isSomeAnd()](#issomeand)

---

### isNone(): bool

値を持っていないかどうかを確認します。

**シグネチャ:**
```php
public function isNone(): bool
```

**戻り値:**
- `true`: None状態（値なし）の場合
- `false`: Some状態（値あり）の場合

**使用例:**
```php
$option = None::instance();
if ($option->isNone()) {
    echo "値なし";
}

$someOption = Some::of("値");
var_dump($someOption->isNone()); // false
```

**関連メソッド:** [isSome()](#issome-bool), [contains()](#contains)

---

### isSomeAnd(callable $predicate): bool

値を持っていて、かつ述語関数を満たすかを確認します。

**シグネチャ:**
```php
/**
 * @param callable(T): bool $predicate
 * @return bool
 */
public function isSomeAnd(callable $predicate): bool
```

**パラメータ:**
- `$predicate`: 値に対する検証関数

**戻り値:**
- Some状態で述語を満たす場合: `true`
- None状態または述語を満たさない場合: `false`

**使用例:**
```php
$option = Some::of(10);
$isPositive = $option->isSomeAnd(fn($x) => $x > 0); // true
$isLarge = $option->isSomeAnd(fn($x) => $x > 100); // false

$noneOption = None::instance();
$anyCheck = $noneOption->isSomeAnd(fn($x) => true); // false (Noneなので)
```

**ショートハンドメソッド:** より簡潔な条件確認が可能

**実践例:**
```php
// ユーザー権限の確認
$user = $session->getUser();
$isAdmin = $user->isSomeAnd(fn($u) => $u->hasRole('admin'));
```

**関連メソッド:** [isSome()](#issome-bool), [contains()](#contains), [filter()](#filter)

## 変換メソッド

### map(callable $fn): Option

値に関数を適用して新しいOptionを作成します。

**シグネチャ:**
```php
/**
 * @template U
 * @param callable(T): U $fn
 * @return Option<U>
 */
public function map(callable $fn): Option
```

**パラメータ:**
- `$fn`: 値に適用する変換関数

**戻り値:**
- Some状態: 変換後の値を持つSome
- None状態: None（変換は実行されない）

**使用例:**
```php
$option = Some::of(5);
$doubled = $option->map(fn($x) => $x * 2); // Some(10)

$noneOption = None::instance();
$notExecuted = $noneOption->map(fn($x) => $x * 2); // None - 関数は実行されない
```

**文字列操作の例:**
```php
$name = Some::of("john");
$capitalized = $name->map(fn($n) => ucfirst($n)); // Some("John")

$emptyName = None::instance();
$unchanged = $emptyName->map(fn($n) => ucfirst($n)); // None
```

**チェーン操作:**
```php
$result = Some::of("  hello world  ")
    ->map(fn($s) => trim($s))
    ->map(fn($s) => strtoupper($s))
    ->map(fn($s) => str_replace(' ', '_', $s)); // Some("HELLO_WORLD")
```

**関連メソッド:** [mapOr()](#mapor), [mapOrElse()](#maporelse), [andThen()](#andthen)

---

### mapOr(callable $fn, mixed $default): mixed

値に関数を適用するか、値がない場合はデフォルト値を返します。

**シグネチャ:**
```php
/**
 * @template U
 * @param callable(T): U $fn
 * @param U $default
 * @return U
 */
public function mapOr(callable $fn, mixed $default): mixed
```

**パラメータ:**
- `$fn`: 値に適用する変換関数
- `$default`: 値がない場合のデフォルト値

**戻り値:**
- Some状態: `$fn`を適用した結果
- None状態: `$default`

**使用例:**
```php
$option = Some::of(5);
$doubled = $option->mapOr(fn($x) => $x * 2, 0); // 10

$noneOption = None::instance();
$defaultUsed = $noneOption->mapOr(fn($x) => $x * 2, 0); // 0
```

**実践例:**
```php
// 設定値の変換
$configValue = $config->get('timeout')
    ->mapOr(fn($t) => $t * 1000, 5000); // デフォルト5秒

// ユーザー表示名の生成
$displayName = $user->getName()
    ->mapOr(fn($name) => "こんにちは、{$name}さん", "ゲストユーザー");
```

**ショートハンドの効果:**
```php
// 従来の書き方
$value = $option->map(fn($x) => $x * 2)->unwrapOr(0);

// mapOr()を使用
$value = $option->mapOr(fn($x) => $x * 2, 0); // より効率的
```

**関連メソッド:** [map()](#map), [mapOrElse()](#maporelse), [unwrapOr()](#unwrapor)

---

### mapOrElse(callable $fn, callable $defaultFn): mixed

値に関数を適用するか、値がない場合はクロージャを実行します。

**シグネチャ:**
```php
/**
 * @template U
 * @param callable(T): U $fn
 * @param callable(): U $defaultFn
 * @return U
 */
public function mapOrElse(callable $fn, callable $defaultFn): mixed
```

**パラメータ:**
- `$fn`: 値に適用する変換関数
- `$defaultFn`: 値がない場合に実行するクロージャ

**戻り値:**
- Some状態: `$fn`を適用した結果
- None状態: `$defaultFn`を実行した結果

**使用例:**
```php
$option = Some::of(5);
$result = $option->mapOrElse(
    fn($x) => $x * 2,
    fn() => time()
); // 10

$noneOption = None::instance();
$timestamp = $noneOption->mapOrElse(
    fn($x) => $x * 2,
    fn() => time()
); // 現在のタイムスタンプ
```

**遅延評価の利点:**
```php
// 重い処理は値がない場合のみ実行される
$result = $cache->get('expensive_data')
    ->mapOrElse(
        fn($data) => $data->process(),
        fn() => $this->calculateExpensiveDefault() // Some時は実行されない
    );
```

**動的デフォルト値:**
```php
$message = $user->getLastLogin()
    ->mapOrElse(
        fn($login) => "最終ログイン: " . $login->format('Y-m-d'),
        fn() => "初回ログインです（" . date('Y-m-d') . "）"
    );
```

**関連メソッド:** [mapOr()](#mapor), [unwrapOrElse()](#unwraporelse), [orElse()](#orelse)

---

### andThen(callable $fn): Option

値に対してOptionを返す関数を適用します（モナド的チェーン）。

**シグネチャ:**
```php
/**
 * @template U
 * @param callable(T): Option<U> $fn
 * @return Option<U>
 */
public function andThen(callable $fn): Option
```

**パラメータ:**
- `$fn`: 値を受け取ってOptionを返す関数

**戻り値:**
- Some状態: `$fn`が返すOption
- None状態: None

**使用例:**
```php
function parseNumber(string $str): Option {
    return is_numeric($str) ? Some::of((int)$str) : None::instance();
}

$option = Some::of("42")
    ->andThen(fn($str) => parseNumber($str))  // Some(42)
    ->andThen(fn($num) => $num > 0 ? Some::of($num) : None::instance()); // Some(42)

$invalidOption = Some::of("abc")
    ->andThen(fn($str) => parseNumber($str)); // None
```

**複雑なチェーン:**
```php
$result = $this->getUser($id)
    ->andThen(fn($user) => $this->loadProfile($user))
    ->andThen(fn($profile) => $this->checkPermissions($profile))
    ->andThen(fn($profile) => $this->formatUserData($profile));
```

**flatMapとの関係:** `andThen`は他の言語の`flatMap`に相当

**関連メソッド:** [map()](#map), [filter()](#filter), [flatten()](#flatten)

## 値取得メソッド

### unwrap(): mixed

値を取得します。値がない場合は例外をスローします。

**シグネチャ:**
```php
/**
 * @return T
 * @throws UnwrapException
 */
public function unwrap(): mixed
```

**戻り値:**
- Some状態: 値
- None状態: UnwrapExceptionをスロー

**使用例:**
```php
$option = Some::of(42);
$value = $option->unwrap(); // 42

$noneOption = None::instance();
try {
    $value = $noneOption->unwrap(); // UnwrapException
} catch (UnwrapException $e) {
    echo $e->getMessage(); // "None value"
}
```

**⚠️ 注意事項:**
- 予期しない例外を避けるため、事前に`isSome()`でチェックするか、`unwrapOr()`を使用することを推奨
- プロダクションコードでは慎重に使用

**安全な使用例:**
```php
if ($option->isSome()) {
    $value = $option->unwrap(); // 安全
}
```

**関連メソッド:** [unwrapOr()](#unwrapor), [unwrapOrElse()](#unwraporelse), [expect()](#expect)

---

### unwrapOr(mixed $default): mixed

値を取得するか、値がない場合はデフォルト値を返します。

**シグネチャ:**
```php
/**
 * @template U
 * @param U $default
 * @return T|U
 */
public function unwrapOr(mixed $default): mixed
```

**パラメータ:**
- `$default`: 値がない場合のデフォルト値

**戻り値:**
- Some状態: 値
- None状態: `$default`

**使用例:**
```php
$option = Some::of(42);
$value = $option->unwrapOr(0); // 42

$noneOption = None::instance();
$value = $noneOption->unwrapOr(0); // 0
```

**実践例:**
```php
// 設定値の取得
$maxItems = $config->getMaxItems()->unwrapOr(10);

// ユーザー設定
$theme = $user->getPreference('theme')->unwrapOr('default');

// API レスポンス
$timeout = $response->getTimeout()->unwrapOr(30);
```

**関連メソッド:** [unwrap()](#unwrap), [unwrapOrElse()](#unwraporelse), [mapOr()](#mapor)

---

### unwrapOrElse(callable $fn): mixed

値を取得するか、値がない場合はクロージャの結果を返します。

**シグネチャ:**
```php
/**
 * @template U
 * @param callable(): U $fn
 * @return T|U
 */
public function unwrapOrElse(callable $fn): mixed
```

**パラメータ:**
- `$fn`: 値がない場合に実行するクロージャ

**戻り値:**
- Some状態: 値
- None状態: `$fn`の実行結果

**使用例:**
```php
$option = Some::of(42);
$value = $option->unwrapOrElse(fn() => time()); // 42

$noneOption = None::instance();
$value = $noneOption->unwrapOrElse(fn() => time()); // 現在のタイムスタンプ
```

**遅延評価の利点:**
```php
// 重い処理は値がない場合のみ実行
$value = $cache->get('data')
    ->unwrapOrElse(fn() => $this->computeExpensiveValue());
```

**動的デフォルト生成:**
```php
$sessionId = $session->getId()
    ->unwrapOrElse(fn() => $this->generateNewSessionId());
```

**関連メソッド:** [unwrapOr()](#unwrapor), [mapOrElse()](#maporelse), [orElse()](#orelse)

---

### expect(string $message): mixed

値を取得します。値がない場合はカスタムメッセージで例外をスローします。

**シグネチャ:**
```php
/**
 * @param string $message
 * @return T
 * @throws UnwrapException
 */
public function expect(string $message): mixed
```

**パラメータ:**
- `$message`: 値がない場合の例外メッセージ

**戻り値:**
- Some状態: 値
- None状態: カスタムメッセージでUnwrapExceptionをスロー

**使用例:**
```php
$option = Some::of(42);
$value = $option->expect("値が必要です"); // 42

$noneOption = None::instance();
try {
    $value = $noneOption->expect("設定ファイルが見つかりません");
} catch (UnwrapException $e) {
    echo $e->getMessage(); // "設定ファイルが見つかりません"
}
```

**デバッグでの活用:**
```php
// 期待される状況を明確にする
$config = $this->loadConfig()
    ->expect("設定ファイルの読み込みが必要");

$user = $session->getAuthenticatedUser()
    ->expect("認証済みユーザーが必要");
```

**関連メソッド:** [unwrap()](#unwrap), [unwrapOr()](#unwrapor)

## 検査メソッド

### inspect(callable $fn): Option

値を検査し、副作用を実行します（値は変更されません）。

**シグネチャ:**
```php
/**
 * @param callable(T): void $fn
 * @return Option<T>
 */
public function inspect(callable $fn): Option
```

**パラメータ:**
- `$fn`: 値を受け取る検査関数（副作用のみ）

**戻り値:** 元のOption（変更されない）

**使用例:**
```php
$option = Some::of(42)
    ->inspect(fn($x) => error_log("Value: $x"))
    ->map(fn($x) => $x * 2);
// ログ出力: "Value: 42"
// 結果: Some(84)

$noneOption = None::instance()
    ->inspect(fn($x) => error_log("Value: $x")); // 実行されない
```

**デバッグでの活用:**
```php
$result = $this->processUser($userId)
    ->inspect(fn($user) => $this->logUserAccess($user))
    ->map(fn($user) => $user->getProfile())
    ->inspect(fn($profile) => $this->trackProfileView($profile));
```

**条件付きログ:**
```php
$debugMode = $_ENV['DEBUG'] ?? false;

$result = $data->getProcessedValue()
    ->inspect(function($value) use ($debugMode) {
        if ($debugMode) {
            error_log("Processed value: " . json_encode($value));
        }
    });
```

**関連メソッド:** [map()](#map), [filter()](#filter)

## フィルタリングメソッド

### filter(callable $predicate): Option

述語関数を満たす場合のみ値を保持します。

**シグネチャ:**
```php
/**
 * @param callable(T): bool $predicate
 * @return Option<T>
 */
public function filter(callable $predicate): Option
```

**パラメータ:**
- `$predicate`: 値を評価する述語関数

**戻り値:**
- Some状態で述語を満たす: 元のSome
- Some状態で述語を満たさない、またはNone状態: None

**使用例:**
```php
$option = Some::of(10);
$positive = $option->filter(fn($x) => $x > 0); // Some(10)
$negative = $option->filter(fn($x) => $x < 0); // None

$noneOption = None::instance();
$filtered = $noneOption->filter(fn($x) => true); // None
```

**実践例:**
```php
// 有効なユーザーのフィルタリング
$activeUser = $this->getUser($id)
    ->filter(fn($user) => $user->isActive())
    ->filter(fn($user) => !$user->isBanned());

// 数値の範囲チェック
$validAge = $input->getAge()
    ->filter(fn($age) => $age >= 0 && $age <= 150);

// 権限チェック
$authorizedUser = $session->getUser()
    ->filter(fn($user) => $user->hasPermission('admin'));
```

**チェーン操作:**
```php
$result = $data->getValue()
    ->filter(fn($val) => is_string($val))
    ->filter(fn($val) => strlen($val) > 0)
    ->filter(fn($val) => !str_contains($val, 'invalid'));
```

**関連メソッド:** [isSomeAnd()](#issomeand), [map()](#map), [andThen()](#andthen)

## 結合メソッド

### or(Option $opt): Option

Noneの場合に代替のOptionを返します（即座評価）。

**シグネチャ:**
```php
/**
 * @template U
 * @param Option<U> $opt
 * @return Option<T|U>
 */
public function or(Option $opt): Option
```

**パラメータ:**
- `$opt`: 代替のOption

**戻り値:**
- Some状態: 元のSome
- None状態: `$opt`

**使用例:**
```php
$primary = Some::of(42);
$fallback = Some::of(100);
$result = $primary->or($fallback); // Some(42)

$primaryNone = None::instance();
$fallbackSome = Some::of(100);
$result = $primaryNone->or($fallbackSome); // Some(100)
```

**複数のフォールバック:**
```php
$result = $this->getFromCache($key)
    ->or($this->getFromDatabase($key))
    ->or($this->getFromBackup($key))
    ->or(Some::of($this->getDefault()));
```

**設定値の優先順位:**
```php
$config = $userConfig
    ->or($projectConfig)
    ->or($globalConfig)
    ->or(Some::of($defaultConfig));
```

**関連メソッド:** [orElse()](#orelse), [and()](#and), [unwrapOr()](#unwrapor)

---

### orElse(callable $fn): Option

Noneの場合に代替のOptionを返します（遅延評価）。

**シグネチャ:**
```php
/**
 * @template U
 * @param callable(): Option<U> $fn
 * @return Option<T|U>
 */
public function orElse(callable $fn): Option
```

**パラメータ:**
- `$fn`: 代替のOptionを返すクロージャ

**戻り値:**
- Some状態: 元のSome
- None状態: `$fn`の実行結果

**使用例:**
```php
$result = None::instance()
    ->orElse(fn() => Some::of("代替値")); // Some("代替値")

$result = Some::of(42)
    ->orElse(fn() => Some::of("代替値")); // Some(42) - クロージャは実行されない
```

**遅延評価の利点:**
```php
// 重い処理はNone時のみ実行される
$result = $cache->get('data')
    ->orElse(fn() => $this->computeExpensiveAlternative());
```

**条件付きフォールバック:**
```php
$result = $primary->orElse(function() {
    if ($this->isOnline()) {
        return $this->fetchFromRemote();
    }
    return $this->getLocalFallback();
});
```

**関連メソッド:** [or()](#or), [unwrapOrElse()](#unwraporelse), [mapOrElse()](#maporelse)

---

### and(Option $opt): Option

Someの場合に別のOptionを返し、Noneの場合は自身を返します（即座評価）。

**シグネチャ:**
```php
/**
 * @template U
 * @param Option<U> $opt
 * @return Option<U>
 */
public function and(Option $opt): Option
```

**パラメータ:**
- `$opt`: 継続するOption

**戻り値:**
- Some状態: `$opt`
- None状態: None

**使用例:**
```php
$step1 = Some::of("ステップ1完了");
$step2 = Some::of("ステップ2完了");
$result = $step1->and($step2); // Some("ステップ2完了")

$step1None = None::instance();
$step2 = Some::of("ステップ2完了");
$result = $step1None->and($step2); // None
```

**バリデーションチェーン:**
```php
$result = $this->validateRequired($input)
    ->and($this->validateFormat($input))
    ->and($this->validateBusinessRules($input));
```

**andThenとの違い:** `and`は値を使わない継続、`andThen`は値を使った変換

**関連メソッド:** [andThen()](#andthen), [or()](#or), [filter()](#filter)

## ユーティリティメソッド

### contains(mixed $value): bool

Some値が指定された値を含むかどうかを確認します。

**シグネチャ:**
```php
/**
 * @param mixed $value 確認したい値
 * @return bool Some値が指定値と厳密に等価な場合true、それ以外はfalse
 */
public function contains(mixed $value): bool
```

**パラメータ:**
- `$value`: 確認したい値

**戻り値:**
- Some状態で値が一致: `true`
- Some状態で値が不一致またはNone状態: `false`

**使用例:**
```php
$option = Some::of(42);
var_dump($option->contains(42));   // true
var_dump($option->contains("42")); // false (厳密比較)
var_dump($option->contains(100));  // false

$noneOption = None::instance();
var_dump($noneOption->contains(42)); // false (Noneなので)
```

**厳密比較の重要性:**
```php
$option = Some::of([1, 2, 3]);
var_dump($option->contains([1, 2, 3])); // true
var_dump($option->contains([1, 2]));    // false

$objectOption = Some::of(new stdClass());
var_dump($objectOption->contains(new stdClass())); // false (異なるインスタンス)
```

**実践例:**
```php
// 権限チェック
$hasAdminRole = $user->getRoles()->contains('admin');

// 設定値の確認
$isDebugMode = $config->getMode()->contains('debug');
```

**PHP独自機能:** Rustの標準Option型にはない、PHP向けの便利メソッド

**関連メソッド:** [isSomeAnd()](#issomeand), [filter()](#filter)

---

### flatten(): Option

ネストしたOptionを一段階平坦化します。

**シグネチャ:**
```php
/**
 * @return Option<mixed>
 */
public function flatten(): Option
```

**戻り値:**
- Some(Option): 内側のOption
- Some(非Option): 元のSome
- None: None

**使用例:**
```php
// ネストしたOption
$nested = Some::of(Some::of(42));
$flattened = $nested->flatten(); // Some(42)

// 非Option値
$notNested = Some::of(42);
$unchanged = $notNested->flatten(); // Some(42)

// Noneケース
$none = None::instance();
$stillNone = $none->flatten(); // None
```

**複雑なネスト:**
```php
// Some(Some(None))
$deepNested = Some::of(Some::of(None::instance()));
$oneLevel = $deepNested->flatten(); // Some(None)
$fullyFlat = $oneLevel->flatten();  // None
```

**実践例:**
```php
// 条件付きでOptionを返す関数の結果をフラット化
function findUserMaybe(int $id): Option {
    return $id > 0 ? Some::of($this->findUser($id)) : None::instance();
}

$user = Some::of(42)
    ->map(fn($id) => $this->findUserMaybe($id))
    ->flatten(); // Some(User) または None
```

**関連メソッド:** [andThen()](#andthen), [map()](#map)

## 型間変換メソッド

### transpose(): Result

Option<Result<T, E>> → Result<Option<T>, E> への変換を行います。

**シグネチャ:**
```php
/**
 * @return Result<mixed, mixed>
 */
public function transpose(): Result
```

**変換ルール:**
- `Some(Ok(value))` → `Ok(Some(value))`
- `Some(Err(error))` → `Err(error)`
- `None` → `Ok(None)`
- `Some(非Result)` → `Ok(Some(value))`

**使用例:**
```php
// Some(Ok(value)) → Ok(Some(value))
$option = Some::of(Ok::of(42));
$result = $option->transpose(); // Ok(Some(42))

// Some(Err(error)) → Err(error)
$option = Some::of(Err::of("エラー"));
$result = $option->transpose(); // Err("エラー")

// None → Ok(None)
$option = None::instance();
$result = $option->transpose(); // Ok(None)
```

**実践的な使用例:**
```php
// バッチ処理での結果集約
$items = [1, 2, 3];
$results = array_map(fn($id) => 
    $this->findItem($id)  // Option<Item>
        ->map(fn($item) => $this->validateItem($item)), // Option<Result<Item, Error>>
    $items
);

$transposed = array_map(fn($opt) => $opt->transpose(), $results);
// [Ok(Some(item1)), Err("validation_error"), Ok(Some(item3))]
```

**Rust互換性:** Rustの標準ライブラリと同じ変換ルール

**関連メソッド:** [okOr()](#okor), [okOrElse()](#okorelse), [Result::transpose()](/docs/api/result_api_reference.md#transpose)

---

### okOr(mixed $err): Result

OptionをResultに変換します（Noneの場合は指定されたエラーでErr）。

**シグネチャ:**
```php
/**
 * @param mixed $err
 * @return Result<mixed, mixed>
 */
public function okOr(mixed $err): Result
```

**パラメータ:**
- `$err`: None時のエラー値

**戻り値:**
- Some状態: `Ok(value)`
- None状態: `Err($err)`

**使用例:**
```php
$option = Some::of(42);
$result = $option->okOr("エラー"); // Ok(42)

$noneOption = None::instance();
$result = $noneOption->okOr("値がありません"); // Err("値がありません")
```

**実践例:**
```php
// 必須フィールドの検証
$name = $input->getName()
    ->okOr("名前は必須です");

// 設定値の取得
$config = $this->loadConfig()
    ->okOr("設定ファイルが見つかりません");

// ユーザー認証
$user = $session->getUser()
    ->okOr("認証が必要です");
```

**エラー情報の構造化:**
```php
$result = $data->getValue()
    ->okOr([
        'type' => 'missing_value',
        'message' => 'Required value not found',
        'timestamp' => time()
    ]);
```

**関連メソッド:** [okOrElse()](#okorelse), [transpose()](#transpose), [unwrapOr()](#unwrapor)

---

### okOrElse(callable $fn): Result

OptionをResultに変換します（Noneの場合はクロージャの結果でErr）。

**シグネチャ:**
```php
/**
 * @param callable $fn
 * @return Result<mixed, mixed>
 */
public function okOrElse(callable $fn): Result
```

**パラメータ:**
- `$fn`: None時にエラー値を生成するクロージャ

**戻り値:**
- Some状態: `Ok(value)`
- None状態: `Err($fn())`

**使用例:**
```php
$option = Some::of(42);
$result = $option->okOrElse(fn() => "動的エラー"); // Ok(42)

$noneOption = None::instance();
$result = $noneOption->okOrElse(fn() => "生成されたエラー"); // Err("生成されたエラー")
```

**動的エラー生成:**
```php
$result = $cache->get($key)
    ->okOrElse(fn() => "Cache miss for key: {$key} at " . date('Y-m-d H:i:s'));

$user = $session->getUser()
    ->okOrElse(fn() => [
        'error' => 'authentication_required',
        'redirect_url' => '/login',
        'timestamp' => time()
    ]);
```

**遅延評価の利点:**
```php
// 重い処理は実際にエラーが必要な場合のみ実行
$result = $primaryData
    ->okOrElse(fn() => $this->generateDetailedErrorReport());
```

**関連メソッド:** [okOr()](#okor), [unwrapOrElse()](#unwraporelse), [orElse()](#orelse)

## 高度な操作メソッド

### xor(Option $opt): Option

排他的OR操作：片方のみSomeの場合にSome、両方Some/両方Noneの場合にNone。

**シグネチャ:**
```php
/**
 * @template U
 * @param Option<U> $opt
 * @return Option<T|U>
 */
public function xor(Option $opt): Option
```

**パラメータ:**
- `$opt`: 比較するOption

**戻り値:**
- 片方のみSome: その値を持つSome
- 両方Some または 両方None: None

**使用例:**
```php
$a = Some::of(1);
$b = Some::of(2);
$result = $a->xor($b); // None (両方Some)

$a = Some::of(1);
$b = None::instance();
$result = $a->xor($b); // Some(1) (片方のみSome)

$a = None::instance();
$b = Some::of(2);
$result = $a->xor($b); // Some(2) (片方のみSome)

$a = None::instance();
$b = None::instance();
$result = $a->xor($b); // None (両方None)
```

**実践例:**
```php
// 設定の排他制御
$useCache = $config->getCacheEnabled();
$useDatabase = $config->getDatabaseEnabled();
$storage = $useCache->xor($useDatabase); // 片方のみ有効な場合に値を取得

// ユーザー選択の排他性
$emailNotify = $user->getEmailNotification();
$smsNotify = $user->getSmsNotification();
$notification = $emailNotify->xor($smsNotify); // 片方のみ選択されている場合
```

**論理的排他性の確認:**
```php
// 2つの条件のうち正確に1つが真の場合のみ処理
$developmentMode = $env->isDevelopment();
$testingMode = $env->isTesting();
$singleMode = $developmentMode->xor($testingMode);

if ($singleMode->isSome()) {
    echo "単一モードで実行中";
}
```

**関連メソッド:** [or()](#or), [and()](#and), [zip()](#zip)

---

### zip(Option $opt): Option

2つのOptionを結合：両方Someの場合にタプル、片方でもNoneの場合にNone。

**シグネチャ:**
```php
/**
 * @template U
 * @param Option<U> $opt
 * @return Option<array{T, U}>
 */
public function zip(Option $opt): Option
```

**パラメータ:**
- `$opt`: 結合するOption

**戻り値:**
- 両方Some: タプル（配列）を持つSome
- 片方でもNone: None

**使用例:**
```php
$a = Some::of(1);
$b = Some::of("hello");
$result = $a->zip($b); // Some([1, "hello"])

$a = Some::of(1);
$b = None::instance();
$result = $a->zip($b); // None

$a = None::instance();
$b = Some::of("hello");
$result = $a->zip($b); // None
```

**実践例:**
```php
// ユーザー情報の結合
$firstName = $user->getFirstName();
$lastName = $user->getLastName();
$fullName = $firstName->zip($lastName)
    ->map(fn($names) => $names[0] . ' ' . $names[1]);

// 座標の結合
$x = $input->getX();
$y = $input->getY();
$coordinate = $x->zip($y)
    ->map(fn($coords) => ['x' => $coords[0], 'y' => $coords[1]]);

// バリデーション結果の結合
$email = $this->validateEmail($input);
$password = $this->validatePassword($input);
$credentials = $email->zip($password)
    ->map(fn($creds) => ['email' => $creds[0], 'password' => $creds[1]]);
```

**複数の値の結合:**
```php
// zip3の実装例
function zip3(Option $a, Option $b, Option $c): Option {
    return $a->zip($b)
        ->andThen(fn($ab) => $c->map(fn($c_val) => [...$ab, $c_val]));
}

$result = zip3(Some::of(1), Some::of(2), Some::of(3)); // Some([1, 2, 3])
```

**配列の分解:**
```php
$zipped = $option1->zip($option2);
if ($zipped->isSome()) {
    [$first, $second] = $zipped->unwrap();
    // $first, $secondを使用
}
```

**関連メソッド:** [and()](#and), [xor()](#xor), [map()](#map)

## 実装クラス

### Some<T>

値を持つOptionを表すクラス。

```php
final class Some implements Option
{
    public function __construct(private readonly mixed $value) {}
    public static function of(mixed $value): self {}
}
```

**使用例:**
```php
$some = Some::of(42);
$some = Some::of("文字列");
$some = Some::of(['key' => 'value']);
$some = Some::of(new User());
```

### None

値を持たないOptionを表すクラス（シングルトン）。

```php
final class None implements Option
{
    private static ?self $instance = null;
    
    private function __construct() {}
    
    public static function instance(): self {}
}
```

**使用例:**
```php
$none = None::instance(); // 常に同じインスタンス
```

**シングルトンパターンの利点:**
- メモリ効率化
- 比較演算の高速化
- 一貫性の保証

## 使用上の注意

### パフォーマンス考慮事項
- オブジェクトラッピングによるオーバーヘッド
- Noneのシングルトンパターンによる最適化
- 詳細は[パフォーマンスガイド](/docs/guide/performance_guide.md)を参照

### 型安全性
- PHPStan Level MAX対応
- ジェネリクス型アノテーション推奨
- 詳細は[型エラーのトラブルシューティング](/docs/guide/debugging_guide.md#型エラーのトラブルシューティング)を参照

### null安全性
- null値の代替として使用
- `unwrap()`系メソッドは慎重に使用
- 詳細は[ベストプラクティス](/docs/guide/best_practices.md)を参照

### 実用的な設計パターン
- Builder パターンでの optional fields
- Repository パターンでの検索結果
- Configuration パターンでの設定値