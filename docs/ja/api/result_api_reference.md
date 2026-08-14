# Result型 API リファレンス

Result型は成功/失敗を表現する汎用的な型で、エラーハンドリングを型安全に行うためのインターフェースです。

## 目次

1. [型定義](#型定義)
2. [状態確認メソッド](#状態確認メソッド)
3. [変換メソッド](#変換メソッド)
4. [値取得メソッド](#値取得メソッド)
5. [検査メソッド](#検査メソッド)
6. [結合メソッド](#結合メソッド)
7. [ユーティリティメソッド](#ユーティリティメソッド)
8. [型間変換メソッド](#型間変換メソッド)
9. [実装クラス](#実装クラス)

## 型定義

```php
/**
 * 成功/失敗を表現する為の型
 *
 * @template T 成功時の値の型
 * @template E 失敗時のエラーの型
 */
interface Result
```

## 状態確認メソッド

### isOk(): bool

成功状態かどうかを確認します。

**シグネチャ:**
```php
public function isOk(): bool
```

**戻り値:**
- `true`: Ok状態の場合
- `false`: Err状態の場合

**使用例:**
```php
$result = Ok::of(42);
if ($result->isOk()) {
    echo "成功: " . $result->unwrap();
}

$errorResult = Err::of("エラー");
var_dump($errorResult->isOk()); // false
```

**関連メソッド:** [isErr()](#iserr-bool), [isOkAnd()](#isokandcallable-predicate-bool)

---

### isErr(): bool

失敗状態かどうかを確認します。

**シグネチャ:**
```php
public function isErr(): bool
```

**戻り値:**
- `true`: Err状態の場合
- `false`: Ok状態の場合

**使用例:**
```php
$result = Err::of("何かエラー");
if ($result->isErr()) {
    echo "エラー: " . $result->unwrapErr();
}
```

**関連メソッド:** [isOk()](#isok-bool), [isErrAnd()](#iserrandcallable-predicate-bool)

---

### isOkAnd(callable $predicate): bool

成功状態で、かつ述語関数を満たすかを確認します。

**シグネチャ:**
```php
/**
 * @param callable(T): bool $predicate
 * @return bool
 */
public function isOkAnd(callable $predicate): bool
```

**パラメータ:**
- `$predicate`: 成功値に対する検証関数

**戻り値:**
- Ok状態で述語を満たす場合: `true`
- Err状態または述語を満たさない場合: `false`

**使用例:**
```php
$result = Ok::of(10);
$isPositive = $result->isOkAnd(fn($x) => $x > 0); // true
$isNegative = $result->isOkAnd(fn($x) => $x < 0); // false

$errorResult = Err::of("エラー");
$anyCheck = $errorResult->isOkAnd(fn($x) => true); // false (Errなので)
```

**ショートハンドメソッド:** より簡潔な条件確認が可能

**関連メソッド:** [isOk()](#isok-bool), [contains()](#containsmixed-value-bool)

---

### isErrAnd(callable $predicate): bool

失敗状態で、かつ述語関数を満たすかを確認します。

**シグネチャ:**
```php
/**
 * @param callable(E): bool $predicate
 * @return bool
 */
public function isErrAnd(callable $predicate): bool
```

**パラメータ:**
- `$predicate`: エラー値に対する検証関数

**戻り値:**
- Err状態で述語を満たす場合: `true`
- Ok状態または述語を満たさない場合: `false`

**使用例:**
```php
$result = Err::of("not found");
$isNotFoundError = $result->isErrAnd(fn($err) => str_contains($err, "not found")); // true

$successResult = Ok::of(42);
$anyErrorCheck = $successResult->isErrAnd(fn($err) => true); // false (Okなので)
```

**関連メソッド:** [isErr()](#iserr-bool), [containsErr()](#containserrmixed-error-bool)

## 変換メソッド

### map(callable $fn): Result

成功値に関数を適用して新しいResultを作成します。

**シグネチャ:**
```php
/**
 * @template U
 * @param callable(T): U $fn
 * @return Result<U, E>
 */
public function map(callable $fn): Result
```

**パラメータ:**
- `$fn`: 成功値に適用する変換関数

**戻り値:**
- Ok状態: 変換後の値を持つOk
- Err状態: 元のErr（変換は実行されない）

**使用例:**
```php
$result = Ok::of(5);
$doubled = $result->map(fn($x) => $x * 2); // Ok(10)

$errorResult = Err::of("エラー");
$notExecuted = $errorResult->map(fn($x) => $x * 2); // Err("エラー") - 関数は実行されない
```

**エラーハンドリング:**
```php
// 変換関数内で例外が発生した場合、そのまま伝播します
$result = Ok::of("invalid");
try {
    $converted = $result->map(fn($x) => intval($x) / 0); // ZeroDivisionError
} catch (DivisionByZeroError $e) {
    // 例外処理
}
```

**関連メソッド:** [mapErr()](#maperrcallable-fn-result), [mapOr()](#maporcallable-fn-mixed-default-mixed), [mapOrElse()](#maporelsecallable-fn-callable-defaultfn-mixed), [andThen()](#andthencallable-fn-result)

---

### mapErr(callable $fn): Result

エラー値に関数を適用して新しいResultを作成します。

**シグネチャ:**
```php
/**
 * @template F
 * @param callable(E): F $fn
 * @return Result<T, F>
 */
public function mapErr(callable $fn): Result
```

**パラメータ:**
- `$fn`: エラー値に適用する変換関数

**戻り値:**
- Err状態: 変換後のエラー値を持つErr
- Ok状態: 元のOk（変換は実行されない）

**使用例:**
```php
$result = Err::of("user not found");
$localized = $result->mapErr(fn($err) => "ユーザーが見つかりません"); // Err("ユーザーが見つかりません")

$successResult = Ok::of(42);
$unchanged = $successResult->mapErr(fn($err) => "変換されない"); // Ok(42)
```

**実践例:**
```php
// エラーログの構造化
$result = $this->databaseOperation()
    ->mapErr(fn($err) => [
        'type' => 'database_error',
        'message' => $err,
        'timestamp' => time()
    ]);
```

**関連メソッド:** [map()](#mapcallable-fn-result), [inspectErr()](#inspecterrcallable-fn-result)

---

### mapOr(callable $fn, mixed $default): mixed

成功値に関数を適用するか、失敗時はデフォルト値を返します。

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
- `$fn`: 成功値に適用する変換関数
- `$default`: 失敗時のデフォルト値

**戻り値:**
- Ok状態: `$fn`を適用した結果
- Err状態: `$default`

**使用例:**
```php
$result = Ok::of(5);
$doubled = $result->mapOr(fn($x) => $x * 2, 0); // 10

$errorResult = Err::of("エラー");
$defaultUsed = $errorResult->mapOr(fn($x) => $x * 2, 0); // 0
```

**ショートハンドの効果:**
```php
// 従来の書き方
$value = $result->map(fn($x) => $x * 2)->unwrapOr(0);

// mapOr()を使用
$value = $result->mapOr(fn($x) => $x * 2, 0); // より効率的
```

**関連メソッド:** [map()](#mapcallable-fn-result), [mapOrElse()](#maporelsecallable-fn-callable-defaultfn-mixed), [unwrapOr()](#unwrapormixed-default-mixed)

---

### mapOrElse(callable $fn, callable $defaultFn): mixed

成功値に関数を適用するか、失敗時はクロージャを実行します。

**シグネチャ:**
```php
/**
 * @template U
 * @param callable(T): U $fn
 * @param callable(E): U $defaultFn
 * @return U
 */
public function mapOrElse(callable $fn, callable $defaultFn): mixed
```

**パラメータ:**
- `$fn`: 成功値に適用する変換関数
- `$defaultFn`: 失敗時に実行するクロージャ（エラー値を受け取る）

**戻り値:**
- Ok状態: `$fn`を適用した結果
- Err状態: `$defaultFn`を実行した結果

**使用例:**
```php
$result = Ok::of(5);
$value = $result->mapOrElse(
    fn($x) => $x * 2,
    fn($err) => strlen($err)
); // 10

$errorResult = Err::of("error message");
$errorLength = $errorResult->mapOrElse(
    fn($x) => $x * 2,
    fn($err) => strlen($err)
); // 13
```

**遅延評価の利点:**
```php
// 重い処理は失敗時のみ実行される
$result = $someOperation->mapOrElse(
    fn($data) => $data->process(),
    fn($err) => $this->generateExpensiveDefault($err) // Ok時は実行されない
);
```

**関連メソッド:** [mapOr()](#maporcallable-fn-mixed-default-mixed), [unwrapOrElse()](#unwraporelsecallable-fn-mixed)

---

### andThen(callable $fn): Result

成功値に対してResultを返す関数を適用します（モナド的チェーン）。

**シグネチャ:**
```php
/**
 * @template U
 * @template F
 * @param callable(T): Result<U, F> $fn
 * @return Result<U, E|F>
 */
public function andThen(callable $fn): Result
```

**パラメータ:**
- `$fn`: 成功値を受け取ってResultを返す関数

**戻り値:**
- Ok状態: `$fn`が返すResult
- Err状態: 元のErr

**使用例:**
```php
function divide(int $a, int $b): Result {
    return $b === 0 ? Err::of("division by zero") : Ok::of($a / $b);
}

$result = Ok::of(10)
    ->andThen(fn($x) => divide($x, 2))  // Ok(5)
    ->andThen(fn($x) => divide($x, 0)); // Err("division by zero")
```

**複雑なチェーン:**
```php
$result = $this->getUser($id)
    ->andThen(fn($user) => $this->validateUser($user))
    ->andThen(fn($user) => $this->authorizeUser($user))
    ->andThen(fn($user) => $this->processUser($user));
```

**flatMapとの関係:** `andThen`は他の言語の`flatMap`に相当

**関連メソッド:** [map()](#mapcallable-fn-result), [flatten()](#flatten-result)

## 値取得メソッド

### unwrap(): mixed

成功値を取得します。失敗時は例外をスローします。

**シグネチャ:**
```php
/**
 * @return T
 * @throws UnwrapException
 */
public function unwrap(): mixed
```

**戻り値:**
- Ok状態: 成功値
- Err状態: UnwrapExceptionをスロー

**使用例:**
```php
$result = Ok::of(42);
$value = $result->unwrap(); // 42

$errorResult = Err::of("エラー");
try {
    $value = $errorResult->unwrap(); // UnwrapException
} catch (UnwrapException $e) {
    echo $e->getMessage(); // "Called unwrap() on an Err value: エラー"
}
```

**⚠️ 注意事項:**
- 予期しない例外を避けるため、事前に`isOk()`でチェックするか、`unwrapOr()`を使用することを推奨
- プロダクションコードでは慎重に使用

**関連メソッド:** [unwrapOr()](#unwrapormixed-default-mixed), [unwrapOrElse()](#unwraporelsecallable-fn-mixed), [expect()](#expectstring-message-mixed)

---

### unwrapErr(): mixed

エラー値を取得します。成功時は例外をスローします。

**シグネチャ:**
```php
/**
 * @return E
 * @throws UnwrapException
 */
public function unwrapErr(): mixed
```

**戻り値:**
- Err状態: エラー値
- Ok状態: UnwrapExceptionをスロー

**使用例:**
```php
$result = Err::of("ファイルが見つかりません");
$error = $result->unwrapErr(); // "ファイルが見つかりません"

$successResult = Ok::of(42);
try {
    $error = $successResult->unwrapErr(); // UnwrapException
} catch (UnwrapException $e) {
    echo $e->getMessage(); // "Called unwrapErr() on an Ok value: 42"
}
```

**関連メソッド:** [unwrap()](#unwrap-mixed), [expectErr()](#expecterrstring-message-mixed)

---

### unwrapOr(mixed $default): mixed

成功値を取得するか、失敗時はデフォルト値を返します。

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
- `$default`: 失敗時のデフォルト値

**戻り値:**
- Ok状態: 成功値
- Err状態: `$default`

**使用例:**
```php
$result = Ok::of(42);
$value = $result->unwrapOr(0); // 42

$errorResult = Err::of("エラー");
$value = $errorResult->unwrapOr(0); // 0
```

**実践例:**
```php
// 設定値の取得
$port = $config->getPort()->unwrapOr(8080);

// ユーザー名の取得
$username = $session->getUser()
    ->map(fn($user) => $user->getName())
    ->unwrapOr('Guest');
```

**関連メソッド:** [unwrap()](#unwrap-mixed), [unwrapOrElse()](#unwraporelsecallable-fn-mixed), [mapOr()](#maporcallable-fn-mixed-default-mixed)

---

### unwrapOrElse(callable $fn): mixed

成功値を取得するか、失敗時はクロージャの結果を返します。

**シグネチャ:**
```php
/**
 * @template U
 * @param callable(E): U $fn
 * @return T|U
 */
public function unwrapOrElse(callable $fn): mixed
```

**パラメータ:**
- `$fn`: エラー値を受け取って代替値を生成する関数

**戻り値:**
- Ok状態: 成功値
- Err状態: `$fn`の実行結果

**使用例:**
```php
$result = Ok::of(42);
$value = $result->unwrapOrElse(fn($err) => strlen($err)); // 42

$errorResult = Err::of("error");
$value = $errorResult->unwrapOrElse(fn($err) => strlen($err)); // 5
```

**遅延評価の利点:**
```php
// 重い処理は失敗時のみ実行
$value = $result->unwrapOrElse(fn($err) => $this->generateExpensiveDefault($err));
```

**関連メソッド:** [unwrapOr()](#unwrapormixed-default-mixed), [mapOrElse()](#maporelsecallable-fn-callable-defaultfn-mixed)

---

### expect(string $message): mixed

成功値を取得します。失敗時はカスタムメッセージで例外をスローします。

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
- `$message`: 失敗時の例外メッセージ

**戻り値:**
- Ok状態: 成功値
- Err状態: カスタムメッセージでUnwrapExceptionをスロー

**使用例:**
```php
$result = Ok::of(42);
$value = $result->expect("値が必要です"); // 42

$errorResult = Err::of("ファイルエラー");
try {
    $value = $errorResult->expect("設定ファイルの読み込みに失敗");
} catch (UnwrapException $e) {
    echo $e->getMessage(); // "設定ファイルの読み込みに失敗: ファイルエラー"
}
```

**デバッグでの活用:**
```php
// 期待される状況を明確にする
$user = $session->getUser()
    ->expect("認証済みユーザーが必要");
```

**関連メソッド:** [unwrap()](#unwrap-mixed), [expectErr()](#expecterrstring-message-mixed)

---

### expectErr(string $message): mixed

エラー値を取得します。成功時はカスタムメッセージで例外をスローします。

**シグネチャ:**
```php
/**
 * @param string $message
 * @return E
 * @throws UnwrapException
 */
public function expectErr(string $message): mixed
```

**パラメータ:**
- `$message`: 成功時の例外メッセージ

**戻り値:**
- Err状態: エラー値
- Ok状態: カスタムメッセージでUnwrapExceptionをスロー

**使用例:**
```php
$result = Err::of("認証エラー");
$error = $result->expectErr("エラーが発生しているはず"); // "認証エラー"

$successResult = Ok::of(42);
try {
    $error = $successResult->expectErr("エラーケースのテスト中");
} catch (UnwrapException $e) {
    echo $e->getMessage(); // "エラーケースのテスト中: 42"
}
```

**テストでの活用:**
```php
// エラーケースのテスト
$result = $service->invalidOperation();
$error = $result->expectErr("無効な操作はエラーになるべき");
$this->assertEquals("invalid operation", $error);
```

**関連メソッド:** [expect()](#expectstring-message-mixed), [unwrapErr()](#unwraperr-mixed)

## 検査メソッド

### inspect(callable $fn): Result

成功値を検査し、副作用を実行します（値は変更されません）。

**シグネチャ:**
```php
/**
 * @param callable(T): void $fn
 * @return Result<T, E>
 */
public function inspect(callable $fn): Result
```

**パラメータ:**
- `$fn`: 成功値を受け取る検査関数（副作用のみ）

**戻り値:** 元のResult（変更されない）

**使用例:**
```php
$result = Ok::of(42)
    ->inspect(fn($x) => error_log("Success value: $x"))
    ->map(fn($x) => $x * 2);
// ログ出力: "Success value: 42"
// 結果: Ok(84)

$errorResult = Err::of("エラー")
    ->inspect(fn($x) => error_log("Success value: $x")); // 実行されない
```

**デバッグでの活用:**
```php
$result = $this->complexOperation()
    ->inspect(fn($data) => $this->logProcessingStep('step1', $data))
    ->map(fn($data) => $this->transformData($data))
    ->inspect(fn($data) => $this->logProcessingStep('step2', $data));
```

**関連メソッド:** [inspectErr()](#inspecterrcallable-fn-result), [map()](#mapcallable-fn-result)

---

### inspectErr(callable $fn): Result

エラー値を検査し、副作用を実行します（エラーは変更されません）。

**シグネチャ:**
```php
/**
 * @param callable(E): void $fn
 * @return Result<T, E>
 */
public function inspectErr(callable $fn): Result
```

**パラメータ:**
- `$fn`: エラー値を受け取る検査関数（副作用のみ）

**戻り値:** 元のResult（変更されない）

**使用例:**
```php
$result = Err::of("データベースエラー")
    ->inspectErr(fn($err) => error_log("Error occurred: $err"))
    ->mapErr(fn($err) => "内部エラー");
// ログ出力: "Error occurred: データベースエラー"
// 結果: Err("内部エラー")

$successResult = Ok::of(42)
    ->inspectErr(fn($err) => error_log("Error: $err")); // 実行されない
```

**エラー監視での活用:**
```php
$result = $this->criticalOperation()
    ->inspectErr(fn($err) => $this->alertSystem->notify($err))
    ->inspectErr(fn($err) => $this->logger->error('Critical failure', ['error' => $err]));
```

**関連メソッド:** [inspect()](#inspectcallable-fn-result), [mapErr()](#maperrcallable-fn-result)

## 結合メソッド

### or(Result $res): Result

Errの場合に代替のResultを返します（即座評価）。

**シグネチャ:**
```php
/**
 * @template U
 * @template F
 * @param Result<U, F> $res
 * @return Result<T|U, F>
 */
public function or(Result $res): Result
```

**パラメータ:**
- `$res`: 代替のResult

**戻り値:**
- Ok状態: 元のOk
- Err状態: `$res`

**使用例:**
```php
$primary = Ok::of(42);
$fallback = Ok::of(100);
$result = $primary->or($fallback); // Ok(42)

$primaryFailed = Err::of("primary error");
$fallbackSuccess = Ok::of(100);
$result = $primaryFailed->or($fallbackSuccess); // Ok(100)
```

**複数のフォールバック:**
```php
$result = $this->primarySource()
    ->or($this->secondarySource())
    ->or($this->tertiarySource())
    ->or(Ok::of($this->defaultValue()));
```

**関連メソッド:** [orElse()](#orelsecallable-fn-result), [and()](#andresult-res-result)

---

### orElse(callable $fn): Result

Errの場合に代替のResultを返します（遅延評価）。

**シグネチャ:**
```php
/**
 * @template U
 * @template F
 * @param callable(E): Result<U, F> $fn
 * @return Result<T|U, F>
 */
public function orElse(callable $fn): Result
```

**パラメータ:**
- `$fn`: エラー値を受け取ってResultを返す関数

**戻り値:**
- Ok状態: 元のOk
- Err状態: `$fn`の実行結果

**使用例:**
```php
$result = Err::of("not found")
    ->orElse(fn($err) => str_contains($err, "not found") 
        ? Ok::of("デフォルト値") 
        : Err::of("予期しないエラー"));
// Ok("デフォルト値")
```

**エラー種別による分岐:**
```php
$result = $this->fetchData()
    ->orElse(fn($err) => match($err['type']) {
        'timeout' => $this->fetchFromCache(),
        'network' => $this->fetchFromBackup(),
        default => Err::of('fatal error')
    });
```

**関連メソッド:** [or()](#orresult-res-result), [unwrapOrElse()](#unwraporelsecallable-fn-mixed)

---

### and(Result $res): Result

Okの場合に別のResultを返し、Errの場合は自身を返します（即座評価）。

**シグネチャ:**
```php
/**
 * @template U
 * @template F
 * @param Result<U, F> $res
 * @return Result<U, E|F>
 */
public function and(Result $res): Result
```

**パラメータ:**
- `$res`: 連続して評価するResult

**戻り値:**
- Ok状態: `$res`
- Err状態: 元のErr

**使用例:**
```php
$step1 = Ok::of("step1完了");
$step2 = Ok::of("step2完了");
$result = $step1->and($step2); // Ok("step2完了")

$step1Failed = Err::of("step1エラー");
$step2 = Ok::of("step2完了");
$result = $step1Failed->and($step2); // Err("step1エラー")
```

**連続処理:**
```php
$result = $this->validateInput($data)
    ->and($this->checkPermissions($user))
    ->and($this->processRequest($data));
```

**andThenとの違い:** `and`は値を使わない継続、`andThen`は値を使った変換

**関連メソッド:** [andThen()](#andthencallable-fn-result), [or()](#orresult-res-result)

## ユーティリティメソッド

### contains(mixed $value): bool

Ok値が指定された値を含むかどうかを確認します。

**シグネチャ:**
```php
/**
 * @param mixed $value 確認したい値
 * @return bool Ok値が指定値と厳密に等価な場合true、それ以外はfalse
 */
public function contains(mixed $value): bool
```

**パラメータ:**
- `$value`: 確認したい値

**戻り値:**
- Ok状態で値が一致: `true`
- Ok状態で値が不一致またはErr状態: `false`

**使用例:**
```php
$result = Ok::of(42);
var_dump($result->contains(42));   // true
var_dump($result->contains("42")); // false (厳密比較)
var_dump($result->contains(100));  // false

$errorResult = Err::of("エラー");
var_dump($errorResult->contains(42)); // false (Errなので)
```

**厳密比較の重要性:**
```php
$result = Ok::of([1, 2, 3]);
var_dump($result->contains([1, 2, 3])); // true
var_dump($result->contains([1, 2]));    // false

$objectResult = Ok::of(new stdClass());
var_dump($objectResult->contains(new stdClass())); // false (異なるインスタンス)
```

**PHP独自機能:** Rustの標準Result型にはない、PHP向けの便利メソッド

**関連メソッド:** [containsErr()](#containserrmixed-error-bool), [isOkAnd()](#isokandcallable-predicate-bool)

---

### containsErr(mixed $error): bool

Err値が指定されたエラーを含むかどうかを確認します。

**シグネチャ:**
```php
/**
 * @param mixed $error 確認したいエラー値
 * @return bool Err値が指定エラーと厳密に等価な場合true、それ以外はfalse
 */
public function containsErr(mixed $error): bool
```

**パラメータ:**
- `$error`: 確認したいエラー値

**戻り値:**
- Err状態で値が一致: `true`
- Err状態で値が不一致またはOk状態: `false`

**使用例:**
```php
$result = Err::of("not found");
var_dump($result->containsErr("not found")); // true
var_dump($result->containsErr("timeout"));   // false

$successResult = Ok::of(42);
var_dump($successResult->containsErr("not found")); // false (Okなので)
```

**エラータイプの確認:**
```php
$result = Err::of(['type' => 'validation', 'field' => 'email']);
var_dump($result->containsErr(['type' => 'validation', 'field' => 'email'])); // true
```

**関連メソッド:** [contains()](#containsmixed-value-bool), [isErrAnd()](#iserrandcallable-predicate-bool)

---

### flatten(): Result

ネストしたResultを一段階平坦化します。

**シグネチャ:**
```php
/**
 * @return Result<T, E>
 */
public function flatten(): Result
```

**戻り値:**
- Ok(Result): 内側のResult
- Ok(非Result): 元のOk
- Err: 元のErr

**使用例:**
```php
// ネストしたResult
$nested = Ok::of(Ok::of(42));
$flattened = $nested->flatten(); // Ok(42)

// 非Result値
$notNested = Ok::of(42);
$unchanged = $notNested->flatten(); // Ok(42)

// エラーケース
$error = Err::of("エラー");
$stillError = $error->flatten(); // Err("エラー")
```

**複雑なネスト:**
```php
// Ok(Ok(Err("inner error")))
$deepNested = Ok::of(Ok::of(Err::of("inner error")));
$oneLevel = $deepNested->flatten(); // Ok(Err("inner error"))
$fullyFlat = $oneLevel->flatten();  // Err("inner error")
```

**実行時型チェック:** instanceofによる実行時判定が必要

**関連メソッド:** [andThen()](#andthencallable-fn-result), [transpose()](#transpose-option)

## 型間変換メソッド

### transpose(): Option

Result<Option<T>, E> → Option<Result<T, E>> への変換を行います。

**シグネチャ:**
```php
/**
 * @return Option<mixed>
 */
public function transpose(): Option
```

**変換ルール:**
- `Ok(Some(value))` → `Some(Ok(value))`
- `Ok(None)` → `None`
- `Err(error)` → `Some(Err(error))`
- `Ok(非Option)` → `Some(Ok(value))`

**使用例:**
```php
// Ok(Some(value)) → Some(Ok(value))
$result = Ok::of(Some::of(42));
$transposed = $result->transpose(); // Some(Ok(42))

// Ok(None) → None
$result = Ok::of(None::instance());
$transposed = $result->transpose(); // None

// Err(error) → Some(Err(error))
$result = Err::of("エラー");
$transposed = $result->transpose(); // Some(Err("エラー"))
```

**実践的な使用例:**
```php
// データベースクエリの結果処理
function findUser(int $id): Result {
    $userData = $this->database->find($id); // null | array
    return Ok::of($userData === null ? None::instance() : Some::of($userData));
}

$users = [1, 2, 3];
$userResults = array_map(fn($id) => $this->findUser($id), $users);
$transposed = array_map(fn($result) => $result->transpose(), $userResults);
// [Some(Ok(user1)), None, Some(Ok(user3))]
```

**Rust互換性:** Rustの標準ライブラリと同じ変換ルール

**関連メソッド:** [ok()](#ok-option), [err()](#err-option), [Option::transpose()](option_api_reference.md#transpose-result)

---

### ok(): Option

成功値をOptionとして取得します。

**シグネチャ:**
```php
/**
 * @return Option<T>
 */
public function ok(): Option
```

**戻り値:**
- Ok状態: `Some(value)`
- Err状態: `None`

**使用例:**
```php
$result = Ok::of(42);
$option = $result->ok(); // Some(42)

$errorResult = Err::of("エラー");
$option = $errorResult->ok(); // None
```

**実践例:**
```php
// Resultのリストから成功値のみを抽出
$results = [$ok1, $err1, $ok2, $err2];
$values = array_filter(
    array_map(fn($r) => $r->ok(), $results),
    fn($opt) => $opt->isSome()
);
```

**関連メソッド:** [err()](#err-option), [transpose()](#transpose-option)

---

### err(): Option

エラー値をOptionとして取得します。

**シグネチャ:**
```php
/**
 * @return Option<E>
 */
public function err(): Option
```

**戻り値:**
- Err状態: `Some(error)`
- Ok状態: `None`

**使用例:**
```php
$result = Err::of("ファイルエラー");
$errorOption = $result->err(); // Some("ファイルエラー")

$successResult = Ok::of(42);
$errorOption = $successResult->err(); // None
```

**エラー分析での活用:**
```php
// Resultのリストからエラーのみを収集
$results = [$ok1, $err1, $ok2, $err2];
$errors = array_filter(
    array_map(fn($r) => $r->err(), $results),
    fn($opt) => $opt->isSome()
);
```

**関連メソッド:** [ok()](#ok-option), [unwrapErr()](#unwraperr-mixed)

## 実装クラス

### Ok<T>

成功を表すクラス。

```php
final class Ok implements Result
{
    public function __construct(private readonly mixed $value) {}
    public static function of(mixed $value): self {}
}
```

**使用例:**
```php
$success = Ok::of(42);
$success = Ok::of("成功メッセージ");
$success = Ok::of(['data' => 'value']);
```

### Err<E>

失敗を表すクラス。

```php
final class Err implements Result
{
    public function __construct(private readonly mixed $error) {}
    public static function of(mixed $error): self {}
}
```

**使用例:**
```php
$error = Err::of("エラーメッセージ");
$error = Err::of(['type' => 'validation', 'message' => 'Invalid input']);
$error = Err::of(new Exception("例外オブジェクト"));
```

## 使用上の注意

### パフォーマンス考慮事項
- オブジェクトラッピングによるオーバーヘッド
- 高頻度処理での使用は要検討
- 詳細は[パフォーマンスガイド](../guide/performance_guide.md)を参照

### 型安全性
- PHPStan Level MAX対応
- ジェネリクス型アノテーション推奨
- 詳細は[型エラーのトラブルシューティング](../guide/debugging_guide.md#型エラーのトラブルシューティング)を参照

### エラーハンドリング
- `unwrap()`系メソッドは慎重に使用
- `unwrapOr()`系メソッドを推奨
- 詳細は[ベストプラクティス](../guide/best_practices.md)を参照