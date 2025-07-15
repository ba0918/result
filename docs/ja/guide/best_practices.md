# ベストプラクティス集

プロジェクトでResult型・Option型を効果的に活用するための実用的なガイドラインと運用ベストプラクティスを解説します。

## 🎯 このガイドで学べること

- 適切な使用場面の判断基準
- メソッド選択の指針と使い分け
- エラーメッセージの設計指針
- チーム開発での運用方法
- 実際のプロジェクトでの導入戦略

## 🔍 適切な使用場面の判断基準

### Result型を使うべき場面

#### ✅ 推奨される場面

```php
// 1. ファイル操作
function readConfigFile(string $path): Result
{
    if (!file_exists($path)) {
        return new Err("ファイルが存在しません: $path");
    }
    
    $content = file_get_contents($path);
    if ($content === false) {
        return new Err("ファイルの読み込みに失敗しました");
    }
    
    return new Ok($content);
}

// 2. 外部API呼び出し
function callExternalAPI(string $endpoint): Result
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return new Err("API呼び出しエラー: $error");
    }
    
    curl_close($ch);
    
    if ($httpCode >= 400) {
        return new Err("HTTPエラー: $httpCode");
    }
    
    return new Ok($response);
}

// 3. バリデーション処理
function validateEmail(string $email): Result
{
    if (empty($email)) {
        return new Err("メールアドレスが空です");
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return new Err("メールアドレスの形式が正しくありません");
    }
    
    return new Ok($email);
}

// 4. データ変換処理
function parseJSON(string $json): Result
{
    $data = json_decode($json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new Err("JSON解析エラー: " . json_last_error_msg());
    }
    
    return new Ok($data);
}
```

#### ❌ 推奨されない場面

```php
// 単純な計算（例外の方が適切）
function add(int $a, int $b): Result
{
    return new Ok($a + $b); // これは不要
}

// プログラムエラー（例外の方が適切）
function getConfig(): Result
{
    if (!class_exists('Config')) {
        return new Err("Configクラスが存在しません"); // これは例外であるべき
    }
    return new Ok(new Config());
}
```

### Option型を使うべき場面

#### ✅ 推奨される場面

```php
// 1. データベース検索
function findUserById(int $id): Option
{
    $user = $this->database->selectOne('users', ['id' => $id]);
    
    if ($user === null) {
        return None::instance();
    }
    
    return new Some($user);
}

// 2. 設定値の取得
function getConfigValue(string $key): Option
{
    $value = $_ENV[$key] ?? null;
    
    if ($value === null) {
        return None::instance();
    }
    
    return new Some($value);
}

// 3. 配列・連想配列からの安全な取得
function safeArrayGet(array $array, string $key): Option
{
    if (!array_key_exists($key, $array)) {
        return None::instance();
    }
    
    return new Some($array[$key]);
}

// 4. 文字列操作の結果
function extractDomain(string $email): Option
{
    $parts = explode('@', $email);
    
    if (count($parts) !== 2) {
        return None::instance();
    }
    
    return new Some($parts[1]);
}
```

#### ❌ 推奨されない場面

```php
// 必須値（例外の方が適切）
function getCurrentUser(): Option
{
    // ログインしていないのはエラー状態
    return None::instance(); // これは例外であるべき
}

// 単純なnullチェック（従来の方法で十分）
function getName(?string $name): Option
{
    return $name === null ? None::instance() : new Some($name);
    // これは単純すぎるケース
}
```

## 🛠️ メソッド選択の指針

### unwrap() vs unwrapOr() vs expect()

#### unwrap() - 確実に成功する場合のみ

```php
// ✅ 良い例 - バリデーション済みの値
function processValidatedData(array $data): string
{
    $result = validateRequired($data)           // バリデーション済み
        ->andThen(fn($d) => enrichData($d))     // 確実に成功
        ->map(fn($d) => formatData($d));        // フォーマット
    
    return $result->unwrap(); // バリデーション済みなので安全
}

// ❌ 悪い例 - 失敗する可能性がある
function processUserInput(string $input): string
{
    $result = parseUserInput($input);
    return $result->unwrap(); // 危険！例外の可能性
}
```

#### unwrapOr() - デフォルト値がある場合

```php
// ✅ 良い例 - 設定値の取得
function getMaxRetries(): int
{
    return getConfigValue('max_retries')
        ->map(fn($value) => (int)$value)
        ->unwrapOr(3); // デフォルト値
}

// ✅ 良い例 - ユーザー表示名
function getDisplayName(int $userId): string
{
    return findUser($userId)
        ->map(fn($user) => $user['name'])
        ->unwrapOr('ゲストユーザー'); // デフォルト表示
}

// ❌ 悪い例 - 不適切なデフォルト値
function getPassword(): string
{
    return getConfigValue('password')
        ->unwrapOr('default123'); // セキュリティ上危険
}
```

#### expect() - 明確なエラーメッセージが必要な場合

```php
// ✅ 良い例 - デバッグ情報付き
function loadCriticalConfig(): array
{
    return readConfigFile('app.conf')
        ->andThen(fn($content) => parseJSON($content))
        ->expect('アプリケーション設定の読み込みに失敗しました');
}

// ✅ 良い例 - 開発時のデバッグ
function developmentHelper(string $data): ProcessedData
{
    return parseDebugData($data)
        ->expect("デバッグデータの解析に失敗: $data");
}

// ❌ 悪い例 - 本番環境での使用
function productionFunction(): string
{
    return riskyOperation()
        ->expect("失敗しました"); // 本番では unwrapOr() を使うべき
}
```

### map() vs andThen() の使い分け

#### map() - 値の変換

```php
// ✅ 型変換・フォーマット
$result = getUserAge($id)
    ->map(fn($age) => (string)$age)              // int → string
    ->map(fn($ageStr) => "$ageStr 歳")           // フォーマット
    ->unwrapOr('年齢不明');

// ✅ データ構造の変換
$userData = getUser($id)
    ->map(fn($user) => [
        'display_name' => $user['name'],
        'email' => $user['email'],
        'joined' => date('Y年m月', strtotime($user['created_at']))
    ]);
```

#### andThen() - 連続的な処理・エラー可能な処理

```php
// ✅ 連続的な処理
$result = getUser($id)
    ->andThen(fn($user) => validateUser($user))      // エラー可能
    ->andThen(fn($user) => enrichUserData($user))    // エラー可能
    ->andThen(fn($user) => saveUserData($user));     // エラー可能

// ✅ 条件付き処理
$permission = getUser($id)
    ->andThen(fn($user) => 
        $user['is_admin'] ? 
            new Ok($user) : 
            new Err('管理者権限が必要です')
    );
```

## 📝 エラーメッセージの設計指針

### 1. 具体的で実行可能なメッセージ

```php
// ✅ 良い例
function validatePassword(string $password): Result
{
    if (strlen($password) < 8) {
        return new Err("パスワードは8文字以上で入力してください（現在: " . strlen($password) . "文字）");
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        return new Err("パスワードに大文字を1文字以上含めてください");
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        return new Err("パスワードに数字を1文字以上含めてください");
    }
    
    return new Ok($password);
}

// ❌ 悪い例
function validatePassword(string $password): Result
{
    if (!isValidPassword($password)) {
        return new Err("無効なパスワード"); // 何が悪いのか不明
    }
    
    return new Ok($password);
}
```

### 2. コンテキスト情報の提供

```php
// ✅ 良い例
function processFile(string $filePath): Result
{
    if (!file_exists($filePath)) {
        return new Err("ファイルが見つかりません: $filePath");
    }
    
    $content = file_get_contents($filePath);
    if ($content === false) {
        return new Err("ファイルの読み込みに失敗しました: $filePath (権限を確認してください)");
    }
    
    return new Ok($content);
}

// ❌ 悪い例
function processFile(string $filePath): Result
{
    $content = file_get_contents($filePath);
    if ($content === false) {
        return new Err("エラー"); // 情報不足
    }
    
    return new Ok($content);
}
```

### 3. エラーメッセージの統一規則

```php
// エラーメッセージ規則クラス
class ErrorMessages
{
    // 形式: "操作が失敗しました: 理由 (解決方法)"
    public static function fileNotFound(string $path): string
    {
        return "ファイルが見つかりません: $path (パスを確認してください)";
    }
    
    public static function validationFailed(string $field, string $rule): string
    {
        return "入力検証に失敗しました: {$field}フィールドは{$rule}である必要があります";
    }
    
    public static function apiCallFailed(string $endpoint, int $statusCode): string
    {
        return "API呼び出しに失敗しました: $endpoint (HTTPステータス: $statusCode)";
    }
}

// 使用例
function validateEmail(string $email): Result
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return new Err(ErrorMessages::validationFailed('email', '有効なメールアドレス形式'));
    }
    
    return new Ok($email);
}
```

## 👥 チーム開発での運用ベストプラクティス

### 1. コーディング規約

#### 命名規則

```php
// ✅ 良い例 - 明確な命名
function parseUserData(string $json): Result { /* ... */ }
function findActiveUser(int $id): Option { /* ... */ }
function validateRequiredFields(array $data): Result { /* ... */ }

// ❌ 悪い例 - 曖昧な命名
function process(string $data): Result { /* ... */ }
function get(int $id): Option { /* ... */ }
function check(array $data): Result { /* ... */ }
```

#### 戻り値型の明示

```php
// ✅ 良い例
function loadConfig(string $path): Result
{
    // 実装...
}

function findUser(int $id): Option
{
    // 実装...
}

// ❌ 悪い例 - 型が不明
function loadConfig(string $path)
{
    return new Ok($config); // 戻り値型が不明
}
```

### 2. ドキュメント化規則

```php
/**
 * ユーザー設定ファイルを読み込み、バリデーションを行う
 *
 * @param string $configPath 設定ファイルのパス
 * @return Result<array, string> 成功時は設定配列、失敗時はエラーメッセージ
 * 
 * @example
 * $config = loadUserConfig('/path/to/config.json');
 * if ($config->isOk()) {
 *     $settings = $config->unwrap();
 *     // 設定を使用
 * } else {
 *     error_log($config->unwrapErr());
 * }
 */
function loadUserConfig(string $configPath): Result
{
    // 実装...
}
```

### 3. エラーハンドリング戦略

#### レイヤー別責任分離

```php
// データアクセス層 - 技術的エラー
class UserRepository
{
    public function findById(int $id): Result
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            
            if ($user === false) {
                return new Err("ユーザーが見つかりません: ID $id");
            }
            
            return new Ok($user);
        } catch (PDOException $e) {
            return new Err("データベースエラー: " . $e->getMessage());
        }
    }
}

// ビジネスロジック層 - ビジネス的エラー
class UserService
{
    public function activateUser(int $id): Result
    {
        return $this->userRepository->findById($id)
            ->andThen(fn($user) => $this->validateUserStatus($user))
            ->andThen(fn($user) => $this->performActivation($user))
            ->mapErr(fn($error) => "ユーザー有効化エラー: $error");
    }
    
    private function validateUserStatus(array $user): Result
    {
        if ($user['status'] === 'active') {
            return new Err("ユーザーは既に有効化されています");
        }
        
        if ($user['status'] === 'banned') {
            return new Err("BANされたユーザーは有効化できません");
        }
        
        return new Ok($user);
    }
}

// プレゼンテーション層 - ユーザー向けメッセージ
class UserController
{
    public function activateAction(int $id): JsonResponse
    {
        $result = $this->userService->activateUser($id);
        
        if ($result->isOk()) {
            return new JsonResponse([
                'success' => true,
                'message' => 'ユーザーが正常に有効化されました'
            ]);
        } else {
            return new JsonResponse([
                'success' => false,
                'message' => $result->unwrapErr()
            ], 400);
        }
    }
}
```

### 4. テスト戦略

#### Result型のテスト

```php
class UserServiceTest extends TestCase
{
    public function testActivateUserSuccess(): void
    {
        $userService = new UserService($this->mockRepository);
        $result = $userService->activateUser(1);
        
        $this->assertTrue($result->isOk());
        $this->assertEquals('active', $result->unwrap()['status']);
    }
    
    public function testActivateUserAlreadyActive(): void
    {
        $userService = new UserService($this->mockRepository);
        $result = $userService->activateUser(2); // 既に有効なユーザー
        
        $this->assertTrue($result->isErr());
        $this->assertStringContains('既に有効化', $result->unwrapErr());
    }
}
```

#### Option型のテスト

```php
class UserRepositoryTest extends TestCase
{
    public function testFindByIdExists(): void
    {
        $user = $this->repository->findOptionalById(1);
        
        $this->assertTrue($user->isSome());
        $this->assertEquals('alice@example.com', $user->unwrap()['email']);
    }
    
    public function testFindByIdNotExists(): void
    {
        $user = $this->repository->findOptionalById(999);
        
        $this->assertTrue($user->isNone());
    }
}
```

### 5. 段階的導入戦略

#### Phase 1: 新機能での導入

```php
// 新機能から導入開始
class NewFeatureService
{
    public function processNewFeature(array $data): Result
    {
        return $this->validateNewFeatureData($data)
            ->andThen(fn($d) => $this->executeNewFeature($d));
    }
}
```

#### Phase 2: 既存機能の部分的移行

```php
// 既存機能の一部をラップ
class LegacyUserService
{
    // 新しいメソッドはResult型
    public function createUserSafely(array $userData): Result
    {
        try {
            $user = $this->createUser($userData); // 既存メソッド
            return new Ok($user);
        } catch (Exception $e) {
            return new Err($e->getMessage());
        }
    }
    
    // 既存メソッドは変更せず
    public function createUser(array $userData): User
    {
        // 既存の実装
    }
}
```

#### Phase 3: 完全移行

```php
// 最終的に全体をResult型に統一
class ModernUserService
{
    public function createUser(array $userData): Result { /* ... */ }
    public function updateUser(int $id, array $data): Result { /* ... */ }
    public function deleteUser(int $id): Result { /* ... */ }
    public function findUser(int $id): Option { /* ... */ }
}
```

## 🚨 よくある落とし穴と回避方法

### 1. unwrap()の乱用

```php
// ❌ 悪い例
function dangerousChain(int $id): string
{
    $user = findUser($id)->unwrap();           // 危険
    $profile = getProfile($user['id'])->unwrap(); // 危険
    return formatProfile($profile)->unwrap();     // 危険
}

// ✅ 良い例
function safeChain(int $id): string
{
    return findUser($id)
        ->andThen(fn($user) => getProfile($user['id']))
        ->map(fn($profile) => formatProfile($profile))
        ->unwrapOr('プロフィール情報なし');
}
```

### 2. 過度な入れ子

```php
// ❌ 悪い例
function complexNesting(array $data): Result
{
    return validateData($data)->andThen(fn($d1) =>
        enrichData($d1)->andThen(fn($d2) =>
            processData($d2)->andThen(fn($d3) =>
                saveData($d3)->andThen(fn($d4) =>
                    notifyData($d4)
                )
            )
        )
    );
}

// ✅ 良い例
function clearPipeline(array $data): Result
{
    return $this->validateStep($data)
        ->andThen(fn($d) => $this->enrichStep($d))
        ->andThen(fn($d) => $this->processStep($d))
        ->andThen(fn($d) => $this->saveStep($d))
        ->andThen(fn($d) => $this->notifyStep($d));
}
```

### 3. 適切でないエラー粒度

```php
// ❌ 悪い例 - 粒度が粗すぎる
function validateUser(array $data): Result
{
    if (!$this->isValidUser($data)) {
        return new Err("ユーザーデータが無効"); // 何が無効か不明
    }
    return new Ok($data);
}

// ✅ 良い例 - 適切な粒度
function validateUser(array $data): Result
{
    return $this->validateEmail($data['email'] ?? '')
        ->andThen(fn() => $this->validatePassword($data['password'] ?? ''))
        ->andThen(fn() => $this->validateAge($data['age'] ?? null))
        ->map(fn() => $data);
}
```

## 📊 パフォーマンス指針

### 1. メソッドチェーンの最適化

```php
// ✅ 効率的なチェーン
function efficientProcessing(array $data): Result
{
    // 早期リターンを活用
    return $this->quickValidation($data)  // 軽い検証を先に
        ->andThen(fn($d) => $this->heavyProcessing($d)); // 重い処理を後に
}

// ❌ 非効率なチェーン
function inefficientProcessing(array $data): Result
{
    return $this->heavyProcessing($data)  // 重い処理を先に実行
        ->andThen(fn($d) => $this->quickValidation($d));
}
```

### 2. 遅延評価の活用

```php
// ✅ 遅延評価
function lazyDefault(): string
{
    return getExpensiveValue()
        ->unwrapOrElse(fn() => calculateExpensiveDefault()); // 必要時のみ実行
}

// ❌ 即座評価
function eagerDefault(): string
{
    $default = calculateExpensiveDefault(); // 常に実行される
    return getExpensiveValue()->unwrapOr($default);
}
```

## 🎯 まとめ

### チェックリスト

プロジェクトでの導入前に以下を確認してください：

- [ ] 使用場面が適切か判断している
- [ ] メソッド選択の指針を理解している
- [ ] エラーメッセージが具体的で実行可能
- [ ] チーム内でコーディング規約を合意
- [ ] テスト戦略を策定
- [ ] 段階的導入計画を立案

### 推奨学習パス

1. **小規模導入**: 新機能の一部から開始
2. **チーム共有**: ベストプラクティスをチームで共有
3. **段階的拡大**: 成功事例を元に適用範囲を拡大
4. **継続改善**: 運用を通じて規約を洗練

---

💡 **実践のヒント**: このガイドラインを段階的に導入し、チームの状況に合わせてカスタマイズしてください。完璧を求めず、継続的な改善を心がけることが成功の鍵です。