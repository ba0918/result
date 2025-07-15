# AI-Friendly PHP Coding Guidelines

## **1. Introduction**

These coding guidelines aim to maximize not only human readability and maintainability but also the accuracy of AI code analysis, review, refactoring, and automatic generation.

AI infers code "intent" from context. Code that is unambiguous and has clear structure dramatically improves AI inference accuracy.

## **2. Basic Policies**

We establish the following three basic policies:

- **Discipline:** Adhere to standard conventions (PSR) and maintain consistency.
- **Immutability:** Eliminate side effects and make data flow traceable.
- **Type Safety:** Eliminate mixed types and guarantee code behavior through strict typing.

## **3. Specific Conventions**

### 3.1. Coding Style

- **Rule:** Comply with [PSR-12 (Extended Coding Style Guide)](https://www.php-fig.org/psr/psr-12/).
- **Enforcement:** Always write `<?php declare(strict_types=1);` at the beginning of all PHP files.
- **Automation:** PHP-CS-Fixer is introduced to enforce style unification. Additionally, PHPStan static analysis is also implemented.

#### Code Formatting Commands

```bash
# Check code formatting (no changes)
composer cs-check

# Auto-fix code formatting
composer cs-fix

# Run all quality checks (cs-check → phpstan → test)
composer check
```

**Benefits for AI:** Standardized formatting and strict typing mode reduce noise when AI performs static code analysis, enabling more accurate syntax parsing.

### 3.2. Immutability

Use objects whose state does not change as the foundation. This limits the scope of side effects and makes code behavior predictable.

- **Rules:**
  - Class properties should primarily use `readonly`.
  - When state modification is necessary, implement `with...` methods that return new instances instead of directly modifying properties with `setters`.
  - Particularly enforce this rule for classes representing data structures (DTO, Value Object).

**Benefits for AI:** Since object state only changes during construction or regeneration with `with...` methods, AI can easily track data flow and state changes. Tracking "where was this variable modified?" becomes unnecessary, improving the accuracy of refactoring and bug analysis.

#### Bad Example ❌ (Mutable)
```php
class User
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}

    // Directly modifies properties
    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
```

#### Good Example 👍 (Immutable)
```php
final readonly class User
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}

    // Returns new instance
    public function withName(string $name): self
    {
        return new self($this->id, $name);
    }
}
```

### 3.3. Type Declarations (Type Safety)

The `mixed` type means "anything goes" and makes code behavior opaque. We thoroughly eliminate this.

- **Rules:**
  - Declare specific types for arguments, return values, and properties whenever possible.
  - Prohibit the use of `mixed` type in principle.
  - Instead of `mixed`, use Union Types (`string|int`) or DTO (Data Transfer Object) classes with specific data structures.
  - For arrays with indefinite contents, explicitly specify types in PHPDoc using generics format like `/** @var array<int, string> */`.

**Benefits for AI:** `mixed` is a "black box" for AI. When types are clear, AI can understand 100% what methods expect and what they return. This enables advanced code completion based on type safety and detection of impossible code paths.

#### Bad Example ❌
```php
// Unclear what is returned and what's in the array
function processRequest(array $request): mixed
{
    if (!$request['success']) {
        return false;
    }
    return $request['data']; // What's in data?
}
```

#### Good Example 👍
```php
final readonly class UserRequestDto { /* ... */ }
final readonly class UserResponseDto { /* ... */ }

// Express failure with exceptions
function createUser(UserRequestDto $request): UserResponseDto
{
    // ...
    // Throw exception on failure
    if (/* condition */) {
        throw new InvalidArgumentException('...');
    }
    
    // ...
    return new UserResponseDto(/* ... */);
}
```

### 3.4. Class Design

Clarify class responsibilities and prevent unintended usage.

- **Rules:**
  - Classes should be `final` by default, prohibiting inheritance. Remove `final` only when inheritance is necessary.
  - Actively use constructor property promotion to keep code concise.
  - Be conscious of the **Single Responsibility Principle (SRP)** - give each class only one responsibility.

**Benefits for AI:** Final classes are a strong signal of "no further changes." AI no longer needs to consider method overriding and can interpret class usage restrictively. This dramatically simplifies call hierarchy analysis and refactoring.

#### Good Example 👍
```php
// Prohibit inheritance and concisely describe property initialization
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

### 3.5. Choose Composition over Inheritance

When extending or reusing class functionality, don't easily use `extends`. Instead, primarily consider holding instances of other classes as properties (composition) and using their functionality (delegation).

- **Rules:**
  - Avoid inheritance except when "is-a (... is a type of ...)" relationships clearly hold.
  - When you want to use functionality, implement it as composition with "has-a (... has a ...)" relationships and use dependency injection (DI).

**Benefits for AI:** For AI, class responsibilities and dependencies become clear in the constructor, enabling extremely accurate code structure comprehension. Without implicit side effects or method pollution from inheritance, refactoring and code generation accuracy improves.

#### Bad Example ❌ (Inheritance)
```php
// NotificationService becomes a type of Logger
class NotificationService extends Monolog\Logger 
{
    public function notifyUser(int $userId, string $message): void
    {
        // Processing...
        $this->info("Notified user {$userId}");
    }
}
```
#### Good Example 👍 (Composition)
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
        // Delegation: delegate logging processing to logger object
        $this->logger->info("Notified user {$userId}");
    }
}
```

### 3.6. Enum Utilization

Define statuses and types as fixed value sets using Enum.

- **Rules:**
  - Use Backed Enum instead of strings or numbers (magic numbers) defined with `const`.

**Benefits for AI:** Strings and numbers are just values, but `Enum` is a type itself. AI can understand "this variable can only contain `PostStatus` type" and can detect code with invalid value assignments before compilation. The set of allowable values becomes clear, making code completion more accurate.

#### Bad Example ❌
```php
class Post
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public string $status;
}

$post->status = 'draf'; // Can't notice typo
```

#### Good Example 👍
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
// $post->status = 'draft'; // Such assignment becomes type error and is prevented before execution
```

### 3.7. Intentional PHPDoc

Code shows "how" it works, but comments supplement "what" and "why" it does so.

- **Rules:**
  - Describe business logic, preconditions, and processing intent that cannot be conveyed through type information alone in PHPDoc.
  - Use `@throws` tags to specify exceptions that methods might throw.

**Benefits for AI:** By teaching AI the "context" and "constraints" behind code, more business logic-appropriate code generation and potential bug identification becomes possible.

#### Good Example 👍
```php
/**
 * Updates user's last login time and invalidates session tokens.
 * This method is intended to be called immediately after successful user authentication.
 *
 * @param UserId $userId Target user ID
 * @return void
 * @throws UserIsDeactivatedException When executed on users undergoing withdrawal processing
 */
public function recordLogin(UserId $userId): void
{
    // ...
}
```
### 3.8. Clear Role-Defining Naming

Variable and method names must clearly express their role or state.

- **Rules:**
  - Avoid ambiguous names (e.g., `$data`, `$list`, `handle()`) and use specific names.
  - For variables representing state, consider prefixes/suffixes that indicate the state (e.g., `is...`, `...At`).

**Benefits for AI:** Specific naming is the most direct clue for AI to accurately understand the role of each code part. This improves the accuracy of inferring relationships between variables and suggestions for method usage.

- **Variable name examples:**
  - Bad examples: `$user`, `$flag`, `$tmp`
  - Good examples: `$userToUpdate`, `isAdministrator`, `$rawCsvData`

- **Method name examples:**
  - Bad examples: `check()`, `process()`, `get()`
  - Good examples: `hasActiveSubscription()`, `activateUserAccount()`, `getUserById()`

### 3.9. Specific Exception Class Usage

In error handling, use specific exception classes that clearly convey the reason for failure.

- **Rules:**
  - Instead of directly `throw`ing generic exceptions like `\Exception` or `\RuntimeException`, define and use application-specific exception classes.

**Benefits for AI:** The more specific the exceptions caught in `try-catch` blocks, the more accurately AI can track processing flow during errors. Being able to distinguish between "user not found" and "database connection failed" situations enables learning and generation of more appropriate error handling code.

#### Bad Example ❌
```php
public function findUser(UserId $userId): User
{
    $user = // ... DB search
    if ($user === null) {
        throw new \RuntimeException('User not found.');
    }
    return $user;
}
```

#### Good Example 👍
```php
// Define application-specific exception
class UserNotFoundException extends \RuntimeException {}

public function findUser(UserId $userId): User
{
    $user = // ... DB search
    if ($user === null) {
        throw new UserNotFoundException("User with ID {$userId->value} not found.");
    }
    return $user;
}

// Caller
try {
    $user = $userService->findUser($userId);
} catch (UserNotFoundException $e) {
    // Dedicated processing for when user is not found
}
```

### 3.10. Test Code Conventions

Test code not only ensures application quality but also serves as "living documentation" describing code behavior.

- **Rules:**
  - Use of test frameworks (e.g., PHPUnit) is standard.
  - Test class names should be the test target class name with `Test` appended (e.g., `UserService` -> `UserServiceTest`).
  - Test method names should have `@test` annotation and names that specifically indicate test content (e.g., `it_throws_exception_if_user_is_not_found`).

**Benefits for AI:** Well-organized test code is the best teaching material for AI to learn correct class and method usage, expected behavior, and edge cases. This enables AI to learn existing code usage patterns accurately and suggest safer refactoring and feature additions.

#### Good Example 👍
```php
use PHPUnit\Framework\TestCase;

class UserServiceTest extends TestCase
{
    /** @test */
    public function it_returns_user_if_user_exists(): void
    {
        // Arrange
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('find')->willReturn(new User(1, 'test user'));
        $userService = new UserService($userRepository);

        // Act
        $result = $userService->findUser(new UserId(1));

        // Assert
        $this->assertInstanceOf(User::class, $result);
    }

    /** @test */
    public function it_throws_exception_if_user_is_not_found(): void
    {
        // Define expected exception
        $this->expectException(UserNotFoundException::class);

        // Arrange
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('find')->willReturn(null);
        $userService = new UserService($userRepository);

        // Act
        $userService->findUser(new UserId(1));
    }
}
```

### 3.11. Design Principle Adherence

Many rules in these guidelines are based on proven software design principles. Being conscious of these principles enables creating more consistent, robust, and AI-understandable code.

- **SOLID Principles:**
  - **S (Single Responsibility Principle):** Classes should have only one responsibility (see convention 3.4).
  - **O (Open/Closed Principle):** Should be open for extension, closed for modification. Realized through interface dependency and composition utilization.
  - **L (Liskov Substitution Principle):** Subclasses should be substitutable for parent classes. This is one reason to handle inheritance carefully (see convention 3.5).
  - **I (Interface Segregation Principle):** Should be divided into small interfaces with minimal necessary responsibilities.
  - **D (Dependency Inversion Principle):** Should depend on abstractions (interfaces) rather than concrete classes (see example in convention 3.5).

- **DRY (Don't Repeat Yourself) / OAOO (Once and Only Once):**
  - Avoid duplication of the same knowledge (logic). Duplicate code becomes a breeding ground for bugs due to missed modifications and complicates AI analysis. Consolidate logic in one place and reuse it.

- **YAGNI (You Ain't Gonna Need It):**
  - Don't implement features based on predictions of "might be needed someday." Currently unnecessary code increases noise that AI must analyze and unnecessarily complicates the codebase. Implement when truly needed.

**Benefits for AI:** Code following these principles has clear responsibilities, loosely coupled dependencies, and eliminated redundancy. As a result, AI can accurately grasp the role and relationships of each component and perform higher quality analysis and code generation.