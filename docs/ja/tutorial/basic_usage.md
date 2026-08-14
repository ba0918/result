# 基本的な使用方法

実際のプロジェクトで即座に適用できる、Result型・Option型の実用的なパターンと使用例を解説します。

## 🎯 このチュートリアルで学べること

- 実際のコードでのbefore/after比較
- 典型的なエラーハンドリングパターン
- null安全性の実現方法
- メソッドチェーンによる宣言的なコード
- 実用的なユースケース

## 📋 前提知識

- [初心者向けチュートリアル](getting_started.md)の完了
- Result型・Option型の基本概念の理解

## 🔄 実際のコードでの置き換え例

### パターン1: API呼び出しのエラーハンドリング

#### Before（従来のPHP）

```php
// 従来のコード - 危険で冗長
class UserService
{
    public function getUser(int $id): ?array
    {
        try {
            $response = file_get_contents("https://api.example.com/users/$id");
            if ($response === false) {
                return null; // エラー情報が失われる
            }
            
            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("JSON parse error: " . json_last_error_msg());
                return null; // エラー情報が失われる
            }
            
            if (!isset($data['id'])) {
                return null; // なぜnullなのかわからない
            }
            
            return $data;
        } catch (Exception $e) {
            error_log("API error: " . $e->getMessage());
            return null; // すべてのエラーがnullに
        }
    }
    
    public function displayUser(int $id): string
    {
        $user = $this->getUser($id);
        if ($user === null) {
            return "User not found"; // エラーの詳細がわからない
        }
        
        return "User: " . $user['name'];
    }
}
```

#### After（Result型使用）

```php
use ba0918\Result\{Ok, Err, Result};

// Result型使用 - 安全で明確
class UserService
{
    public function getUser(int $id): Result
    {
        // API呼び出し
        $response = file_get_contents("https://api.example.com/users/$id");
        if ($response === false) {
            return Err::of("API呼び出しに失敗しました");
        }
        
        // JSON解析
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return Err::of("JSON解析エラー: " . json_last_error_msg());
        }
        
        // データ検証
        if (!isset($data['id'])) {
            return Err::of("不正なユーザーデータ: IDが見つかりません");
        }
        
        return Ok::of($data);
    }
    
    public function displayUser(int $id): string
    {
        return $this->getUser($id)
            ->map(fn($user) => "User: " . $user['name'])
            ->unwrapOr("ユーザーが見つかりませんでした");
    }
    
    public function displayUserWithError(int $id): string
    {
        $result = $this->getUser($id);
        
        if ($result->isOk()) {
            $user = $result->unwrap();
            return "User: " . $user['name'];
        } else {
            $error = $result->unwrapErr();
            return "エラー: " . $error;
        }
    }
}
```

### パターン2: データベース検索とnull安全性

#### Before（従来のPHP）

```php
// 従来のコード - null参照エラーの危険性
class ProductRepository
{
    public function findById(int $id): ?array
    {
        // PDOでの検索をシミュレート
        $products = [
            1 => ['id' => 1, 'name' => 'Laptop', 'price' => 80000],
            2 => ['id' => 2, 'name' => 'Mouse', 'price' => 1500],
        ];
        
        return $products[$id] ?? null;
    }
    
    public function getProductName(int $id): string
    {
        $product = $this->findById($id);
        
        // null チェックを忘れがち
        return $product['name']; // null の場合は Fatal Error!
    }
    
    public function calculateTax(int $id): float
    {
        $product = $this->findById($id);
        if ($product === null) {
            return 0.0; // エラーケースが曖昧
        }
        
        return $product['price'] * 0.1;
    }
}
```

#### After（Option型使用）

```php
use ba0918\Result\{Some, None, Option};

// Option型使用 - null安全
class ProductRepository
{
    public function findById(int $id): Option
    {
        $products = [
            1 => ['id' => 1, 'name' => 'Laptop', 'price' => 80000],
            2 => ['id' => 2, 'name' => 'Mouse', 'price' => 1500],
        ];
        
        if (isset($products[$id])) {
            return Some::of($products[$id]);
        }
        return None::instance();
    }
    
    public function getProductName(int $id): string
    {
        return $this->findById($id)
            ->map(fn($product) => $product['name'])
            ->unwrapOr('商品名不明');
    }
    
    public function calculateTax(int $id): Option
    {
        return $this->findById($id)
            ->map(fn($product) => $product['price'] * 0.1);
    }
    
    public function getFormattedPrice(int $id): string
    {
        return $this->findById($id)
            ->map(fn($product) => number_format($product['price']) . '円')
            ->unwrapOr('価格情報なし');
    }
}
```

## 🔗 メソッドチェーンの基本パターン

### パターン1: データ変換の連鎖

```php
use ba0918\Result\{Ok, Err, Some, None};

// 文字列の正規化と検証
function processUsername(string $input): Result
{
    return Ok::of($input)
        ->map(fn($s) => trim($s))                    // 1. 空白除去
        ->map(fn($s) => strtolower($s))              // 2. 小文字化
        ->andThen(fn($s) => strlen($s) >= 3 ? 
            Ok::of($s) : 
            Err::of("ユーザー名は3文字以上である必要があります"))  // 3. 長さ検証
        ->andThen(fn($s) => preg_match('/^[a-z0-9_]+$/', $s) ? 
            Ok::of($s) : 
            Err::of("ユーザー名に無効な文字が含まれています"));    // 4. 文字種検証
}

// 使用例
$result = processUsername("  John_123  ");
echo $result->unwrapOr("無効なユーザー名"); // "john_123"

$error = processUsername("ab");
echo $error->unwrapErr(); // "ユーザー名は3文字以上である必要があります"
```

### パターン2: 複数の検索結果の組み合わせ

```php
// ユーザーとその設定を組み合わせ
function getUserWithSettings(int $userId): Option
{
    return findUser($userId)
        ->andThen(fn($user) => 
            getSettings($user['id'])
                ->map(fn($settings) => array_merge($user, ['settings' => $settings]))
        );
}

function findUser(int $id): Option
{
    $users = [1 => ['id' => 1, 'name' => 'Alice']];
    return isset($users[$id]) ? Some::of($users[$id]) : None::instance();
}

function getSettings(int $userId): Option
{
    $settings = [1 => ['theme' => 'dark', 'lang' => 'ja']];
    return isset($settings[$userId]) ? Some::of($settings[$userId]) : None::instance();
}

// 使用例
$userWithSettings = getUserWithSettings(1);
if ($userWithSettings->isSome()) {
    $data = $userWithSettings->unwrap();
    echo "ユーザー: " . $data['name'] . ", テーマ: " . $data['settings']['theme'];
}
```

## 🛠️ 典型的なエラーハンドリングパターン

### パターン1: 設定ファイルの読み込み

```php
use ba0918\Result\{Ok, Err, Result};

/**
 * @throws RuntimeException  設定ファイルが見つからない/読み込み失敗
 * @return Result<array, string>  JSON形式の不正や必須項目不足がErrになる
 */
function loadConfiguration(string $configPath): Result
{
    // インフラ障害（ファイルの不在・読み込み不能）は例外のまま
    if (!file_exists($configPath)) {
        throw new RuntimeException('設定ファイルが見つかりません: ' . $configPath);
    }
    
    $content = file_get_contents($configPath);
    if ($content === false) {
        throw new RuntimeException('設定ファイルの読み込みに失敗しました: ' . $configPath);
    }
    
    // 呼び出し側が分岐したいデータの問題はErrにする
    $config = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return Err::of("設定ファイルのJSON形式が不正です: " . json_last_error_msg());
    }
    
    $required = ['app_name', 'database'];
    foreach ($required as $key) {
        if (!isset($config[$key])) {
            return Err::of("必須設定項目が不足しています: $key");
        }
    }
    
    return Ok::of($config);
}

// 使用例 - デフォルト設定との合成
function getAppConfig(): array
{
    $defaultConfig = [
        'app_name' => 'DefaultApp',
        'debug' => false,
        'database' => ['host' => 'localhost']
    ];
    
    try {
        // Err（JSON形式の不正・必須項目不足）は同じ例外に変換し、
        // インフラ障害とデータの問題を1つのハンドラで扱う
        return loadConfiguration('config.json')
            ->map(fn($config) => array_merge($defaultConfig, $config))
            ->unwrapOrElse(fn($error) => throw new RuntimeException($error));
    } catch (RuntimeException $e) {
        return $defaultConfig;
    }
}
```

### パターン2: バリデーション処理

```php
use ba0918\Result\{Ok, Err, Result};

class UserValidator
{
    public function validateUserData(array $data): Result
    {
        return $this->validateEmail($data['email'] ?? '')
            ->andThen(fn($email) => $this->validatePassword($data['password'] ?? ''))
            ->andThen(fn($password) => $this->validateAge($data['age'] ?? null))
            ->map(fn($age) => [
                'email' => $data['email'],
                'password' => $data['password'],
                'age' => $age
            ]);
    }
    
    private function validateEmail(string $email): Result
    {
        if (empty($email)) {
            return Err::of("メールアドレスが入力されていません");
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return Err::of("メールアドレスの形式が正しくありません");
        }
        
        return Ok::of($email);
    }
    
    private function validatePassword(string $password): Result
    {
        if (strlen($password) < 8) {
            return Err::of("パスワードは8文字以上である必要があります");
        }
        
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            return Err::of("パスワードは英字と数字を含む必要があります");
        }
        
        return Ok::of($password);
    }
    
    private function validateAge(?int $age): Result
    {
        if ($age === null) {
            return Err::of("年齢が入力されていません");
        }
        
        if ($age < 0 || $age > 150) {
            return Err::of("年齢は0-150の範囲で入力してください");
        }
        
        return Ok::of($age);
    }
}

// 使用例
$validator = new UserValidator();
$result = $validator->validateUserData([
    'email' => 'user@example.com',
    'password' => 'password123',
    'age' => 25
]);

if ($result->isOk()) {
    $validData = $result->unwrap();
    echo "バリデーション成功: " . $validData['email'];
} else {
    echo "バリデーションエラー: " . $result->unwrapErr();
}
```

## 🔧 null安全性の実現

### パターン1: 連想配列からの安全な値取得

```php
use ba0918\Result\{Some, None, Option};

function safeGet(array $array, string $key): Option
{
    return isset($array[$key]) ? Some::of($array[$key]) : None::instance();
}

function safeGetNested(array $array, array $keys): Option
{
    $current = $array;
    
    foreach ($keys as $key) {
        if (!isset($current[$key])) {
            return None::instance();
        }
        $current = $current[$key];
    }
    
    return Some::of($current);
}

// 使用例
$data = [
    'user' => [
        'profile' => [
            'name' => 'Alice',
            'settings' => [
                'theme' => 'dark'
            ]
        ]
    ]
];

// 従来の危険な方法
// $theme = $data['user']['profile']['settings']['theme']; // エラーになる可能性

// 安全な方法
$theme = safeGetNested($data, ['user', 'profile', 'settings', 'theme'])
    ->unwrapOr('light');

echo "テーマ: $theme"; // "テーマ: dark"
```

### パターン2: データベース結果の安全な処理

```php
use ba0918\Result\{Some, None, Option};

class UserRepository
{
    public function findByEmail(string $email): Option
    {
        // 実際のDB検索をシミュレート
        $users = [
            'alice@example.com' => ['id' => 1, 'name' => 'Alice', 'role' => 'admin'],
            'bob@example.com' => ['id' => 2, 'name' => 'Bob', 'role' => 'user'],
        ];
        
        if (isset($users[$email])) {
            return Some::of($users[$email]);
        }
        return None::instance();
    }
    
    public function getUserRole(string $email): string
    {
        return $this->findByEmail($email)
            ->map(fn($user) => $user['role'])
            ->unwrapOr('guest');
    }
    
    public function isAdmin(string $email): bool
    {
        return $this->findByEmail($email)
            ->map(fn($user) => $user['role'] === 'admin')
            ->unwrapOr(false);
    }
    
    public function getDisplayName(string $email): string
    {
        return $this->findByEmail($email)
            ->map(fn($user) => $user['name'])
            ->map(fn($name) => "Mr./Ms. $name")
            ->unwrapOr('ゲストユーザー');
    }
}
```

## 🎯 実用的なユースケース

### ケース1: フォーム処理

```php
use ba0918\Result\{Ok, Err, Some, None, Result, Option};

class ContactFormProcessor
{
    public function processForm(array $formData): Result
    {
        return $this->validateForm($formData)
            ->andThen(fn($data) => $this->sendEmail($data))
            ->andThen(fn($result) => $this->saveToDatabase($formData))
            ->map(fn($id) => "お問い合わせを受け付けました。ID: $id");
    }
    
    private function validateForm(array $data): Result
    {
        $name = $this->getFormValue($data, 'name');
        $email = $this->getFormValue($data, 'email');
        $message = $this->getFormValue($data, 'message');
        
        if ($name->isNone()) {
            return Err::of("お名前を入力してください");
        }
        
        if ($email->isNone()) {
            return Err::of("メールアドレスを入力してください");
        }
        
        if ($message->isNone()) {
            return Err::of("メッセージを入力してください");
        }
        
        return Ok::of($data);
    }
    
    private function getFormValue(array $data, string $key): Option
    {
        $value = trim($data[$key] ?? '');
        return empty($value) ? None::instance() : Some::of($value);
    }
    
    private function sendEmail(array $data): Result
    {
        // メール送信をシミュレート
        $success = rand(0, 1); // ランダムに成功/失敗
        
        if ($success) {
            return Ok::of("メール送信成功");
        } else {
            return Err::of("メール送信に失敗しました");
        }
    }
    
    private function saveToDatabase(array $data): Result
    {
        // DB保存をシミュレート
        $id = rand(1000, 9999);
        return Ok::of($id);
    }
}

// 使用例
$processor = new ContactFormProcessor();
$result = $processor->processForm([
    'name' => 'Alice',
    'email' => 'alice@example.com',
    'message' => 'こんにちは'
]);

echo $result->unwrapOr('処理に失敗しました');
```

### ケース2: 外部API連携

```php
use ba0918\Result\{Ok, Err, Result};

class WeatherService
{
    public function getWeather(string $city): Result
    {
        return $this->fetchWeatherData($city)
            ->andThen(fn($response) => $this->parseResponse($response))
            ->andThen(fn($data) => $this->extractTemperature($data))
            ->map(fn($temp) => $this->formatTemperature($temp));
    }
    
    private function fetchWeatherData(string $city): Result
    {
        // 実際のAPI呼び出しをシミュレート
        $validCities = ['Tokyo', 'Osaka', 'Nagoya'];
        
        if (!in_array($city, $validCities, true)) {
            return Err::of("対応していない都市です: $city");
        }
        
        // API レスポンスをシミュレート
        $response = json_encode([
            'weather' => [
                'main' => ['temp' => rand(15, 35)]
            ]
        ]);
        
        return Ok::of($response);
    }
    
    private function parseResponse(string $response): Result
    {
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return Err::of("API レスポンスの解析に失敗しました");
        }
        
        return Ok::of($data);
    }
    
    private function extractTemperature(array $data): Result
    {
        if (!isset($data['weather']['main']['temp'])) {
            return Err::of("温度データが見つかりません");
        }
        
        return Ok::of($data['weather']['main']['temp']);
    }
    
    private function formatTemperature(int $temp): string
    {
        return "{$temp}℃";
    }
}

// 使用例
$weather = new WeatherService();
$result = $weather->getWeather('Tokyo');

echo $result->unwrapOr('天気情報を取得できませんでした');
```

## 🚀 次のステップ

このチュートリアルで基本的な使用パターンを理解できました。

### 学習パス

1. **✅ 完了**: [初心者向けチュートリアル](getting_started.md)
2. **✅ 完了**: 基本的な使用方法（このチュートリアル）
3. **➡️ 次**: [高度なパターン](advanced_patterns.md) - より複雑な使用例とテクニック

### より詳しく学ぶ

- **[ベストプラクティス](../guide/best_practices.md)** - チーム開発での運用指針
- **[パフォーマンスガイド](../guide/performance_guide.md)** - 効率的な使用方法
- **[移行ガイド](../guide/migration_guide.md)** - 既存プロジェクトへの導入

---

💡 **実践のヒント**: この章で紹介したパターンを、実際のプロジェクトの小さな部分から適用してみてください。段階的な導入が成功の鍵です。