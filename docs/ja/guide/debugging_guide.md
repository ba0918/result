# デバッグ・トラブルシューティングガイド

このガイドでは、PHP Result/Option型ライブラリを使用する際の一般的な問題と解決方法について説明します。

## 目次

1. [よくある問題と解決方法](#よくある問題と解決方法)
2. [デバッグテクニック](#デバッグテクニック)
3. [inspectメソッドの活用](#inspectメソッドの活用)
4. [エラーメッセージの読み方](#エラーメッセージの読み方)
5. [IDE統合での支援機能](#ide統合での支援機能)
6. [パフォーマンスデバッグ](#パフォーマンスデバッグ)
7. [型エラーのトラブルシューティング](#型エラーのトラブルシューティング)

## よくある問題と解決方法

### 1. UnwrapException が発生する

#### 問題
```php
$option = None::instance();
$value = $option->unwrap(); // UnwrapException: Called unwrap() on a None value
```

#### 解決方法
```php
// ❌ 危険: unwrap()を直接使用
$value = $option->unwrap();

// ✅ 安全: 事前チェック
if ($option->isSome()) {
    $value = $option->unwrap();
}

// ✅ 安全: デフォルト値付き
$value = $option->unwrapOr('デフォルト値');

// ✅ 安全: 条件付きデフォルト
$value = $option->unwrapOrElse(fn() => $this->generateDefault());

// ✅ 安全: パターンマッチング
$result = $option->map(fn($x) => $x->process())->unwrapOr(null);
```

### 2. Err値でOk専用メソッドを呼び出す

#### 問題
```php
$result = Err::of('エラー');
$value = $result->unwrap(); // UnwrapException
```

#### 解決方法
```php
// ✅ 状態確認してから処理
if ($result->isOk()) {
    $value = $result->unwrap();
} else {
    $error = $result->unwrapErr();
    $this->handleError($error);
}

// ✅ 型安全なパターンマッチング
$finalValue = $result
    ->map(fn($value) => $value->process())
    ->mapErr(fn($error) => $this->logError($error))
    ->unwrapOr('デフォルト値');
```

### 3. 型推論の問題

#### 問題
```php
// PHPStanで型エラー
$option = Some::of(42);
$doubled = $option->map(fn($x) => $x * 2); // mixed型エラー
```

#### 解決方法
```php
// ✅ 型アノテーション追加
/** @var Option<int> $option */
$option = Some::of(42);
$doubled = $option->map(fn(int $x): int => $x * 2);

// ✅ factory methodでの型明示
/** @return Option<User> */
public function findUser(int $id): Option
{
    $user = $this->repository->find($id);
    return $user ? Some::of($user) : None::instance();
}
```

### 4. ネストした型の取り扱い

#### 問題
```php
// Option<Option<T>>の処理が複雑
$nested = Some::of(Some::of(42));
$value = $nested->unwrap()->unwrap(); // 危険
```

#### 解決方法
```php
// ✅ flatten()を使用
$flattened = $nested->flatten();
$value = $flattened->unwrapOr(0);

// ✅ andThen()でチェーン
$result = $nested->andThen(fn($inner) => $inner);

// ✅ 設計の見直し
// そもそもネストを避ける設計にする
public function findAndProcess(int $id): Option
{
    return $this->findUser($id)
        ->andThen(fn($user) => $this->processUser($user));
}
```

## デバッグテクニック

### 1. inspect()メソッドによるデバッグ

```php
// 処理の流れを追跡
$result = $this->getData()
    ->inspect(fn($data) => error_log("Data received: " . print_r($data, true)))
    ->map(fn($data) => $this->validate($data))
    ->inspect(fn($validated) => error_log("Validation result: " . var_export($validated, true)))
    ->andThen(fn($validated) => $this->process($validated))
    ->inspect(fn($processed) => error_log("Processing complete: " . json_encode($processed)));

// エラー時の詳細ログ
$result = $this->riskyOperation()
    ->inspectErr(fn($error) => error_log("Error occurred: " . $error))
    ->inspectErr(fn($error) => $this->notifyError($error));
```

### 2. カスタムデバッグヘルパー

```php
class ResultDebugger
{
    public static function trace(Result $result, string $label = ''): Result
    {
        $prefix = $label ? "[$label] " : '';
        
        if ($result->isOk()) {
            error_log($prefix . "OK: " . print_r($result->unwrap(), true));
        } else {
            error_log($prefix . "ERR: " . print_r($result->unwrapErr(), true));
        }
        
        return $result;
    }
    
    public static function dump(Option $option, string $label = ''): Option
    {
        $prefix = $label ? "[$label] " : '';
        
        if ($option->isSome()) {
            error_log($prefix . "Some: " . print_r($option->unwrap(), true));
        } else {
            error_log($prefix . "None");
        }
        
        return $option;
    }
}

// 使用例
$result = ResultDebugger::trace(
    $this->complexOperation(),
    'Complex Operation'
);
```

### 3. 段階的デバッグ

```php
public function debugComplexChain(array $input): Result
{
    // Step 1: 入力確認
    error_log("Input: " . json_encode($input));
    
    $step1 = Some::of($input)
        ->filter(fn($data) => !empty($data));
    
    if ($step1->isNone()) {
        error_log("Step 1 failed: empty input");
        return Err::of('Empty input');
    }
    
    // Step 2: バリデーション
    $step2 = $step1->andThen(fn($data) => $this->validate($data));
    
    if ($step2->isNone()) {
        error_log("Step 2 failed: validation error");
        return Err::of('Validation failed');
    }
    
    // Step 3: 処理
    return $step2
        ->map(fn($data) => $this->process($data))
        ->okOr('Processing failed');
}
```

## inspectメソッドの活用

### 1. 基本的な使用方法

```php
// Result型での値の追跡
$result = $this->processData($input)
    ->inspect(fn($data) => $this->logSuccess('Data processed', $data))
    ->inspectErr(fn($error) => $this->logError('Processing failed', $error));

// Option型での値の追跡
$option = $this->findUser($id)
    ->inspect(fn($user) => $this->logActivity('User found', $user->getId()));
```

### 2. 条件付きデバッグ

```php
class ConditionalDebugger
{
    public static function inspectIf(bool $condition): callable
    {
        return function($value) use ($condition) {
            if ($condition) {
                error_log("Debug: " . print_r($value, true));
            }
        };
    }
}

// 使用例
$debug = $_ENV['APP_DEBUG'] ?? false;

$result = $this->complexOperation()
    ->inspect(ConditionalDebugger::inspectIf($debug));
```

### 3. パフォーマンス監視

```php
class PerformanceInspector
{
    private static array $timers = [];
    
    public static function startTimer(string $name): callable
    {
        return function($value) use ($name) {
            self::$timers[$name] = microtime(true);
            return $value;
        };
    }
    
    public static function endTimer(string $name): callable
    {
        return function($value) use ($name) {
            if (isset(self::$timers[$name])) {
                $elapsed = microtime(true) - self::$timers[$name];
                error_log("Timer [$name]: " . round($elapsed * 1000, 2) . "ms");
                unset(self::$timers[$name]);
            }
            return $value;
        };
    }
}

// 使用例
$result = $this->heavyOperation()
    ->inspect(PerformanceInspector::startTimer('heavy_op'))
    ->map(fn($data) => $this->processHeavyData($data))
    ->inspect(PerformanceInspector::endTimer('heavy_op'));
```

## エラーメッセージの読み方

### 1. UnwrapException の詳細

```php
// Exception message: "Called unwrap() on an Err value: error_details"
try {
    $value = $result->unwrap();
} catch (UnwrapException $e) {
    // エラー内容はメッセージから抽出可能
    $message = $e->getMessage();
    
    if (str_contains($message, 'Called unwrap() on an Err value:')) {
        $errorValue = substr($message, strlen('Called unwrap() on an Err value: '));
        error_log("Error value was: " . $errorValue);
    }
}
```

### 2. 型エラーの解読

```php
// PHPStan error: "Parameter #1 $fn of method map() expects callable(T): U, callable(mixed): int given"

// 解決方法：型を明示
/** @var Option<string> $option */
$option = Some::of("hello");

$result = $option->map(fn(string $str): int => strlen($str));
```

### 3. カスタムエラー情報

```php
// より詳細なエラー情報を含む
public function processWithContext(array $data): Result
{
    try {
        $result = $this->validate($data);
        return Ok::of($result);
    } catch (ValidationException $e) {
        return Err::of([
            'type' => 'validation_error',
            'message' => $e->getMessage(),
            'field' => $e->getField(),
            'input' => $data,
            'timestamp' => time()
        ]);
    }
}
```

## IDE統合での支援機能

### 1. PhpStorm設定

```php
// .phpstorm.meta.php
<?php
namespace PHPSTORM_META {
    
    // Option型の型推論改善
    override(\ba0918\Result\Option::map(0), map([
        '' => '@',
    ]));
    
    // Result型の型推論改善
    override(\ba0918\Result\Result::map(0), map([
        '' => '@',
    ]));
    
    // unwrap()の戻り値型推論
    override(\ba0918\Result\Some::unwrap(), type(0));
    override(\ba0918\Result\Ok::unwrap(), type(0));
}
```

### 2. VS Code設定

```json
// settings.json
{
    "php.suggest.basic": false,
    "php.validate.enable": true,
    "phpstan.enabled": true,
    "phpstan.level": "max",
    "intelephense.completion.insertUseDeclaration": true,
    "intelephense.completion.fullyQualifyGlobalConstantsAndFunctions": true
}
```

### 3. カスタムライブテンプレート

```php
// PhpStorm Live Template: "optmap"
$SELECTION$->map(fn($VAR$) => $END$)

// PhpStorm Live Template: "reschain"
$SELECTION$
    ->map(fn($VAR$) => $END$)
    ->mapErr(fn($error) => $this->handleError($error))
    ->unwrapOr($DEFAULT$)
```

## パフォーマンスデバッグ

### 1. メモリ使用量の監視

```php
class MemoryProfiler
{
    public static function profile(callable $operation, string $label): mixed
    {
        $memoryBefore = memory_get_usage(true);
        $peakBefore = memory_get_peak_usage(true);
        
        $result = $operation();
        
        $memoryAfter = memory_get_usage(true);
        $peakAfter = memory_get_peak_usage(true);
        
        error_log(sprintf(
            "%s - Memory: %s bytes, Peak: %s bytes",
            $label,
            number_format($memoryAfter - $memoryBefore),
            number_format($peakAfter - $peakBefore)
        ));
        
        return $result;
    }
}

// 使用例
$result = MemoryProfiler::profile(
    fn() => $this->processLargeDataset($data),
    'Large Dataset Processing'
);
```

### 2. オブジェクト生成の追跡

```php
// デバッグ用のファクトリメソッド
class DebugOption
{
    private static int $instanceCount = 0;
    
    public static function of(mixed $value): Option
    {
        self::$instanceCount++;
        error_log("Option instance #" . self::$instanceCount . " created");
        
        return $value === null ? None::instance() : Some::of($value);
    }
    
    public static function getInstanceCount(): int
    {
        return self::$instanceCount;
    }
}
```

## 型エラーのトラブルシューティング

### 1. PHPStan Level MAX対応

```php
// 問題：mixed型警告
public function processData(mixed $data): Result
{
    return Ok::of($data)
        ->map(fn($x) => $x->process()); // PHPStan error: mixed型
}

// 解決方法1：型アサーション
public function processData(mixed $data): Result
{
    assert($data instanceof ProcessableData);
    
    return Ok::of($data)
        ->map(fn(ProcessableData $x) => $x->process());
}

// 解決方法2：実行時型チェック
public function processData(mixed $data): Result
{
    if (!$data instanceof ProcessableData) {
        return Err::of('Invalid data type');
    }
    
    return Ok::of($data)
        ->map(fn(ProcessableData $x) => $x->process());
}
```

### 2. ジェネリクス型の問題

```php
// 問題：ジェネリクス型が推論されない
class Repository
{
    /**
     * @template T
     * @param class-string<T> $class
     * @return Option<T>
     */
    public function find(string $class, int $id): Option
    {
        $entity = $this->database->find($class, $id);
        return $entity ? Some::of($entity) : None::instance();
    }
}

// 使用時に型を明示
/** @var Option<User> $user */
$user = $repository->find(User::class, 123);
```

### 3. 実践的なエラー処理

```php
class RobustProcessor
{
    public function process(mixed $input): Result
    {
        try {
            // 型安全な処理チェーン
            return $this->validateInput($input)
                ->andThen(fn($data) => $this->transformData($data))
                ->andThen(fn($transformed) => $this->saveData($transformed));
                
        } catch (Throwable $e) {
            return Err::of([
                'error' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    private function validateInput(mixed $input): Result
    {
        if (!is_array($input)) {
            return Err::of('Input must be array');
        }
        
        if (empty($input)) {
            return Err::of('Input cannot be empty');
        }
        
        return Ok::of($input);
    }
}
```

## まとめ

効果的なデバッグのための原則：

1. **予防的デバッグ**: unwrap()の代わりにunwrapOr()を使用
2. **段階的追跡**: inspect()メソッドで処理フローを可視化
3. **型安全性**: PHPStanとIDEを活用した型チェック
4. **エラー文脈**: 十分な情報を含むエラーメッセージ
5. **パフォーマンス監視**: 実行時間とメモリ使用量の追跡

これらのテクニックを活用することで、Result/Option型を使用したコードの品質と保守性を大幅に向上させることができます。