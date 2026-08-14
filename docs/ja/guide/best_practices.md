# ベストプラクティス集

プロジェクトでResult型・Option型を効果的に活用するための実用的なガイドラインと運用ベストプラクティスを解説します。

## 🎯 このガイドで学べること

- 適切な使用場面の判断基準
- メソッド選択の指針と使い分け
- エラーメッセージの設計指針
- チーム開発での運用方法
- 実際のプロジェクトでの導入戦略

## 🔍 適切な使用場面の判断基準

あらゆる失敗に対して、まず次の問いを立てます。

> その失敗を、呼び出し側は「通常の分岐」として処理したいだろうか？

「はい」なら `Result` / `Option` で表現し、「いいえ」なら `Exception` として
抜け出させます。以降の節は、この問いの適用方法です。

| 状況 | 表現 |
|---|---|
| 正常な「値なし」で、理由が不要 | `Option<T>` |
| 想定内の失敗で、呼び出し側に分岐させたい（バリデーション・業務ルール） | `Result<T, E>` |
| 不変条件違反・プログラミングミス | `Exception` |
| この層では回復不能なインフラ障害（ファイル・DB・ネットワーク） | `Exception` |
| 上位層でリトライや代替処理を行う障害 | 境界で `Result` に変換 |

### Result型を使うべき場面

#### ✅ Resultに載せるべき失敗

呼び出し側が分岐することを期待されている失敗です。型のバリアントごとに
呼び出し側が「やるべきこと」を持っているので、型で列挙する価値があります。

```php
// 1. バリデーション - 呼び出し側が項目ごとに分岐する
/** @return Result<array, InvalidField[]> */
function validateForm(array $input): Result
{
    // 不正フィールドのリストをErrで返す
}

// 2. 業務ルール違反 - 呼び出し側が種類ごとに処理する
/** @return Result<User, UserAlreadyExists|InvalidPassword> */
function addUser(...): Result
{
    if ($this->userExists($user)) {
        return Err::of(new UserAlreadyExists($user));
    }
    ...
}

// 3. 呼び出し側が区別する必要のある想定内の失敗群
/** @return Result<Order, InsufficientBalance|OrderCancelled> */
function placeOrder(...): Result
```

呼び出し側には、エラーバリアントごとに実際の分岐ができます。

```php
$result = addUser($user, $password);

if ($result->isErr()) {
    return match (true) {
        $result->unwrapErr() instanceof UserAlreadyExists => 'exists',
        $result->unwrapErr() instanceof InvalidPassword   => 'invalid',
    };
}
```

#### ❌ 例外のまま残すべき失敗

すべての失敗を `Result` に載せてはいけません。次の 2 つは一見自然に見えますが、
呼び出し側を困らせます。

```php
// ❌ ダメな例: インフラ障害をErrで包む - 呼び出し側は何もできない
function readConfigFile(string $path): Result
{
    $content = file_get_contents($path); // ここでの失敗は「分岐」ではない
    if ($content === false) {
        return Err::of('ファイルの読み込みに失敗しました: ' . $path);
    }
    return Ok::of($content);
}

// 呼び出し側は結局こうなる - 意味のあるelseがない:
$config = readConfigFile($path)->unwrapOr([]);
// ファイルの不在も権限エラーも、同じように黙って握りつぶされる。
```

```php
// ❌ ダメな例: プログラミングミスをErrで表現
function getConfig(): Result
{
    if (!class_exists('Config')) {
        return Err::of('Configクラスが存在しません'); // これはバグであって分岐ではない
    }
    return Ok::of(new Config());
}
```

呼び出し側に意味のある分岐がない失敗は、例外のままにします。

```php
// ✅ 良い例: インフラ障害は例外のまま
function readConfigFile(string $path): array
{
    // FileNotFoundException / PermissionDeniedException が例外として伝播する
    return parse_ini_file($path, true);
}
```

#### 境界での変換（例外がResultになる地点）

「インフラ障害は例外」はデフォルトであって絶対ではありません。リトライや
代替処理を担当する層は、その境界で失敗を `Result` に変換する意味があります。

```text
PDOException
    ↓ Repository境界で意味付け
InfrastructureException
    ↓ UseCase境界で回復可能なら変換
Result<User, ServiceUnavailable>
```

```php
/**
 * この層はフォールバックを提供できるので、失敗が「分岐」になる。
 *
 * @throws InfrastructureException
 */
public function fetchUser(int $id): Result
{
    try {
        return Ok::of($this->repository->find($id));
    } catch (UserNotFound $e) {
        return Err::of($e);            // 想定内の不在 → Result
    }
    // InfrastructureException は伝播する - この層にはフォールバックがない
}
```

同じ失敗でも層によって表現が変わります。グローバルに決めるのではなく、
「どこで分岐が意味を持つか」を境界ごとに判断します。

### Option型を使うべき場面

`None` は**正常な不在**だけを意味します。呼び出し側が可能性として想定している
「見つからない」ケースです。異常系の失敗を `None` に潰してはいけません。
DBの障害とユーザーの不在は別の状況であり、呼び出し側は区別できなければいけません。

#### ✅ 推奨される場面

```php
// 1. データベース検索 - 不在は正常な分岐、障害は例外
function findUserById(int $id): Option
{
    // DatabaseException は伝播する。「レコードが無い」だけが None になる
    $user = $this->database->selectOne('users', ['id' => $id]);
    
    if ($user === null) {
        return None::instance();
    }
    
    return Some::of($user);
}

// 2. 設定値の取得
function getConfigValue(string $key): Option
{
    $value = $_ENV[$key] ?? null;
    
    if ($value === null) {
        return None::instance();
    }
    
    return Some::of($value);
}

// 3. 配列・連想配列からの安全な取得
function safeArrayGet(array $array, string $key): Option
{
    if (!array_key_exists($key, $array)) {
        return None::instance();
    }
    
    return Some::of($array[$key]);
}

// 4. 文字列操作の結果
function extractDomain(string $email): Option
{
    $parts = explode('@', $email);
    
    if (count($parts) !== 2) {
        return None::instance();
    }
    
    return Some::of($parts[1]);
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
    return $name === null ? None::instance() : Some::of($name);
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
            Ok::of($user) : 
            Err::of('管理者権限が必要です')
    );
```

#### チェーンを読みやすく保つ: 責務で分ける

PHPにはRustの `?` 演算子に相当する構文がないため、チェーンの `andThen` は
すべてコールバックの入れ子として書かれます。読む人は「現在の `Ok` の型・
次の型・クロージャのキャプチャ変数・エラー時の短絡・副作用の発生有無」を
同時に追跡する必要があります。つまりチェーンの長さは、前払いで払う可読性の
コストです。

チェーンを読みやすく保つルールはこれです。

> 1つのチェーン = 1つの責務。責務が変わるところで名前付きメソッドへ抽出する。

同じフロー（検証 → 存在確認 → 更新 → 監査）を2つの書き方で比べます。

```php
// ❌ 読みにくい: すべてのステップが同じに見える
public function add(Username $user, string $password): Result
{
    return $this->ensureHtpasswdAuth()
        ->andThen(fn() => $this->validatePassword($password))
        ->andThen(fn() => $this->readUsernames($path))
        ->andThen(fn(array $names) => $this->lock->capture($path)
            ->andThen(function (Fingerprint $fingerprint) use ($names, $user) {
                if (in_array($user->value, $names, true)) {
                    return Err::of(new UserAlreadyExists($user));
                }
                return Ok::of($fingerprint);
            }))
        ->andThen(fn(Fingerprint $f) => $this->lock->assertCurrent($path, $f))
        ->andThen(fn() => $this->snapshots()->capture($path, $now))
        ->andThen(fn(Snapshot $snapshot) => $this->executeAdd($path, $user, $password, $snapshot))
        ->andThen(fn() => $this->audit->record($actor, 'user.add', $path, 'ok'))
        ->orElse(fn(mixed $error) => $this->recordErrorAndReturn($actor, $error));
}

// ✅ 読みやすい: 公開メソッドは業務フローを一目で示す
public function add(Username $user, string $password): Result
{
    $path = $this->config->htpasswdPath;

    return $this->validateAddRequest($password)
        ->andThen(fn() => $this->prepareAdd($path, $user, $now))
        ->andThen(fn(Snapshot $snapshot) => $this->executeAdd($path, $user, $password, $snapshot))
        ->andThen(fn() => $this->recordAddSuccess($actor, $path, $user, $now))
        ->orElse(fn(mixed $error) => $this->recordErrorAndReturn($actor, $error));
}

private function validateAddRequest(string $password): Result
{
    return $this->ensureHtpasswdAuth()
        ->andThen(fn() => $this->validatePassword($password));
}

private function executeAdd(string $path, Username $user, string $password, Snapshot $snapshot): Result
{
    return $this->runHtpasswdAdd($path, $user, $password)
        ->andThen(fn() => $this->lock->capture($path))
        ->andThen(fn(Fingerprint $after) => $this->snapshots()->noteExpectedState($snapshot, $after));
}
```

実践的な指針:

- **1つのチェーンに1つの責務。** バリデーション・I/O・監査記録は別の責務です。
  1本のチェーンに積み重ねず、責務が変わる箇所でチェーンを分割します。
- **読者が楽なのは `andThen` 4段程度までが目安。** それ以上は、そのメソッドが
  複数の仕事をしているサインです。
- **`andThen` の中に `andThen` を入れ子にしない。** 入れ子にしたロジックは
  名前付きprivateメソッドへ抽出します。
- **内部実装は命令的に書いて良い。** 重要なのは境界です。本文は素直な `if` と
  早期returnで構いません（[Resultと命令的コードの併用](#resultと命令的コードの併用) を参照）。

 なお、`Result` は副作用の**原子性を保証しません**。後続ステップ（例: 監査記録）が
 失敗したときに、先行ステップ（例: ユーザー追加）がすでに確定していた場合、
 `Err` を返すと呼び出し側の再試行が「既に存在します」に当たります。再試行で
 回復できない構成にするか、副作用をトランザクション的に扱う設計をしてください。

### Resultと命令的コードの併用

このライブラリは、すべてをチェーンで表現することを強制しません。`Result` の
契約が重要なのはメソッドの**境界**（呼び出し側が受け取るもの）です。本文は、
自然に命令的になるシーケンスでは素直なPHPの方が読みやすくなります。

```php
/**
 * @return Result<null, UserAlreadyExists|InvalidPassword>
 *
 * @throws InfrastructureException
 */
public function add(Username $user, string $password, string $actor, int $now): Result
{
    $validation = $this->validatePassword($password);
    if ($validation->isErr()) {
        return $validation;
    }

    if ($this->userExists($path, $user)) {
        return Err::of(new UserAlreadyExists($user));
    }

    // インフラ障害は例外として脱出する
    $this->addUserAtomically($path, $user, $password);
    $this->audit->record($actor, 'user.add', $path, 'ok', $user->value, $now);

    return Ok::of(null);
}
```

これは現実的な中間地点です。

- **境界**: 呼び出し側がその失敗で分岐すべきなら `Result` を返す
- **本文**: それ以外は素直な `if` / 早期return / 例外で書く
- **変換点**: 分岐が意味を持つ境界で、想定内の失敗を捕捉して `Err` にする
  （[境界での変換](#境界での変換例外がresultになる地点) を参照）

チェーンは、短い変換処理や、テスト済みメソッドを数個合成する場面で真価を発揮します。
このライブラリの唯一の使い方ではありません。

## 📝 エラーメッセージの設計指針

### 1. 具体的で実行可能なメッセージ

```php
// ✅ 良い例
function validatePassword(string $password): Result
{
    if (strlen($password) < 8) {
        return Err::of("パスワードは8文字以上で入力してください（現在: " . strlen($password) . "文字）");
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        return Err::of("パスワードに大文字を1文字以上含めてください");
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        return Err::of("パスワードに数字を1文字以上含めてください");
    }
    
    return Ok::of($password);
}

// ❌ 悪い例
function validatePassword(string $password): Result
{
    if (!isValidPassword($password)) {
        return Err::of("無効なパスワード"); // 何が悪いのか不明
    }
    
    return Ok::of($password);
}
```

### 2. コンテキスト情報の提供

```php
// ✅ 良い例
function parseJson(string $json): Result
{
    $data = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return Err::of("JSONの解析に失敗しました: " . json_last_error_msg() . " (入力: " . substr($json, 0, 80) . ")");
    }
    
    return Ok::of($data);
}

// ❌ 悪い例
function parseJson(string $json): Result
{
    $data = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return Err::of("エラー"); // 情報不足
    }
    
    return Ok::of($data);
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
        return Err::of(ErrorMessages::validationFailed('email', '有効なメールアドレス形式'));
    }
    
    return Ok::of($email);
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
    return Ok::of($config); // 戻り値型が不明
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
                return Err::of("ユーザーが見つかりません: ID $id");
            }
            
            return Ok::of($user);
        } catch (PDOException $e) {
            return Err::of("データベースエラー: " . $e->getMessage());
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
            return Err::of("ユーザーは既に有効化されています");
        }
        
        if ($user['status'] === 'banned') {
            return Err::of("BANされたユーザーは有効化できません");
        }
        
        return Ok::of($user);
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
            return Ok::of($user);
        } catch (Exception $e) {
            return Err::of($e->getMessage());
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

### 2. 過度なチェーンとandThenの入れ子

```php
// ❌ 悪い例 - andThenの入れ子: 型とキャプチャを追跡できなくなる
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

// ✅ 良い例 - フラットなチェーン。ただし責務が1つである間だけ
function clearPipeline(array $data): Result
{
    return $this->validateStep($data)
        ->andThen(fn($d) => $this->enrichStep($d))
        ->andThen(fn($d) => $this->processStep($d))
        ->andThen(fn($d) => $this->saveStep($d))
        ->andThen(fn($d) => $this->notifyStep($d));
    // 5ステップは同じ責務（パイプライン処理）。
    // これが快適な限界に近い - あと1ステップ増えたら分割する
}

// ❌ 悪い例 - 1本のチェーンに複数の責務
function registerUser(array $data): Result
{
    return $this->validateInput($data)              // 検証
        ->andThen(fn($d) => $this->saveToDatabase($d))  // I/O
        ->andThen(fn() => $this->sendWelcomeMail())     // 通知
        ->andThen(fn() => $this->audit->record('registered'))
        ->andThen(fn() => $this->notifyAdmins());       // また通知
}

// ✅ 良い例 - 責務が変わるところで分割
function registerUser(array $data): Result
{
    return $this->validateAndCreate($data)
        ->andThen(fn(User $user) => $this->announce($user));
}

private function validateAndCreate(array $data): Result
{
    return $this->validateInput($data)
        ->andThen(fn($d) => $this->saveToDatabase($d));
}

private function announce(User $user): Result
{
    return $this->sendWelcomeMail($user)
        ->andThen(fn() => $this->audit->record('registered', $user));
}
```

### 3. 適切でないエラー粒度

```php
// ❌ 悪い例 - 粒度が粗すぎる
function validateUser(array $data): Result
{
    if (!$this->isValidUser($data)) {
        return Err::of("ユーザーデータが無効"); // 何が無効か不明
    }
    return Ok::of($data);
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