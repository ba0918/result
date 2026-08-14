# 高度なパターン・テクニック

ライブラリの全機能を効果的に活用する、上級者向けのパターンとテクニックを解説します。

## 🎯 このチュートリアルで学べること

- 複雑なメソッドチェーンの構築
- 高度なエラーハンドリングパターン
- flatten、transpose等の特殊メソッド活用
- パフォーマンス考慮事項
- アンチパターンと回避方法

## 📋 前提知識

- [初心者向けチュートリアル](getting_started.md)と[基本的な使用方法](basic_usage.md)の完了
- Result型・Option型の実用的な使用経験

## 🔄 複雑なメソッドチェーン

### パターン1: flatten() による平坦化

ネストしたResult型やOption型を一段階平坦化するメソッドです。

```php
use ba0918\Result\{Ok, Err, Some, None, Result, Option};

// Result<Result<T, E>, E> → Result<T, E>
function complexOperation(int $value): Result
{
    return new Ok($value)
        ->map(fn($v) => $v > 0 ? new Ok($v * 2) : new Err("負の値です"))
        ->flatten(); // Ok(Ok(4)) → Ok(4), Ok(Err("負の値です")) → Err("負の値です")
}

// 使用例
$result1 = complexOperation(2);  // Ok(4)
$result2 = complexOperation(-1); // Err("負の値です")

echo $result1->unwrapOr(0); // 4
echo $result2->unwrapErr(); // "負の値です"
```

```php
// 複数層の処理での活用
function processData(array $data): Result
{
    return new Ok($data)
        ->map(fn($d) => validateData($d))    // Result<Result<T, E>, E>
        ->flatten()                          // Result<T, E>
        ->andThen(fn($d) => enrichData($d))  // Result<T, E>
        ->map(fn($d) => transformData($d));  // Result<T, E>
}

function validateData(array $data): Result
{
    return isset($data['id']) ? 
        new Ok($data) : 
        new Err("IDが不足しています");
}

function enrichData(array $data): Result
{
    // 外部データソースからの情報付加
    $additionalInfo = ['timestamp' => time()];
    return new Ok(array_merge($data, $additionalInfo));
}

function transformData(array $data): array
{
    return [
        'id' => $data['id'],
        'processed_at' => date('Y-m-d H:i:s', $data['timestamp'])
    ];
}
```

### パターン2: transpose() による型変換

Result↔Option間の相互変換を行うメソッドです。

```php
// Option<Result<T, E>> → Result<Option<T>, E>
function processOptionalValue(?string $input): Option
{
    if ($input === null) {
        return None::instance();
    }
    
    return new Some(validateInput($input));
}

function validateInput(string $input): Result
{
    return strlen($input) > 0 ? 
        new Ok(trim($input)) : 
        new Err("空の入力です");
}

// transpose の活用
function handleOptionalValidation(?string $input): Result
{
    return processOptionalValue($input)  // Option<Result<string, string>>
        ->transpose()                    // Result<Option<string>, string>
        ->map(fn($opt) => $opt->unwrapOr("デフォルト値"));
}

// 使用例
$result1 = handleOptionalValidation("hello");  // Ok("hello")
$result2 = handleOptionalValidation(null);     // Ok("デフォルト値")
$result3 = handleOptionalValidation("");       // Err("空の入力です")
```

### パターン3: 複合的なエラーハンドリング

```php
use ba0918\Result\{Ok, Err, Result};

class DataProcessor
{
    public function processComplexData(array $input): Result
    {
        return $this->validateStructure($input)
            ->andThen(fn($data) => $this->enrichWithMetadata($data))
            ->andThen(fn($data) => $this->validateBusinessRules($data))
            ->andThen(fn($data) => $this->persistData($data))
            ->andThen(fn($id) => $this->sendNotification($id))
            ->map(fn($response) => [
                'success' => true,
                'message' => 'データ処理が完了しました',
                'response' => $response
            ]);
    }
    
    private function validateStructure(array $input): Result
    {
        $required = ['type', 'data', 'metadata'];
        
        foreach ($required as $field) {
            if (!isset($input[$field])) {
                return new Err("必須フィールドが不足: $field");
            }
        }
        
        return new Ok($input);
    }
    
    private function enrichWithMetadata(array $data): Result
    {
        // 外部サービスからメタデータを取得
        $metadata = $this->fetchExternalMetadata($data['type']);
        
        if ($metadata->isErr()) {
            return $metadata; // エラーをそのまま伝播
        }
        
        $enriched = array_merge($data, [
            'external_metadata' => $metadata->unwrap()
        ]);
        
        return new Ok($enriched);
    }
    
    private function fetchExternalMetadata(string $type): Result
    {
        // 外部API呼び出しをシミュレート
        $metadataMap = [
            'user' => ['schema_version' => '1.0', 'category' => 'personal'],
            'product' => ['schema_version' => '2.1', 'category' => 'catalog']
        ];
        
        if (!isset($metadataMap[$type])) {
            return new Err("未対応のデータ型: $type");
        }
        
        return new Ok($metadataMap[$type]);
    }
    
    private function validateBusinessRules(array $data): Result
    {
        // ビジネスルール検証
        if ($data['type'] === 'user' && !isset($data['data']['email'])) {
            return new Err("ユーザーデータにはメールアドレスが必要です");
        }
        
        if ($data['type'] === 'product' && !isset($data['data']['price'])) {
            return new Err("商品データには価格が必要です");
        }
        
        return new Ok($data);
    }
    
    private function persistData(array $data): Result
    {
        // データベース保存をシミュレート
        $id = 'record_' . uniqid();
        
        // 成功/失敗をランダムにシミュレート
        if (rand(0, 10) < 8) {
            return new Ok($id);
        } else {
            return new Err("データベース保存に失敗しました");
        }
    }
    
    private function sendNotification(string $recordId): Result
    {
        // 通知送信をシミュレート
        if (rand(0, 10) < 9) {
            return new Ok("通知送信完了: $recordId");
        } else {
            return new Err("通知送信に失敗しました");
        }
    }
}

// 使用例
$processor = new DataProcessor();
$result = $processor->processComplexData([
    'type' => 'user',
    'data' => ['email' => 'user@example.com', 'name' => 'Alice'],
    'metadata' => ['source' => 'web']
]);

if ($result->isOk()) {
    $response = $result->unwrap();
    echo "成功: " . $response['message'];
} else {
    echo "エラー: " . $result->unwrapErr();
}
```

## 🔧 高度なエラーハンドリングパターン

### パターン1: エラーの分類と回復

```php
use ba0918\Result\{Ok, Err, Result};

enum ErrorType: string
{
    case VALIDATION = 'validation';
    case NETWORK = 'network';
    case BUSINESS = 'business';
    case SYSTEM = 'system';
}

class DetailedError
{
    public function __construct(
        public readonly ErrorType $type,
        public readonly string $message,
        public readonly ?string $context = null
    ) {}
    
    public function isRecoverable(): bool
    {
        return match($this->type) {
            ErrorType::NETWORK => true,
            ErrorType::SYSTEM => false,
            ErrorType::VALIDATION => false,
            ErrorType::BUSINESS => false,
        };
    }
}

class RobustService
{
    public function processWithRetry(array $data, int $maxRetries = 3): Result
    {
        $attempt = 0;
        
        while ($attempt < $maxRetries) {
            $result = $this->processData($data);
            
            if ($result->isOk()) {
                return $result;
            }
            
            $error = $result->unwrapErr();
            if (!$error->isRecoverable()) {
                return $result; // 回復不可能なエラーは即座に返す
            }
            
            $attempt++;
            if ($attempt < $maxRetries) {
                sleep(pow(2, $attempt)); // 指数バックオフ
            }
        }
        
        return new Err(new DetailedError(
            ErrorType::SYSTEM,
            "最大試行回数に達しました",
            "試行回数: $maxRetries"
        ));
    }
    
    private function processData(array $data): Result
    {
        // ネットワークエラーをシミュレート（回復可能）
        if (rand(0, 10) < 3) {
            return new Err(new DetailedError(
                ErrorType::NETWORK,
                "ネットワーク接続に失敗しました",
                "timeout after 30s"
            ));
        }
        
        // バリデーションエラー（回復不可能）
        if (!isset($data['id'])) {
            return new Err(new DetailedError(
                ErrorType::VALIDATION,
                "IDフィールドが必要です",
                "required field missing"
            ));
        }
        
        return new Ok("データ処理完了: " . $data['id']);
    }
}
```

### パターン2: エラーの変換と集約

```php
use ba0918\Result\{Ok, Err, Result};

class ValidationResult
{
    public function __construct(
        public readonly array $errors = [],
        public readonly array $warnings = []
    ) {}
    
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }
    
    public function addError(string $field, string $message): self
    {
        $errors = $this->errors;
        $errors[$field][] = $message;
        return new self($errors, $this->warnings);
    }
    
    public function merge(ValidationResult $other): self
    {
        return new self(
            array_merge_recursive($this->errors, $other->errors),
            array_merge_recursive($this->warnings, $other->warnings)
        );
    }
}

class FormValidator
{
    public function validateForm(array $data): Result
    {
        $validation = new ValidationResult();
        
        // 各フィールドの検証
        $emailValidation = $this->validateEmail($data['email'] ?? '');
        $passwordValidation = $this->validatePassword($data['password'] ?? '');
        $ageValidation = $this->validateAge($data['age'] ?? null);
        
        // エラーの集約
        if ($emailValidation->isErr()) {
            $validation = $validation->addError('email', $emailValidation->unwrapErr());
        }
        
        if ($passwordValidation->isErr()) {
            $validation = $validation->addError('password', $passwordValidation->unwrapErr());
        }
        
        if ($ageValidation->isErr()) {
            $validation = $validation->addError('age', $ageValidation->unwrapErr());
        }
        
        // 結果の返却
        if ($validation->hasErrors()) {
            return new Err($validation);
        }
        
        return new Ok([
            'email' => $data['email'],
            'password' => $data['password'],
            'age' => $data['age']
        ]);
    }
    
    private function validateEmail(string $email): Result
    {
        if (empty($email)) {
            return new Err("メールアドレスが入力されていません");
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new Err("メールアドレスの形式が正しくありません");
        }
        
        return new Ok($email);
    }
    
    private function validatePassword(string $password): Result
    {
        if (strlen($password) < 8) {
            return new Err("パスワードは8文字以上である必要があります");
        }
        
        return new Ok($password);
    }
    
    private function validateAge(?int $age): Result
    {
        if ($age === null) {
            return new Err("年齢が入力されていません");
        }
        
        if ($age < 0 || $age > 150) {
            return new Err("年齢は0-150の範囲で入力してください");
        }
        
        return new Ok($age);
    }
}
```

## 🔄 Option型の高度な操作

### パターン1: 複数のOptionの組み合わせ

```php
use ba0918\Result\{Some, None, Option};

class UserProfileBuilder
{
    public function buildProfile(int $userId): Option
    {
        $user = $this->findUser($userId);
        $settings = $this->findSettings($userId);
        $preferences = $this->findPreferences($userId);
        
        // zip() を使用した組み合わせ
        return $user
            ->zip($settings)
            ->andThen(fn($userAndSettings) => 
                $preferences->map(fn($prefs) => [
                    'user' => $userAndSettings[0],
                    'settings' => $userAndSettings[1],
                    'preferences' => $prefs
                ])
            );
    }
    
    public function findOptimalSettings(int $userId): Option
    {
        $personalSettings = $this->findSettings($userId);
        $defaultSettings = $this->getDefaultSettings();
        
        // xor() を使用した排他的選択
        return $personalSettings->xor($defaultSettings);
    }
    
    private function findUser(int $id): Option
    {
        $users = [1 => ['id' => 1, 'name' => 'Alice']];
        return isset($users[$id]) ? new Some($users[$id]) : None::instance();
    }
    
    private function findSettings(int $userId): Option
    {
        $settings = [1 => ['theme' => 'dark', 'lang' => 'ja']];
        return isset($settings[$userId]) ? new Some($settings[$userId]) : None::instance();
    }
    
    private function findPreferences(int $userId): Option
    {
        $preferences = [1 => ['notifications' => true, 'newsletter' => false]];
        return isset($preferences[$userId]) ? new Some($preferences[$userId]) : None::instance();
    }
    
    private function getDefaultSettings(): Option
    {
        return new Some(['theme' => 'light', 'lang' => 'en']);
    }
}
```

### パターン2: フィルタリングと条件分岐

```php
use ba0918\Result\{Some, None, Option};

class ProductFilter
{
    public function findAvailableProduct(int $productId): Option
    {
        return $this->findProduct($productId)
            ->filter(fn($product) => $product['available'])  // 利用可能な商品のみ
            ->filter(fn($product) => $product['price'] > 0);  // 価格が正の商品のみ
    }
    
    public function findDiscountedProduct(int $productId): Option
    {
        return $this->findProduct($productId)
            ->andThen(fn($product) => {
                if ($product['discount'] > 0) {
                    $discountedPrice = $product['price'] * (1 - $product['discount'] / 100);
                    return new Some(array_merge($product, ['final_price' => $discountedPrice]));
                }
                return None::instance();
            });
    }
    
    public function getProductCategory(int $productId): Option
    {
        return $this->findProduct($productId)
            ->andThen(fn($product) => $this->findCategory($product['category_id']));
    }
    
    private function findProduct(int $id): Option
    {
        $products = [
            1 => ['id' => 1, 'name' => 'Laptop', 'price' => 80000, 'available' => true, 'discount' => 10, 'category_id' => 1],
            2 => ['id' => 2, 'name' => 'Mouse', 'price' => 0, 'available' => false, 'discount' => 0, 'category_id' => 2],
        ];
        
        return isset($products[$id]) ? new Some($products[$id]) : None::instance();
    }
    
    private function findCategory(int $categoryId): Option
    {
        $categories = [
            1 => ['id' => 1, 'name' => 'Electronics'],
            2 => ['id' => 2, 'name' => 'Accessories'],
        ];
        
        return isset($categories[$categoryId]) ? new Some($categories[$categoryId]) : None::instance();
    }
}
```

## ⚡ パフォーマンス考慮事項

### 1. 遅延評価の活用

```php
use ba0918\Result\{Ok, Err, Result};

class OptimizedProcessor
{
    public function processLargeDataset(array $items): Result
    {
        // unwrapOrElse() で遅延評価を活用
        return $this->validateDataset($items)
            ->andThen(fn($data) => $this->processInBatches($data))
            ->unwrapOrElse(fn() => $this->fallbackToCache());
    }
    
    private function validateDataset(array $items): Result
    {
        if (count($items) > 10000) {
            return new Err("データセットが大きすぎます");
        }
        return new Ok($items);
    }
    
    private function processInBatches(array $items): Result
    {
        // バッチ処理をシミュレート
        $batches = array_chunk($items, 100);
        $results = [];
        
        foreach ($batches as $batch) {
            $batchResult = $this->processBatch($batch);
            if ($batchResult->isErr()) {
                return $batchResult;
            }
            $results = array_merge($results, $batchResult->unwrap());
        }
        
        return new Ok($results);
    }
    
    private function processBatch(array $batch): Result
    {
        // バッチ処理の実装
        return new Ok(array_map(fn($item) => $item * 2, $batch));
    }
    
    private function fallbackToCache(): array
    {
        // キャッシュからのフォールバック（重い処理）
        return range(1, 100); // 実際にはキャッシュから読み込み
    }
}
```

### 2. メモリ効率の最適化

```php
use ba0918\Result\{Some, None, Option};

class MemoryEfficientProcessor
{
    public function processStream(iterable $stream): \Generator
    {
        foreach ($stream as $item) {
            $result = $this->processItem($item);
            
            if ($result->isSome()) {
                yield $result->unwrap();
            }
            
            // メモリリークを防ぐため明示的にunset
            unset($result, $item);
        }
    }
    
    private function processItem($item): Option
    {
        if (is_numeric($item) && $item > 0) {
            return new Some($item * 2);
        }
        return None::instance();
    }
}

// 使用例
$processor = new MemoryEfficientProcessor();
$largeDataset = range(1, 1000000);

foreach ($processor->processStream($largeDataset) as $processed) {
    // メモリ効率的な処理
    echo $processed . "\n";
}
```

## ❌ アンチパターンと回避方法

### アンチパターン1: 過度なunwrap()の使用

```php
// ❌ 悪い例 - unwrap()の乱用
function badExample(): string
{
    $user = findUser(1)->unwrap();           // 例外の可能性
    $settings = getSettings($user['id'])->unwrap(); // 例外の可能性
    $theme = $settings['theme']->unwrap();   // 例外の可能性
    
    return $theme;
}

// ✅ 良い例 - 安全な処理
function goodExample(): string
{
    return findUser(1)
        ->andThen(fn($user) => getSettings($user['id']))
        ->map(fn($settings) => $settings['theme'])
        ->unwrapOr('default');
}
```

### アンチパターン2: エラー情報の損失

```php
// ❌ 悪い例 - エラー情報の損失
function badErrorHandling(int $id): ?array
{
    $result = fetchUserData($id);
    return $result->isOk() ? $result->unwrap() : null; // エラー情報が失われる
}

// ✅ 良い例 - エラー情報の保持
function goodErrorHandling(int $id): Result
{
    return fetchUserData($id)
        ->mapErr(fn($error) => "ユーザーデータ取得エラー: $error");
}
```

### アンチパターン3: 不適切な型の使用

```php
// ❌ 悪い例 - null可能型との混在
function badMixing(?string $input): Option
{
    if ($input === null) {
        return None::instance();
    }
    return new Some($input); // nullチェックが必要になる
}

// ✅ 良い例 - 一貫した型使用
function goodTyping(Option $input): Option
{
    return $input->filter(fn($value) => !empty($value));
}
```

## 🚀 実践的な統合例

### 完全なワークフロー実装

```php
use ba0918\Result\{Ok, Err, Some, None, Result, Option};

class OrderProcessingWorkflow
{
    public function processOrder(array $orderData): Result
    {
        return $this->validateOrder($orderData)
            ->andThen(fn($order) => $this->checkInventory($order))
            ->andThen(fn($order) => $this->calculatePricing($order))
            ->andThen(fn($order) => $this->processPayment($order))
            ->andThen(fn($order) => $this->reserveItems($order))
            ->andThen(fn($order) => $this->generateInvoice($order))
            ->andThen(fn($invoice) => $this->sendNotifications($invoice))
            ->map(fn($result) => [
                'order_id' => $result['order_id'],
                'invoice_id' => $result['invoice_id'],
                'status' => 'completed',
                'message' => '注文が正常に処理されました'
            ]);
    }
    
    private function validateOrder(array $data): Result
    {
        if (!isset($data['items']) || empty($data['items'])) {
            return new Err("注文商品が指定されていません");
        }
        
        if (!isset($data['customer_id'])) {
            return new Err("顧客IDが指定されていません");
        }
        
        return new Ok($data);
    }
    
    private function checkInventory(array $order): Result
    {
        foreach ($order['items'] as $item) {
            $available = $this->getInventoryCount($item['product_id']);
            
            if ($available->isNone() || $available->unwrap() < $item['quantity']) {
                return new Err("商品ID {$item['product_id']} の在庫が不足しています");
            }
        }
        
        return new Ok($order);
    }
    
    private function getInventoryCount(int $productId): Option
    {
        $inventory = [1 => 10, 2 => 5, 3 => 0];
        return isset($inventory[$productId]) ? 
            new Some($inventory[$productId]) : 
            None::instance();
    }
    
    private function calculatePricing(array $order): Result
    {
        $total = 0;
        $calculatedItems = [];
        
        foreach ($order['items'] as $item) {
            $price = $this->getProductPrice($item['product_id']);
            
            if ($price->isNone()) {
                return new Err("商品ID {$item['product_id']} の価格情報が見つかりません");
            }
            
            $itemTotal = $price->unwrap() * $item['quantity'];
            $total += $itemTotal;
            
            $calculatedItems[] = array_merge($item, [
                'unit_price' => $price->unwrap(),
                'total_price' => $itemTotal
            ]);
        }
        
        return new Ok(array_merge($order, [
            'items' => $calculatedItems,
            'total_amount' => $total
        ]));
    }
    
    private function getProductPrice(int $productId): Option
    {
        $prices = [1 => 1000, 2 => 2000, 3 => 3000];
        return isset($prices[$productId]) ? 
            new Some($prices[$productId]) : 
            None::instance();
    }
    
    private function processPayment(array $order): Result
    {
        // 支払い処理をシミュレート
        if ($order['total_amount'] > 100000) {
            return new Err("支払い金額が上限を超えています");
        }
        
        $paymentId = 'pay_' . uniqid();
        return new Ok(array_merge($order, ['payment_id' => $paymentId]));
    }
    
    private function reserveItems(array $order): Result
    {
        $reservationId = 'res_' . uniqid();
        return new Ok(array_merge($order, ['reservation_id' => $reservationId]));
    }
    
    private function generateInvoice(array $order): Result
    {
        $invoiceId = 'inv_' . uniqid();
        return new Ok([
            'order_id' => 'ord_' . uniqid(),
            'invoice_id' => $invoiceId,
            'payment_id' => $order['payment_id'],
            'reservation_id' => $order['reservation_id'],
            'total_amount' => $order['total_amount']
        ]);
    }
    
    private function sendNotifications(array $invoice): Result
    {
        // 通知送信をシミュレート
        return new Ok($invoice);
    }
}

// 使用例
$workflow = new OrderProcessingWorkflow();
$orderData = [
    'customer_id' => 123,
    'items' => [
        ['product_id' => 1, 'quantity' => 2],
        ['product_id' => 2, 'quantity' => 1]
    ]
];

$result = $workflow->processOrder($orderData);

if ($result->isOk()) {
    $response = $result->unwrap();
    echo "注文処理完了: " . $response['order_id'];
} else {
    echo "注文処理エラー: " . $result->unwrapErr();
}
```

## 🚀 まとめと次のステップ

これで高度なパターンとテクニックを学習しました。

### 学習パス

1. **✅ 完了**: [初心者向けチュートリアル](getting_started.md)
2. **✅ 完了**: [基本的な使用方法](basic_usage.md)
3. **✅ 完了**: 高度なパターン・テクニック（このチュートリアル）

### より深く学ぶ

- **[ベストプラクティス](../guide/best_practices.md)** - チーム開発での運用指針
- **[パフォーマンスガイド](../guide/performance_guide.md)** - 詳細な最適化テクニック
- **[APIリファレンス](../api/result_api_reference.md)** - 全メソッドの完全仕様

### 実践への応用

- 小さなモジュールから段階的に導入
- チームでのコードレビューでパターンを共有
- パフォーマンス測定でボトルネックを特定
- 継続的な学習とパターンの改善

---

💡 **上級者のヒント**: これらのパターンを組み合わせることで、堅牢で保守性の高いコードベースを構築できます。常にビジネス要件とのバランスを考慮して適用してください。