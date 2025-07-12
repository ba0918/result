# AI-Friendly PHP Coding Guidelines

## **1. はじめに**

このコーディング規約は、人間にとっての可読性・保守性を高めることはもちろん、AIによるコード解析、レビュー、リファクタリング、自動生成の精度を最大化することを目的とします。

AIはコードの「意図」を文脈から推測します。曖昧さがなく、構造が明確なコードは、AIの推測精度を飛躍的に向上させます。

## **2. 基本方針**

以下の3つを基本方針とします。

- **規律 (Discipline):** 標準規約 (PSR) に準拠し、一貫性を保つ。
- **不変性 (Immutability):** 副作用をなくし、データの流れを追いやすくする。
- **型安全性 (Type Safety):** mixedを撲滅し、厳密な型でコードの振る舞いを保証する。

## **3. 具体的な規約**

### 3.1. コーディングスタイル

- **ルール:** [PSR-12 (Extended Coding Style Guide)](https://www.php-fig.org/psr/psr-12/) に準拠します。
- **徹底:** 全てのPHPファイルの先頭に `<?php declare(strict_types=1);` を必ず記述します。
- **自動化:** `pint` や `php-cs-fixer` といったリンター/フォーマッターを導入し、スタイルを強制的に統一します。加えて、PHPStanやPsalmのような静的解析ツールを導入し、コードの潜在的な問題を早期に発見することを強く推奨します。

**AIにとってのメリット:** 標準化されたフォーマットと厳密な型モードは、AIがコードを静的解析する際のノイズを減らし、より正確な構文解析を可能にします。

### 3.2. 不変性 (Immutability)

状態が変化しないオブジェクトを基本とします。これにより、副作用の範囲が限定され、コードの振る舞いが予測しやすくなります。

- **ルール:**
  - クラスのプロパティは原則として `readonly` を使用します。
  - 状態を変更する必要がある場合は、`setter` でプロパティを直接変更するのではなく、新しいインスタンスを返す `with...` メソッドを実装します。
  - データ構造を表すクラス（DTO, Value Object）で特にこのルールを徹底します。

**AIにとってのメリット:** オブジェクトの状態がコンストラクト時か、`with...` メソッドでの再生成時にしか変わらないため、AIはデータの流れと状態変化を非常に追いやすくなります。「この変数はどこで変更されたか？」という追跡が不要になるため、リファクタリングやバグ解析の精度が向上します。

#### 悪い例 ❌ (Mutable: 可変)
```php
class User
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}

    // プロパティを直接変更してしまう
    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
```

#### 良い例 👍 (Immutable: 不変)
```php
final readonly class User
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}

    // 新しいインスタンスを返却する
    public function withName(string $name): self
    {
        return new self($this->id, $name);
    }
}
```

### 3.3. 型宣言 (Type Safety)

`mixed` 型は「何でもあり」を意味し、コードの振る舞いを不透明にします。これを徹底的に排除します。

- **ルール:**
  - 引数、戻り値、プロパティには可能な限り具体的な型を宣言します。
  - `mixed` 型の使用を原則禁止します。
  - `mixed` の代わりに、Union Types (`string|int`) や、具体的なデータ構造を持つDTO (Data Transfer Object) クラスを利用します。
  - 配列の中身が不定な場合は、PHPDocで `/** @var array<int, string> */` のようにジェネリクス形式で型を明記します。

**AIにとってのメリット:** `mixed` はAIにとって「ブラックボックス」です。型が明確であれば、AIはメソッドが何を期待し、何を返すのかを100%理解できます。これにより、型安全性を前提とした高度なコード補完や、ありえないコードパスの検出が可能になります。

#### 悪い例 ❌
```php
// 何を返し、何が配列に入っているか不明
function processRequest(array $request): mixed
{
    if (!$request['success']) {
        return false;
    }
    return $request['data']; // dataの中身は？
}
```

#### 良い例 👍
```php
final readonly class UserRequestDto { /* ... */ }
final readonly class UserResponseDto { /* ... */ }

// 例外で失敗を表現する
function createUser(UserRequestDto $request): UserResponseDto
{
    // ...
    // 失敗時は例外をスローする
    if (/* 条件 */) {
        throw new InvalidArgumentException('...');
    }
    
    // ...
    return new UserResponseDto(/* ... */);
}
```

### 3.4. クラス設計

クラスの責務を明確にし、意図しない使われ方を防ぎます。

- **ルール:**
  - クラスは原則として `final` を付け、継承をデフォルトで禁止します。継承が必要な場合にのみ `final` を外します。
  - 積極的にコンストラクタ・プロパティ・プロモーションを利用し、コードを簡潔に保ちます。
  - 一つのクラスには一つの責任だけを持たせる **単一責任の原則 (SRP)** を意識します。

**AIにとってのメリット:** final クラスは「これ以上変化しない」という強力なシグナルです。AIはメソッドのオーバーライドを考慮する必要がなくなり、クラスの使われ方を限定的に解釈できます。これにより、呼び出し階層の解析やリファクタリングが劇的に単純化されます。

#### 良い例 👍
```php
// 継承を禁止し、プロパティの初期化を簡潔に記述
final readonly class MailAddress
{
    public function __construct(
        public string $value,
    ) {
        if (!filter_var($this->value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email address.');
        }
    }
}
```

### 3.5. 継承よりコンポジション（委譲）を選択する

クラスの機能を拡張、再利用する際は、安易に `extends` を使わず、他のクラスのインスタンスをプロパティとして持ち（コンポジション）、その機能を利用（委譲）することを第一に検討します。

- **ルール:**
  - 「is-a（…は…の一種である）」の関係が明確に成り立つ場合を除き、継承は避けます。
  - 機能を利用したい場合は、「has-a（…は…を持つ）」の関係としてコンポジションで実装し、依存性注入 (DI) を利用します。

**AIにとってのメリット:** AIにとって、クラスの責務と依存関係がコンストラクタで明確になるため、コードの構造を極めて正確に把握できます。継承による暗黙的な副作用やメソッドの汚染がないため、リファクタリングやコード生成の精度が向上します。

#### 悪い例 ❌ (継承)
```php
// NotificationServiceがLoggerの一種になってしまっている
class NotificationService extends Monolog\Logger 
{
    public function notifyUser(int $userId, string $message): void
    {
        // 処理...
        $this->info("Notified user {$userId}");
    }
}
```
#### 良い例 👍 (コンポジション)
```php
use Psr\Log\LoggerInterface;

final class NotificationService
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {}

    public function notifyUser(int $userId, string $message): void
    {
        // ...
        // 委譲: ロギング処理をloggerオブジェクトに任せる
        $this->logger->info("Notified user {$userId}");
    }
}
```

### 3.6. Enum (列挙型) の活用

ステータスや種別など、決まった値のセットは Enum で定義します。

- **ルール:**
  - `const` で定義した文字列や数値（マジックナンバー）の代わりに、Backed Enum を使用します。

**AIにとってのメリット:** 文字列や数値はただの値ですが、`Enum` はそれ自体が型です。AIは「この変数には `PostStatus` 型しか入らない」と理解でき、不正な値が代入されるコードをコンパイル前に検出できます。許容される値のセットが明確になるため、コード補完も的確になります。

#### 悪い例 ❌
```php
class Post
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public string $status;
}

$post->status = 'draf'; // タイプミスに気づけない
```

#### 良い例 👍
```php
enum PostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}

final readonly class Post
{
    public function __construct(
        public string $title,
        public PostStatus $status,
    ) {}
}

$post = new Post('My first post', PostStatus::Draft);
// $post->status = 'draft'; // このような代入は型エラーとなり実行前に防げる
```

### 3.7. 意図を伝えるPHPDoc

コードは「どのように(How)」動くかを示しますが、コメントは「何を(What)」「なぜ(Why)」そうするのかを補足するためにあります。

- **ルール:**
  - 型情報だけでは伝わらないビジネスロジック、前提条件、処理の意図をPHPDocに記述します。
  - `@throws` タグを使い、メソッドが送出する可能性のある例外を明記します。

**AIにとってのメリット:** コードの背後にある「文脈」や「制約」をAIに教えることで、よりビジネスロジックに即したコード生成や、潜在的なバグの指摘が可能になります。

#### 良い例 👍
```php
/**
 * ユーザーの最終ログイン日時を更新し、セッショントークンを無効化する。
 * このメソッドはユーザー認証が成功した直後に呼び出すことを想定している。
 *
 * @param UserId $userId 対象のユーザーID
 * @return void
 * @throws UserIsDeactivatedException 退会処理中のユーザーに対して実行された場合
 */
public function recordLogin(UserId $userId): void
{
    // ...
}
```
### 3.8. 役割を明確にする命名

変数名やメソッド名は、その役割や状態を明確に表現する必要があります。

- **ルール:**
  - 曖昧な名前（例: `$data`, `$list`, `handle()`）を避け、具体的な名前を付けます。
  - 状態を表す変数は、その状態がわかるような接頭辞/接尾辞（例: `is...`, `...At`）を検討します。

**AIにとってのメリット:** 具体的な命名は、AIがコードの各部分の役割を正確に理解するための最も直接的な手がかりです。これにより、変数間の関係性の推測や、メソッドの利用方法の提案精度が向上します。

- **変数名の例:**
  - 悪い例: `$user`, `$flag`, `$tmp`
  - 良い例: `$userToUpdate`, `isAdministrator`, `$rawCsvData`

- **メソッド名の例:**
  - 悪い例: `check()`, `process()`, `get()`
  - 良い例: `hasActiveSubscription()`, `activateUserAccount()`, `getUserById()`

### 3.9. 具体的な例外クラスの利用

エラーハンドリングにおいて、失敗の理由が明確に伝わる具体的な例外クラスを利用します。

- **ルール:**
  - `\Exception` や `\RuntimeException` のような汎用的な例外を直接 `throw` するのではなく、アプリケーション固有の例外クラスを定義して利用します。

**AIにとってのメリット:** `try-catch`ブロックで補足される例外が具体的であるほど、AIはエラー発生時の処理フローを正確に追跡できます。「ユーザーが見つからない」という状況と「データベース接続に失敗した」という状況を区別して学習できるため、より的確なエラーハンドリングのコードを生成できます。

#### 悪い例 ❌
```php
public function findUser(UserId $userId): User
{
    $user = // ... DB検索
    if ($user === null) {
        throw new \RuntimeException('User not found.');
    }
    return $user;
}
```

#### 良い例 👍
```php
// アプリケーション固有の例外を定義
class UserNotFoundException extends \RuntimeException {}

public function findUser(UserId $userId): User
{
    $user = // ... DB検索
    if ($user === null) {
        throw new UserNotFoundException("User with ID {$userId->value} not found.");
    }
    return $user;
}

// 呼び出し元
try {
    $user = $userService->findUser($userId);
} catch (UserNotFoundException $e) {
    // ユーザーが見つからなかった場合の専用処理
}
```

### 3.10. テストコードの規約

テストコードはアプリケーションの品質を担保するだけでなく、コードの振る舞いを記述する「生きた仕様書」としての役割も担います。

- **ルール:**
  - テストフレームワーク（例: PHPUnit）の利用を標準とします。
  - テストクラス名は、テスト対象クラスに `Test` を付けた名前にします（例: `UserService` -> `UserServiceTest`）。
  - テストメソッド名は、`@test` アノテーションを付与し、テスト内容が具体的にわかる名前にします（例: `it_throws_exception_if_user_is_not_found`）。

**AIにとってのメリット:** 整然としたテストコードは、クラスやメソッドの正しい使い方、期待される振る舞い、エッジケースをAIに教えるための最良の教材です。これにより、AIは既存コードの利用方法を正確に学習し、より安全なリファクタリングや機能追加を提案できます。

#### 良い例 👍
```php
use PHPUnit\Framework\TestCase;

class UserServiceTest extends TestCase
{
    /** @test */
    public function it_returns_user_if_user_exists(): void
    {
        // 準備 (Arrange)
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('find')->willReturn(new User(1, 'test user'));
        $userService = new UserService($userRepository);

        // 実行 (Act)
        $result = $userService->findUser(new UserId(1));

        // 検証 (Assert)
        $this->assertInstanceOf(User::class, $result);
    }

    /** @test */
    public function it_throws_exception_if_user_is_not_found(): void
    {
        // 期待する例外を定義
        $this->expectException(UserNotFoundException::class);

        // 準備 (Arrange)
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('find')->willReturn(null);
        $userService = new UserService($userRepository);

        // 実行 (Act)
        $userService->findUser(new UserId(1));
    }
}
```

### 3.11. 設計原則の遵守

本規約の多くのルールは、実績のあるソフトウェア設計原則に基づいています。これらの原則を意識することで、より一貫性があり、堅牢で、AIが理解しやすいコードを作成できます。

- **SOLID原則:**
  - **S (単一責任の原則):** クラスは一つの責任のみを持つべき（規約3.4参照）。
  - **O (オープン/クローズドの原則):** 拡張に対して開かれ、修正に対して閉じているべき。インターフェースへの依存やコンポジションの活用で実現します。
  - **L (リスコフの置換原則):** サブクラスは親クラスと置換可能であるべき。これは継承を慎重に扱うべき理由の一つです（規約3.5参照）。
  - **I (インターフェース分離の原則):** 必要最小限の責務を持つ小さなインターフェースに分割すべき。
  - **D (依存性逆転の原則):** 具象クラスではなく、抽象（インターフェース）に依存すべき（規約3.5の例を参照）。

- **DRY (Don't Repeat Yourself) / OAOO (Once and Only Once):**
  - 同じ知識（ロジック）の重複を避けます。重複したコードは、修正漏れによるバグの温床となり、AIの分析を複雑にします。ロジックは一箇所に集約し、それを再利用します。

- **YAGNI (You Ain't Gonna Need It):**
  - 「いつか必要になるだろう」という予測で機能を実装しません。現時点で不要なコードは、AIが分析すべきノイズを増やし、コードベースを不必要に複雑化させます。本当に必要になった時点で実装します。

**AIにとってのメリット:** これらの原則に従ったコードは、責務が明確で、依存関係が疎結合であり、冗長性が排除されています。結果として、AIは各コンポーネントの役割と関係性を正確に把握し、より質の高い分析やコード生成を行うことができます。
