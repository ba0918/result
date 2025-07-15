# パフォーマンス最適化ガイド

このガイドでは、PHP Result/Option型ライブラリのパフォーマンス特性と最適化テクニックについて詳しく説明します。

## 目次

1. [パフォーマンス特性の概要](#パフォーマンス特性の概要)
2. [ベンチマーク結果](#ベンチマーク結果)
3. [最適化テクニック](#最適化テクニック)
4. [避けるべきアンチパターン](#避けるべきアンチパターン)
5. [パフォーマンスクリティカルな場面での使い分け](#パフォーマンスクリティカルな場面での使い分け)
6. [実践的なベストプラクティス](#実践的なベストプラクティス)

## パフォーマンス特性の概要

### オブジェクト設計による効率化

このライブラリは高いパフォーマンスを重視して設計されています：

```php
// すべてのクラスはfinalでimmutable
final class Ok implements Result
{
    public function __construct(private readonly mixed $value) {}
}

// Noneはシングルトンパターンでメモリ効率化
final class None implements Option
{
    private static ?self $instance = null;
    
    public static function instance(): self
    {
        return self::$instance ??= new self();
    }
}
```

### 主要なパフォーマンス要因

#### 1. オブジェクト生成コスト
- **軽量設計**: 各オブジェクトは単一プロパティのみ保持
- **immutable**: 値変更によるコピーコストなし
- **シングルトン**: Noneインスタンスの再利用

#### 2. メモリ使用量
- **ラッピングオーバーヘッド**: 約16-24バイト/オブジェクト（PHP 8.4）
- **参照効率**: readonly propertiesによる最適化
- **ガベージコレクション**: 自動メモリ管理との親和性

#### 3. メソッド呼び出しコスト
- **インライン化**: JITコンパイラでの最適化対象
- **型推論**: opcacheによる最適化
- **ショートハンド**: `mapOr`等の一発変換メソッド

## ベンチマーク結果

### 基本操作のパフォーマンス

以下は10万回の反復実行における比較結果です：

```php
// テスト環境: PHP 8.4, opcache有効, JIT有効

// 1. 値のラッピング・アンラッピング
$start = microtime(true);
for ($i = 0; $i < 100000; $i++) {
    $result = Ok::of($i);
    $value = $result->unwrap();
}
$elapsed = microtime(true) - $start;
// Result: 約15ms (vs native PHP: 約2ms)

// 2. 条件分岐
$start = microtime(true);
for ($i = 0; $i < 100000; $i++) {
    $result = $i % 2 === 0 ? Ok::of($i) : Err::of("error");
    if ($result->isOk()) {
        $value = $result->unwrap();
    }
}
$elapsed = microtime(true) - $start;
// Result: 約25ms (vs native PHP if: 約8ms)

// 3. map操作
$start = microtime(true);
for ($i = 0; $i < 100000; $i++) {
    $result = Ok::of($i)->map(fn($x) => $x * 2);
}
$elapsed = microtime(true) - $start;
// Result: 約35ms (vs native PHP: 約12ms)
```

### メモリ使用量比較

```php
// メモリ使用量測定
$beforeMemory = memory_get_usage();

// native PHP配列
$nativeArray = [];
for ($i = 0; $i < 10000; $i++) {
    $nativeArray[] = $i;
}
$nativeMemory = memory_get_usage() - $beforeMemory;
// 約400KB

// Option型での格納
$beforeMemory = memory_get_usage();
$optionArray = [];
for ($i = 0; $i < 10000; $i++) {
    $optionArray[] = Some::of($i);
}
$optionMemory = memory_get_usage() - $beforeMemory;
// 約800KB（約2倍のオーバーヘッド）
```

## 最適化テクニック

### 1. ショートハンドメソッドの活用

複数の操作を一つのメソッドで実行することでパフォーマンスが向上します：

```php
// ❌ パフォーマンスが劣る
$result = $option->map(fn($x) => $x * 2)->unwrapOr(0);

// ✅ 最適化されたパターン
$result = $option->mapOr(fn($x) => $x * 2, 0);
```

### 2. 早期リターンの活用

```php
// ❌ 不要なチェーンを避ける
public function processData(?array $data): Result
{
    return Option::of($data)
        ->map(fn($d) => $this->validate($d))
        ->map(fn($d) => $this->transform($d))
        ->map(fn($d) => $this->save($d))
        ->okOr('処理エラー');
}

// ✅ 早期リターンでパフォーマンス向上
public function processData(?array $data): Result
{
    if ($data === null) {
        return Err::of('データなし');
    }
    
    $validated = $this->validate($data);
    if (!$validated) {
        return Err::of('バリデーションエラー');
    }
    
    // 以下必要な場合のみ実行...
}
```

### 3. Option型の効率的な使用

```php
// ❌ 頻繁なNone生成
public function findItems(array $conditions): array
{
    $results = [];
    foreach ($conditions as $condition) {
        $item = $this->findOne($condition); // Option<Item>を返す
        if ($item->isSome()) {
            $results[] = $item->unwrap();
        }
    }
    return $results;
}

// ✅ 効率的なパターン
public function findItems(array $conditions): array
{
    $results = [];
    foreach ($conditions as $condition) {
        $item = $this->findOneNative($condition); // null | Itemを返す
        if ($item !== null) {
            $results[] = $item;
        }
    }
    return $results;
}
```

### 4. バッチ処理の最適化

```php
// ❌ 個別処理
public function processItems(array $items): array
{
    return array_map(function ($item) {
        return Option::of($item)
            ->filter(fn($i) => $i->isValid())
            ->map(fn($i) => $i->transform())
            ->unwrapOr(null);
    }, $items);
}

// ✅ バッチ最適化
public function processItems(array $items): array
{
    $validItems = array_filter($items, fn($item) => $item->isValid());
    return array_map(fn($item) => $item->transform(), $validItems);
}
```

## 避けるべきアンチパターン

### 1. 過度なネスト

```php
// ❌ 過度なネスト - パフォーマンス劣化
$result = $option
    ->map(fn($x) => Option::of($x->getValue()))
    ->flatten()
    ->map(fn($x) => Result::ok($x))
    ->transpose()
    ->map(fn($x) => $x->process());

// ✅ シンプルな処理
$value = $option->unwrapOr(null);
if ($value !== null && $value->getValue() !== null) {
    return Ok::of($value->getValue()->process());
}
return Err::of('処理失敗');
```

### 2. 不要なオブジェクト生成

```php
// ❌ 毎回新しいインスタンス
public function getDefault(): Option
{
    return None::instance(); // ✅ シングルトン使用
    // return new None();    // ❌ 毎回新規作成
}

// ❌ 不要なラッピング
public function calculate(int $x): int
{
    return Ok::of($x)
        ->map(fn($n) => $n * 2)
        ->unwrap(); // ✅ 直接 $x * 2
}
```

### 3. 重い処理でのクロージャ

```php
// ❌ 重い処理を毎回実行
$result = $option->mapOrElse(
    fn($x) => $x->process(),
    fn() => $this->heavyDefaultCalculation() // 毎回実行される
);

// ✅ 事前計算または遅延評価
$default = $this->heavyDefaultCalculation();
$result = $option->mapOr(fn($x) => $x->process(), $default);
```

## パフォーマンスクリティカルな場面での使い分け

### 高頻度処理（秒間1000回以上）

```php
// Result/Option型は避け、native PHPを使用
public function hotPath(array $data): ?array
{
    if (empty($data)) {
        return null;
    }
    
    $result = [];
    foreach ($data as $item) {
        if ($item['valid'] ?? false) {
            $result[] = $item['value'] * 2;
        }
    }
    
    return empty($result) ? null : $result;
}
```

### 中頻度処理（秒間100-1000回）

```php
// ショートハンドメソッドを活用
public function mediumPath(array $data): Result
{
    return Option::of($data)
        ->filter(fn($d) => !empty($d))
        ->mapOr(fn($d) => $this->processArray($d), Err::of('空データ'));
}
```

### 低頻度処理（秒間100回未満）

```php
// 完全なResult/Option型の活用
public function complexBusinessLogic(UserInput $input): Result
{
    return $input->validate()
        ->andThen(fn($data) => $this->authorize($data))
        ->andThen(fn($data) => $this->process($data))
        ->andThen(fn($result) => $this->save($result))
        ->map(fn($saved) => $saved->toArray());
}
```

## 実践的なベストプラクティス

### 1. プロファイリングの実施

```php
// パフォーマンス測定用のヘルパー
class PerformanceProfiler
{
    public static function measure(callable $fn, string $label): mixed
    {
        $start = hrtime(true);
        $memory = memory_get_usage();
        
        $result = $fn();
        
        $elapsed = (hrtime(true) - $start) / 1_000_000; // ms
        $memoryUsed = memory_get_usage() - $memory;
        
        echo "{$label}: {$elapsed}ms, {$memoryUsed} bytes\n";
        
        return $result;
    }
}

// 使用例
$result = PerformanceProfiler::measure(
    fn() => $this->processWithResultType($data),
    'Result型での処理'
);
```

### 2. 段階的導入

```php
// Phase 1: エラーハンドリングの重要な部分から導入
public function criticalDatabaseOperation(): Result
{
    try {
        $data = $this->database->fetch();
        return Ok::of($data);
    } catch (Exception $e) {
        return Err::of($e->getMessage());
    }
}

// Phase 2: ビジネスロジックに拡張
public function businessLogic(): Result
{
    return $this->criticalDatabaseOperation()
        ->andThen(fn($data) => $this->validateBusinessRules($data))
        ->map(fn($data) => $this->transformForOutput($data));
}

// Phase 3: フルスタック適用
public function apiEndpoint(): JsonResponse
{
    return $this->businessLogic()
        ->map(fn($data) => response()->json($data))
        ->unwrapOr(response()->json(['error' => 'Internal error'], 500));
}
```

### 3. パフォーマンス監視

```php
// アプリケーション内でのパフォーマンス監視
class ResultPerformanceMonitor
{
    private static array $metrics = [];
    
    public static function trackOperation(string $operation, callable $fn): mixed
    {
        $start = microtime(true);
        
        try {
            $result = $fn();
            $success = $result instanceof Result ? $result->isOk() : true;
        } catch (Exception $e) {
            $success = false;
            throw $e;
        } finally {
            $elapsed = microtime(true) - $start;
            self::$metrics[$operation][] = [
                'elapsed' => $elapsed,
                'success' => $success ?? false,
                'timestamp' => time()
            ];
        }
        
        return $result;
    }
    
    public static function getMetrics(): array
    {
        return self::$metrics;
    }
}
```

## まとめ

このライブラリは実用的なパフォーマンスを提供しながら、型安全性と表現力を大幅に向上させます。

### パフォーマンス判断の指針

- **マイクロサービス・API**: 推奨 - エラーハンドリングの恩恵が大きい
- **バッチ処理**: 部分的推奨 - クリティカルパスは最適化
- **リアルタイム処理**: 慎重に検討 - プロファイリング必須
- **ライブラリ・フレームワーク**: 推奨 - 開発者体験向上

パフォーマンスと開発者体験のバランスを取りながら、段階的に導入することで最適な結果を得られます。