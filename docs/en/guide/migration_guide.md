# Migration Guide from Legacy Code

Practical migration strategies and best practices for gradually introducing Result and Option types into existing projects.

## 🎯 What You'll Learn from This Guide

- Gradual migration strategies and implementation plans
- Specific migration procedures (null → Option, exceptions → Result)
- Mixed patterns with existing code
- Migration pitfalls and troubleshooting
- Team migration process management

## 📋 Prerequisites

- PHP 8.3+ environment
- Package management with Composer
- Existing project codebase

## 🚀 Gradual Migration Strategy

### Phase 1: Foundation Preparation (1-2 weeks)

#### 1.1 Library Installation

```bash
# 1. Install the library
composer require ba0918/result

# 2. Verify autoloader
composer dump-autoload
```

#### 1.2 Team Education

```php
// Team learning with sample code
<?php
require_once 'vendor/autoload.php';

use ba0918\Result\{Ok, Err, Some, None};

// Basic usage examples for team learning
function learningExample(): void
{
    // Result type basics
    $result = Ok::of(42);
    echo $result->unwrap(); // 42
    
    // Option type basics
    $option = Some::of("Hello");
    echo $option->unwrapOr("Default"); // Hello
}
```

#### 1.3 Migration Target Selection

```php
// Identify functions suitable for migration
class MigrationCandidate
{
    // ✅ Migration candidate: functions returning null
    public function findUser(int $id): ?array { /* ... */ }
    
    // ✅ Migration candidate: functions throwing exceptions
    public function parseConfig(string $json): array { /* ... */ }
    
    // ❌ No migration needed: simple calculations
    public function add(int $a, int $b): int { /* ... */ }
}
```

### Phase 2: Introduction in New Features (2-4 weeks)

#### 2.1 Implement new features with Result/Option types

```php
// Implement new features with Result/Option types from the start
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

#### 2.2 Create utility functions

```php
// Utilities to support migration
class ResultHelper
{
    /**
     * Convert traditional exception handling to Result type
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
     * Convert nullable values to Option type
     */
    public static function fromNullable($value): Option
    {
        return $value === null ? None::instance() : Some::of($value);
    }
}

// Usage examples
$result = ResultHelper::tryCall(fn() => json_decode($json, true, 512, JSON_THROW_ON_ERROR));
$option = ResultHelper::fromNullable($_GET['user_id'] ?? null);
```

### Phase 3: Gradual Migration of Existing Features (4-8 weeks)

#### 3.1 Migration using wrapper functions

```php
// Stage 1: Wrap existing functions
class UserServiceLegacy
{
    // Existing method (unchanged)
    public function findUser(int $id): ?array
    {
        // Existing implementation...
        return $this->database->find($id);
    }
    
    // New method (Option type)
    public function findUserSafe(int $id): Option
    {
        $user = $this->findUser($id);
        return $user === null ? None::instance() : Some::of($user);
    }
}

// Stage 2: Migrate to new methods
class UserServiceModern
{
    // Change main method to Option type
    public function findUser(int $id): Option
    {
        $user = $this->database->find($id);
        return $user === null ? None::instance() : Some::of($user);
    }
    
    // Legacy method for backward compatibility
    public function findUserLegacy(int $id): ?array
    {
        return $this->findUser($id)->unwrapOr(null);
    }
}
```

#### 3.2 Migration from exceptions to Result type

```php
// Before: Exception-based implementation
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

// After: Result type implementation
// Only the failures the caller branches on move to Result.
// File absence and read failures are infrastructure failures - they stay
// exceptions. Invalid JSON is a format problem the caller may want to handle.
class ConfigServiceModern
{
    /**
     * @throws RuntimeException  Config file not found / read failure
     * @return Result<array, string>  Invalid JSON becomes Err
     */
    public function loadConfig(string $path): Result
    {
        if (!file_exists($path)) {
            throw new RuntimeException("Config file not found: $path");
        }
        
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException("Failed to read config file: $path");
        }
        
        $config = json_decode($content, true);
        if (!is_array($config)) {
            return Err::of('Configuration file must contain a JSON object or array: ' . $path);
        }
        
        return Ok::of($config);
    }
    
    // Maintain backward compatibility
    public function loadConfigLegacy(string $path): array
    {
        return $this->loadConfig($path)
            ->unwrapOrElse(fn($error) => throw new RuntimeException($error));
    }
}
```

### Phase 4: Complete Migration (2-4 weeks)

#### 4.1 Remove legacy methods

```php
// Final stage: Unify to Result/Option types
class UserServiceFinal
{
    public function findUser(int $id): Option { /* ... */ }
    public function createUser(array $data): Result { /* ... */ }
    public function updateUser(int $id, array $data): Result { /* ... */ }
    public function deleteUser(int $id): Result { /* ... */ }
}
```

## 🔄 Specific Migration Patterns

### Pattern 1: null → Option

#### Before: Nullable return values

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

// Null checks on usage side
$user = $repository->findByEmail('test@example.com');
if ($user !== null) {
    echo "User found: " . $user['name'];
} else {
    echo "User not found";
}
```

#### After: Option type

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
        // Profile data retrieval implementation
        $profile = null; // replace with the actual database fetch
        return $profile ? Some::of($profile) : None::instance();
    }
}

// Option processing on usage side
$message = $repository->findByEmail('test@example.com')
    ->map(fn($user) => "User found: " . $user['name'])
    ->unwrapOr("User not found");

echo $message;
```

### Pattern 2: Exceptions → Result

#### Before: Exception-based error handling

```php
class PaymentService
{
    public function processPayment(array $paymentData): array
    {
        // Validation
        if (empty($paymentData['amount'])) {
            throw new InvalidArgumentException('Amount is required');
        }
        
        if ($paymentData['amount'] <= 0) {
            throw new InvalidArgumentException('Amount must be positive');
        }
        
        // External API call
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

// Exception handling on usage side
try {
    $result = $paymentService->processPayment($paymentData);
    echo "Payment successful: " . $result['transaction_id'];
} catch (Exception $e) {
    echo "Payment failed: " . $e->getMessage();
}
```

#### After: Result type

```php
class PaymentService
{
    /**
     * @throws RuntimeException  Payment API unreachable / server outage
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
            return Err::of('Payment amount not specified');
        }
        
        if ($data['amount'] <= 0) {
            return Err::of('Payment amount must be a positive value');
        }
        
        return Ok::of($data);
    }
    
    /**
     * Network failures stay exceptions - the caller has no meaningful
     * branch for "API unreachable". An HTTP error response, on the other
     * hand, is a business outcome the caller branches on, so it becomes Err.
     *
     * @throws RuntimeException  Payment API unreachable
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
            throw new RuntimeException('Payment API unreachable');
        }
        
        if ($httpCode >= 500) {
            // Server outages are infrastructure failures - the caller needs
            // retry or outage handling, not a business decision
            throw new RuntimeException("Payment API server error (HTTP status: $httpCode)");
        }
        
        if ($httpCode >= 400) {
            return Err::of("Payment declined (HTTP status: $httpCode)");
        }
        
        return Ok::of($response);
    }
    
    private function parseResponse(string $response): Result
    {
        $result = json_decode($response, true);
        if (!is_array($result)) {
            return Err::of('Invalid payment API response format');
        }
        
        return Ok::of($result);
    }
}

// Result processing on usage side
$message = $paymentService->processPayment($paymentData)
    ->map(fn($result) => "Payment successful: " . $result['transaction_id'])
    ->unwrapOr("Payment failed");

echo $message;
```

### Pattern 3: Gradual migration with mixed patterns

```php
// Service class during gradual migration
class MixedUserService
{
    // New methods (Result/Option types)
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
    
    // Existing methods (legacy) - scheduled for gradual removal
    public function createUser(array $userData): array
    {
        return $this->createUserSafe($userData)
            ->unwrapOrElse(fn($error) => throw new RuntimeException($error));
    }
    
    public function findUserLegacy(int $id): ?array
    {
        // Keep existing implementation as is
        return $this->database->find($id);
    }
    
    // Bridge methods - connecting legacy and new implementations
    public function getUserDisplayName(int $id): string
    {
        return $this->findUserSafe($id)
            ->map(fn($user) => $user['name'])
            ->unwrapOr('Unknown User');
    }
}
```

## 🔧 Mixed Patterns with Existing Code

### Pattern 1: Wrapper classes

```php
// Wrap existing libraries
// Exceptions are converted to Result at this boundary because the caller
// wants to branch on these failures (e.g. fall back to a default config).
// A failure the caller has no branch for should stay an exception instead.
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
            return Err::of("File not found: $path");
        } catch (IOException $e) {
            return Err::of("File read error: " . $e->getMessage());
        }
    }
    
    public function findFile(string $pattern): Option
    {
        $files = $this->fileOps->glob($pattern);
        return empty($files) ? None::instance() : Some::of($files[0]);
    }
}
```

### Pattern 2: Adapter pattern

```php
// Adapt existing API client to Result type
class ResultApiClient
{
    private LegacyApiClient $client;
    
    public function get(string $endpoint): Result
    {
        try {
            $response = $this->client->get($endpoint);
            
            if ($response->getStatusCode() >= 400) {
                return Err::of("API error: " . $response->getStatusCode());
            }
            
            return Ok::of($response->getBody());
        } catch (ApiException $e) {
            return Err::of("API call error: " . $e->getMessage());
        }
    }
}
```

### Pattern 3: Gradual interface migration

```php
// Stage 1: Existing interface
interface UserRepositoryLegacy
{
    public function findById(int $id): ?array;
    public function save(array $user): array;
}

// Stage 2: Add new interface
interface UserRepositoryModern
{
    public function findById(int $id): Option;
    public function save(array $user): Result;
}

// Stage 3: Transition class implementing both
class UserRepositoryTransition implements UserRepositoryLegacy, UserRepositoryModern
{
    // New implementation
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
            return Err::of("Save error: " . $e->getMessage());
        }
    }
    
    // Legacy compatibility methods
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

## ⚠️ Migration Pitfalls

### 1. Performance impact

```php
// ❌ Bad example: Unnecessary object creation
function inefficientConversion($value): Option
{
    return Some::of($value); // Always creates Some
}

// ✅ Good example: Proper judgment
function efficientConversion($value): Option
{
    return $value === null ? None::instance() : Some::of($value);
}

// ✅ Utilize caching
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

### 2. Preventing memory leaks

```php
// ❌ Bad example: Circular reference
class BadService
{
    private Result $result;
    
    public function process(): Result
    {
        $this->result = Ok::of($this); // Circular reference
        return $this->result;
    }
}

// ✅ Good example: Proper design
class GoodService
{
    public function process(array $data): Result
    {
        return Ok::of($this->processData($data)); // Return only data
    }
}
```

### 3. Testing considerations

```php
// Testing Result types
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
        $this->assertStringContains('positive value', $result->unwrapErr());
    }
}

// Testing Option types
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

## 🐛 Troubleshooting

### Issue 1: Exceptions with unwrap()

```php
// Problematic code
$user = findUser($id)->unwrap(); // Possibility of UnwrapException

// Solution 1: Use unwrapOr()
$user = findUser($id)->unwrapOr(['name' => 'Guest']);

// Solution 2: Pattern matching
$message = findUser($id)
    ->map(fn($user) => "Hello, {$user['name']}")
    ->unwrapOr("User not found");
```

### Issue 2: Type inconsistency

```php
// Problematic code
function process(): Result
{
    $value = getValue();
    return $value; // Returning non-Result value
}

// Solution: Proper type conversion
function process(): Result
{
    $value = getValue();
    
    if ($value === null) {
        return Err::of("Could not retrieve value");
    }
    
    return Ok::of($value);
}
```

### Issue 3: Inconsistent error messages

```php
// Problematic code
function validate($data): Result
{
    if (!isValid($data)) {
        return Err::of("Invalid"); // Insufficient information
    }
    return Ok::of($data);
}

// Solution: Specific error messages
function validate($data): Result
{
    if (empty($data['email'])) {
        return Err::of("Email address is not entered");
    }
    
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        return Err::of("Email address format is incorrect");
    }
    
    return Ok::of($data);
}
```

## 📊 Migration Progress Management

### Migration checklist

```php
// Checklist for tracking migration progress
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

### Automated migration verification

```bash
# Test suite for migration verification
composer exec phpunit tests/Migration/

# Check migration consistency with type checking
composer exec phpstan analyse src/ --level=max
```

## 🎯 Summary

### Success Points

1. **Gradual approach**: Don't change everything at once
2. **Team education**: Deepen understanding of the library beforehand
3. **Backward compatibility**: Keep existing code working during migration
4. **Continuous testing**: Conduct thorough testing at each stage
5. **Documentation**: Clearly record migration reasons and methods

### Pitfalls to Avoid

- Unplanned bulk migration
- Excessive use of unwrap()
- Neglecting error handling
- Indifference to performance
- Lack of knowledge sharing within the team

---

💡 **Practical Tip**: Perform migration gradually and confirm results at each phase before proceeding to the next. Focus on continuous improvement rather than perfect migration.