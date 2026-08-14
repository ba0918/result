# 従来コードからの移行ガイド

既存プロジェクトでResult型・Option型を段階的に導入するための実用的な移行戦略とベストプラクティスを解説します。

## 🎯 このガイドで学べること

- 段階的移行戦略と実装計画
- 具体的な移行手順（null → Option、例外 → Result）
- 既存コードとの混在パターン
- 移行時の注意点とトラブルシューティング
- チームでの移行プロセス管理

## 📋 前提条件

- PHP 8.3+ の環境
- Composer によるパッケージ管理
- 既存プロジェクトのコードベース

## 🚀 段階的移行戦略

### Phase 1: 基盤準備（1-2週間）

#### 1.1 ライブラリ導入

```bash
# 1. ライブラリのインストール
composer require ba0918/result

# 2. オートローダーの確認
composer dump-autoload
```

#### 1.2 チーム教育

```php
// サンプルコードでチーム学習
<?php
require_once 'vendor/autoload.php';

use ba0918\Result\{Ok, Err, Some, None};

// 基本的な使用例でチーム学習
function learningExample(): void
{
    // Result型の基本
    $result = Ok::of(42);
    echo $result->unwrap(); // 42
    
    // Option型の基本
    $option = Some::of("Hello");
    echo $option->unwrapOr("Default"); // Hello
}
```

#### 1.3 移行対象の選定

```php
// 移行しやすい関数の特定
class MigrationCandidate
{
    // ✅ 移行候補: null を返す関数
    public function findUser(int $id): ?array { /* ... */ }
    
    // ✅ 移行候補: 例外を投げる関数
    public function parseConfig(string $json): array { /* ... */ }
    
    // ❌ 移行不要: 単純な計算
    public function add(int $a, int $b): int { /* ... */ }
}
```

### Phase 2: 新機能での導入（2-4週間）

#### 2.1 新機能をResult/Option型で実装

```php
// 新機能は最初からResult/Option型で実装
class NewFeatureService
{
    public function processNewData(array $data): Result
    {
        return $this->validateNewData($data)
            ->andThen(fn($d) => $this->enrichNewData($d))
            ->andThen(fn($d) => $this->saveNewData($d));
    }
    
    public function findNewEntity(int $id): Option
    {
        $entity = $this->repository->findById($id);
        return $entity ? Some::of($entity) : None::instance();
    }
}
```

#### 2.2 ユーティリティ関数の作成

```php
// 移行を支援するユーティリティ
class ResultHelper
{
    /**
     * 従来の例外処理をResult型に変換
     */
    public static function tryCall(callable $fn, ...$args): Result
    {
        try {
            $result = $fn(...$args);
            return Ok::of($result);
        } catch (Exception $e) {
            return Err::of($e->getMessage());
        }
    }
    
    /**
     * null可能値をOption型に変換
     */
    public static function fromNullable($value): Option
    {
        return $value === null ? None::instance() : Some::of($value);
    }
}

// 使用例
$result = ResultHelper::tryCall(fn() => json_decode($json, true, 512, JSON_THROW_ON_ERROR));
$option = ResultHelper::fromNullable($_GET['user_id'] ?? null);
```

### Phase 3: 既存機能の段階的移行（4-8週間）

#### 3.1 ラッパー関数による移行

```php
// 段階1: 既存関数をラップ
class UserServiceLegacy
{
    // 既存メソッド（変更しない）
    public function findUser(int $id): ?array
    {
        // 既存の実装...
        return $this->database->find($id);
    }
    
    // 新しいメソッド（Option型）
    public function findUserSafe(int $id): Option
    {
        $user = $this->findUser($id);
        return $user === null ? None::instance() : Some::of($user);
    }
}

// 段階2: 新しいメソッドに移行
class UserServiceModern
{
    // 主要メソッドをOption型に変更
    public function findUser(int $id): Option
    {
        $user = $this->database->find($id);
        return $user === null ? None::instance() : Some::of($user);
    }
    
    // 後方互換性のためのレガシーメソッド
    public function findUserLegacy(int $id): ?array
    {
        return $this->findUser($id)->unwrapOr(null);
    }
}
```

#### 3.2 例外からResult型への移行

```php
// Before: 例外ベースの実装
class ConfigServiceLegacy
{
    public function loadConfig(string $path): array
    {
        if (!file_exists($path)) {
            throw new RuntimeException("Config file not found: $path");
        }
        
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException("Failed to read config file: $path");
        }
        
        $config = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Invalid JSON in config file: " . json_last_error_msg());
        }
        
        return $config;
    }
}

// After: Result型の実装
// Resultに載せるのは「呼び出し側が分岐する失敗」だけ。
// ファイルの不在や読み込み失敗はインフラ障害なので例外のまま。
// JSON形式の不正は、呼び出し側が対処したいかもしれないのでResultに載せる。
class ConfigServiceModern
{
    /**
     * @throws RuntimeException  設定ファイルが見つからない/読み込み失敗
     * @return Result<array, string>  JSON形式の不正がErrになる
     */
    public function loadConfig(string $path): Result
    {
        if (!file_exists($path)) {
            throw new RuntimeException("設定ファイルが見つかりません: $path");
        }
        
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException("設定ファイルの読み込みに失敗しました: $path");
        }
        
        $config = json_decode($content, true);
        if (!is_array($config)) {
            return Err::of('設定ファイルはJSONオブジェクトまたは配列である必要があります: ' . $path);
        }
        
        return Ok::of($config);
    }
    
    // 後方互換性を維持
    public function loadConfigLegacy(string $path): array
    {
        return $this->loadConfig($path)
            ->unwrapOrElse(fn($error) => throw new RuntimeException($error));
    }
}
```

### Phase 4: 完全移行（2-4週間）

#### 4.1 レガシーメソッドの除去

```php
// 最終段階: Result/Option型に統一
class UserServiceFinal
{
    public function findUser(int $id): Option { /* ... */ }
    public function createUser(array $data): Result { /* ... */ }
    public function updateUser(int $id, array $data): Result { /* ... */ }
    public function deleteUser(int $id): Result { /* ... */ }
}
```

## 🔄 具体的な移行パターン

### パターン1: null → Option

#### Before: null可能な戻り値

```php
class UserRepository
{
    public function findByEmail(string $email): ?User
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $user ?: null;
    }
    
    public function getUserProfile(int $userId): ?array
    {
        $user = $this->findById($userId);
        if ($user === null) {
            return null;
        }
        
        $profile = $this->getProfileData($user['id']);
        return $profile ?: null;
    }
}

// 使用側でのnullチェック
$user = $repository->findByEmail('test@example.com');
if ($user !== null) {
    echo "User found: " . $user['name'];
} else {
    echo "User not found";
}
```

#### After: Option型

```php
class UserRepository
{
    public function findByEmail(string $email): Option
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $user ? Some::of($user) : None::instance();
    }
    
    public function getUserProfile(int $userId): Option
    {
        return $this->findById($userId)
            ->andThen(fn($user) => $this->getProfileData($user['id']));
    }
    
    private function getProfileData(int $userId): Option
    {
        // プロフィールデータ取得の実装
        $profile = null; // 実際のデータベース取得に置き換える
        return $profile ? Some::of($profile) : None::instance();
    }
}

// 使用側でのOption処理
$message = $repository->findByEmail('test@example.com')
    ->map(fn($user) => "User found: " . $user['name'])
    ->unwrapOr("User not found");

echo $message;
```

### パターン2: 例外 → Result

#### Before: 例外ベースのエラーハンドリング

```php
class PaymentService
{
    public function processPayment(array $paymentData): array
    {
        // バリデーション
        if (empty($paymentData['amount'])) {
            throw new InvalidArgumentException('Amount is required');
        }
        
        if ($paymentData['amount'] <= 0) {
            throw new InvalidArgumentException('Amount must be positive');
        }
        
        // 外部API呼び出し
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://payment-api.example.com/charge');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($paymentData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false) {
            throw new RuntimeException('Payment API call failed');
        }
        
        if ($httpCode !== 200) {
            throw new RuntimeException("Payment failed with HTTP $httpCode");
        }
        
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Invalid response format');
        }
        
        return $result;
    }
}

// 使用側での例外処理
try {
    $result = $paymentService->processPayment($paymentData);
    echo "Payment successful: " . $result['transaction_id'];
} catch (Exception $e) {
    echo "Payment failed: " . $e->getMessage();
}
```

#### After: Result型

```php
class PaymentService
{
    /**
     * @throws RuntimeException  決済APIに到達できない/サーバ障害
     */
    public function processPayment(array $paymentData): Result
    {
        return $this->validatePaymentData($paymentData)
            ->andThen(fn($data) => $this->callPaymentAPI($data))
            ->andThen(fn($response) => $this->parseResponse($response));
    }
    
    private function validatePaymentData(array $data): Result
    {
        if (empty($data['amount'])) {
            return Err::of('支払金額が指定されていません');
        }
        
        if ($data['amount'] <= 0) {
            return Err::of('支払金額は正の値である必要があります');
        }
        
        return Ok::of($data);
    }
    
    /**
     * ネットワーク障害は例外のまま - 呼び出し側に「APIに到達できない」への
     * 意味のある分岐がないため。一方、HTTPエラーレスポンスは呼び出し側が
     * 分岐する業務的な結果なのでErrにする。
     *
     * @throws RuntimeException  決済APIに到達できない
     */
    private function callPaymentAPI(array $data): Result
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://payment-api.example.com/charge');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false) {
            throw new RuntimeException('決済APIに到達できません');
        }
        
        if ($httpCode >= 500) {
            // Server outages are infrastructure failures - the caller needs
            // retry or outage handling, not a business decision
            throw new RuntimeException("決済APIのサーバエラー (HTTPステータス: $httpCode)");
        }
        
        if ($httpCode >= 400) {
            return Err::of("決済が拒否されました (HTTPステータス: $httpCode)");
        }
        
        if ($httpCode < 200 || $httpCode >= 300) {
            // Redirects and other unexpected statuses are not a successful
            // payment response (follow_location is not enabled here)
            throw new RuntimeException("予期しない決済APIのステータス (HTTPステータス: $httpCode)");
        }
        
        return Ok::of($response);
    }
    
    private function parseResponse(string $response): Result
    {
        $result = json_decode($response, true);
        if (!is_array($result)) {
            return Err::of('決済APIのレスポンス形式が不正です');
        }
        
        return Ok::of($result);
    }
}

// 使用側でのResult処理
$message = $paymentService->processPayment($paymentData)
    ->map(fn($result) => "決済成功: " . $result['transaction_id'])
    ->unwrapOr("決済に失敗しました");

echo $message;
```

### パターン3: 混在パターンでの段階的移行

```php
// 段階的移行中のサービスクラス
class MixedUserService
{
    // 新しいメソッド（Result/Option型）
    public function createUserSafe(array $userData): Result
    {
        return $this->validateUserData($userData)
            ->andThen(fn($data) => $this->saveUser($data));
    }
    
    public function findUserSafe(int $id): Option
    {
        $user = $this->findUserLegacy($id);
        return $user === null ? None::instance() : Some::of($user);
    }
    
    // 既存メソッド（レガシー）- 段階的に削除予定
    public function createUser(array $userData): array
    {
        return $this->createUserSafe($userData)
            ->unwrapOrElse(fn($error) => throw new RuntimeException($error));
    }
    
    public function findUserLegacy(int $id): ?array
    {
        // 既存の実装をそのまま維持
        return $this->database->find($id);
    }
    
    // ブリッジメソッド - レガシーと新実装を繋ぐ
    public function getUserDisplayName(int $id): string
    {
        return $this->findUserSafe($id)
            ->map(fn($user) => $user['name'])
            ->unwrapOr('Unknown User');
    }
}
```

## 🔧 既存コードとの混在パターン

### パターン1: ラッパークラス

```php
// 既存のライブラリをラップ
// この境界で例外をResultに変換するのは、呼び出し側がこれらの失敗で
// 分岐したい（例: デフォルト設定にフォールバック）ため。
// 呼び出し側に分岐がない失敗は、例外のままにする。
class SafeFileOperations
{
    private FileOperations $fileOps;
    
    public function __construct(FileOperations $fileOps)
    {
        $this->fileOps = $fileOps;
    }
    
    public function readFile(string $path): Result
    {
        try {
            $content = $this->fileOps->read($path);
            return Ok::of($content);
        } catch (FileNotFoundException $e) {
            return Err::of("ファイルが見つかりません: $path");
        } catch (IOException $e) {
            return Err::of("ファイル読み込みエラー: " . $e->getMessage());
        }
    }
    
    public function findFile(string $pattern): Option
    {
        $files = $this->fileOps->glob($pattern);
        return empty($files) ? None::instance() : Some::of($files[0]);
    }
}
```

### パターン2: アダプターパターン

```php
// 既存のAPIクライアントをResult型に適合
class ResultApiClient
{
    private LegacyApiClient $client;
    
    public function get(string $endpoint): Result
    {
        try {
            $response = $this->client->get($endpoint);
            
            if ($response->getStatusCode() >= 400) {
                return Err::of("APIエラー: " . $response->getStatusCode());
            }
            
            return Ok::of($response->getBody());
        } catch (ApiException $e) {
            return Err::of("API呼び出しエラー: " . $e->getMessage());
        }
    }
}
```

### パターン3: 段階的インターフェース移行

```php
// 段階1: 既存インターフェース
interface UserRepositoryLegacy
{
    public function findById(int $id): ?array;
    public function save(array $user): array;
}

// 段階2: 新しいインターフェースを追加
interface UserRepositoryModern
{
    public function findById(int $id): Option;
    public function save(array $user): Result;
}

// 段階3: 両方を実装する移行クラス
class UserRepositoryTransition implements UserRepositoryLegacy, UserRepositoryModern
{
    // 新しい実装
    public function findById(int $id): Option
    {
        $user = $this->database->find($id);
        return $user ? Some::of($user) : None::instance();
    }
    
    public function save(array $user): Result
    {
        try {
            $saved = $this->database->save($user);
            return Ok::of($saved);
        } catch (DatabaseException $e) {
            return Err::of("保存エラー: " . $e->getMessage());
        }
    }
    
    // レガシー互換メソッド
    public function findByIdLegacy(int $id): ?array
    {
        return $this->findById($id)->unwrapOr(null);
    }
    
    public function saveLegacy(array $user): array
    {
        return $this->save($user)
            ->unwrapOrElse(fn($error) => throw new RuntimeException($error));
    }
}
```

## ⚠️ 移行時の注意点

### 1. パフォーマンスへの影響

```php
// ❌ 悪い例: 不要なオブジェクト生成
function inefficientConversion($value): Option
{
    return Some::of($value); // 常にSomeを作成
}

// ✅ 良い例: 適切な判定
function efficientConversion($value): Option
{
    return $value === null ? None::instance() : Some::of($value);
}

// ✅ キャッシュの活用
class OptimizedService
{
    private static ?Option $cachedNone = null;
    
    public function getNone(): Option
    {
        if (self::$cachedNone === null) {
            self::$cachedNone = None::instance();
        }
        return self::$cachedNone;
    }
}
```

### 2. メモリリークの防止

```php
// ❌ 悪い例: 循環参照
class BadService
{
    private Result $result;
    
    public function process(): Result
    {
        $this->result = Ok::of($this); // 循環参照
        return $this->result;
    }
}

// ✅ 良い例: 適切な設計
class GoodService
{
    public function process(array $data): Result
    {
        return Ok::of($this->processData($data)); // データのみを返す
    }
}
```

### 3. テストでの考慮事項

```php
// Result型のテスト
class PaymentServiceTest extends TestCase
{
    public function testSuccessfulPayment(): void
    {
        $service = new PaymentService($this->mockApiClient);
        $result = $service->processPayment(['amount' => 100]);
        
        $this->assertTrue($result->isOk());
        $this->assertEquals('tx_123', $result->unwrap()['transaction_id']);
    }
    
    public function testInvalidAmount(): void
    {
        $service = new PaymentService($this->mockApiClient);
        $result = $service->processPayment(['amount' => -1]);
        
        $this->assertTrue($result->isErr());
        $this->assertStringContains('正の値', $result->unwrapErr());
    }
}

// Option型のテスト
class UserRepositoryTest extends TestCase
{
    public function testFindExistingUser(): void
    {
        $repo = new UserRepository($this->mockDb);
        $user = $repo->findById(1);
        
        $this->assertTrue($user->isSome());
        $this->assertEquals('alice@example.com', $user->unwrap()['email']);
    }
    
    public function testFindNonExistentUser(): void
    {
        $repo = new UserRepository($this->mockDb);
        $user = $repo->findById(999);
        
        $this->assertTrue($user->isNone());
    }
}
```

## 🐛 トラブルシューティング

### 問題1: unwrap()での例外

```php
// 問題のあるコード
$user = findUser($id)->unwrap(); // UnwrapExceptionの可能性

// 解決策1: unwrapOr()を使用
$user = findUser($id)->unwrapOr(['name' => 'Guest']);

// 解決策2: パターンマッチング
$message = findUser($id)
    ->map(fn($user) => "こんにちは、{$user['name']}さん")
    ->unwrapOr("ユーザーが見つかりません");
```

### 問題2: 型の不整合

```php
// 問題のあるコード
function process(): Result
{
    $value = getValue();
    return $value; // Resultではない値を返している
}

// 解決策: 適切な型変換
function process(): Result
{
    $value = getValue();
    
    if ($value === null) {
        return Err::of("値が取得できませんでした");
    }
    
    return Ok::of($value);
}
```

### 問題3: エラーメッセージの不整合

```php
// 問題のあるコード
function validate($data): Result
{
    if (!isValid($data)) {
        return Err::of("Invalid"); // 情報不足
    }
    return Ok::of($data);
}

// 解決策: 具体的なエラーメッセージ
function validate($data): Result
{
    if (empty($data['email'])) {
        return Err::of("メールアドレスが入力されていません");
    }
    
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        return Err::of("メールアドレスの形式が正しくありません");
    }
    
    return Ok::of($data);
}
```

## 📊 移行進捗の管理

### 移行チェックリスト

```php
// 移行進捗を追跡するためのチェックリスト
class MigrationTracker
{
    private array $migrations = [
        'UserService::findUser' => ['status' => 'completed', 'type' => 'null_to_option'],
        'UserService::createUser' => ['status' => 'in_progress', 'type' => 'exception_to_result'],
        'PaymentService::process' => ['status' => 'pending', 'type' => 'exception_to_result'],
    ];
    
    public function getProgress(): array
    {
        $total = count($this->migrations);
        $completed = count(array_filter($this->migrations, fn($m) => $m['status'] === 'completed'));
        
        return [
            'total' => $total,
            'completed' => $completed,
            'percentage' => round(($completed / $total) * 100, 2)
        ];
    }
}
```

### 自動テストによる移行検証

```bash
# 移行検証用のテストスイート
composer exec phpunit tests/Migration/

# 型チェックで移行の整合性確認
composer exec phpstan analyse src/ --level=max
```

## 🎯 まとめ

### 成功のポイント

1. **段階的アプローチ**: 一度にすべてを変更しない
2. **チーム教育**: 事前にライブラリの理解を深める
3. **後方互換性**: 移行期間中は既存コードも動作させる
4. **継続的テスト**: 各段階で十分なテストを実施
5. **文書化**: 移行理由と方法を明確に記録

### 避けるべき落とし穴

- 無計画な一括移行
- unwrap()の過度な使用
- エラーハンドリングの軽視
- パフォーマンスへの無関心
- チーム内での知識共有不足

---

💡 **実践のヒント**: 移行は段階的に行い、各フェーズで成果を確認してから次に進んでください。完璧な移行より、継続的な改善を重視することが重要です。