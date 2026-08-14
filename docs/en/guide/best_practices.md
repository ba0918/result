# Best Practices Guide

Practical guidelines and operational best practices for effectively utilizing Result and Option types in your projects.

## 🎯 What You'll Learn from This Guide

- Decision criteria for appropriate usage scenarios
- Guidelines for method selection and usage patterns
- Error message design principles
- Team development operational methods
- Integration strategies for real projects

## 🔍 Decision Criteria for Appropriate Usage

### When to Use Result Type

#### ✅ Recommended Scenarios

```php
// 1. File operations
function readConfigFile(string $path): Result
{
    if (!file_exists($path)) {
        return Err::of("File does not exist: $path");
    }
    
    $content = file_get_contents($path);
    if ($content === false) {
        return Err::of("Failed to read file");
    }
    
    return Ok::of($content);
}

// 2. External API calls
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
        return Err::of("API call error: $error");
    }
    
    curl_close($ch);
    
    if ($httpCode >= 400) {
        return Err::of("HTTP error: $httpCode");
    }
    
    return Ok::of($response);
}

// 3. Validation processing
function validateEmail(string $email): Result
{
    if (empty($email)) {
        return Err::of("Email address is empty");
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return Err::of("Email address format is invalid");
    }
    
    return Ok::of($email);
}

// 4. Data transformation processing
function parseJSON(string $json): Result
{
    $data = json_decode($json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return Err::of("JSON parsing error: " . json_last_error_msg());
    }
    
    return Ok::of($data);
}
```

#### ❌ Not Recommended Scenarios

```php
// Simple calculations (exceptions are more appropriate)
function add(int $a, int $b): Result
{
    return Ok::of($a + $b); // This is unnecessary
}

// Program errors (exceptions are more appropriate)
function getConfig(): Result
{
    if (!class_exists('Config')) {
        return Err::of("Config class does not exist"); // This should be an exception
    }
    return Ok::of(new Config());
}
```

### When to Use Option Type

#### ✅ Recommended Scenarios

```php
// 1. Database queries
function findUserById(int $id): Option
{
    $user = $this->database->selectOne('users', ['id' => $id]);
    
    if ($user === null) {
        return None::instance();
    }
    
    return Some::of($user);
}

// 2. Configuration value retrieval
function getConfigValue(string $key): Option
{
    $value = $_ENV[$key] ?? null;
    
    if ($value === null) {
        return None::instance();
    }
    
    return Some::of($value);
}

// 3. Safe retrieval from arrays/associative arrays
function safeArrayGet(array $array, string $key): Option
{
    if (!array_key_exists($key, $array)) {
        return None::instance();
    }
    
    return Some::of($array[$key]);
}

// 4. String operation results
function extractDomain(string $email): Option
{
    $parts = explode('@', $email);
    
    if (count($parts) !== 2) {
        return None::instance();
    }
    
    return Some::of($parts[1]);
}
```

#### ❌ Not Recommended Scenarios

```php
// Required values (exceptions are more appropriate)
function getCurrentUser(): Option
{
    // Not being logged in is an error state
    return None::instance(); // This should be an exception
}

// Simple null checks (conventional methods are sufficient)
function getName(?string $name): Option
{
    return $name === null ? None::instance() : Some::of($name);
    // This case is too simple
}
```

## 🛠️ Method Selection Guidelines

### unwrap() vs unwrapOr() vs expect()

#### unwrap() - Only for guaranteed success cases

```php
// ✅ Good example - validated values
function processValidatedData(array $data): string
{
    $result = validateRequired($data)           // Already validated
        ->andThen(fn($d) => enrichData($d))     // Guaranteed success
        ->map(fn($d) => formatData($d));        // Formatting
    
    return $result->unwrap(); // Safe because validated
}

// ❌ Bad example - possible failure
function processUserInput(string $input): string
{
    $result = parseUserInput($input);
    return $result->unwrap(); // Dangerous! Possible exception
}
```

#### unwrapOr() - When default values are available

```php
// ✅ Good example - configuration value retrieval
function getMaxRetries(): int
{
    return getConfigValue('max_retries')
        ->map(fn($value) => (int)$value)
        ->unwrapOr(3); // Default value
}

// ✅ Good example - user display name
function getDisplayName(int $userId): string
{
    return findUser($userId)
        ->map(fn($user) => $user['name'])
        ->unwrapOr('Guest User'); // Default display
}

// ❌ Bad example - inappropriate default value
function getPassword(): string
{
    return getConfigValue('password')
        ->unwrapOr('default123'); // Security risk
}
```

#### expect() - When clear error messages are needed

```php
// ✅ Good example - with debug information
function loadCriticalConfig(): array
{
    return readConfigFile('app.conf')
        ->andThen(fn($content) => parseJSON($content))
        ->expect('Failed to load application configuration');
}

// ✅ Good example - development debugging
function developmentHelper(string $data): ProcessedData
{
    return parseDebugData($data)
        ->expect("Failed to parse debug data: $data");
}

// ❌ Bad example - production environment usage
function productionFunction(): string
{
    return riskyOperation()
        ->expect("Failed"); // Should use unwrapOr() in production
}
```

### Distinguishing map() vs andThen()

#### map() - Value transformation

```php
// ✅ Type conversion and formatting
$result = getUserAge($id)
    ->map(fn($age) => (string)$age)              // int → string
    ->map(fn($ageStr) => "$ageStr years old")    // Formatting
    ->unwrapOr('Age unknown');

// ✅ Data structure transformation
$userData = getUser($id)
    ->map(fn($user) => [
        'display_name' => $user['name'],
        'email' => $user['email'],
        'joined' => date('Y-m', strtotime($user['created_at']))
    ]);
```

#### andThen() - Sequential processing and error-prone processing

```php
// ✅ Sequential processing
$result = getUser($id)
    ->andThen(fn($user) => validateUser($user))      // Can error
    ->andThen(fn($user) => enrichUserData($user))    // Can error
    ->andThen(fn($user) => saveUserData($user));     // Can error

// ✅ Conditional processing
$permission = getUser($id)
    ->andThen(fn($user) => 
        $user['is_admin'] ? 
            Ok::of($user) : 
            Err::of('Administrator privileges required')
    );
```

## 📝 Error Message Design Principles

### 1. Specific and Actionable Messages

```php
// ✅ Good example
function validatePassword(string $password): Result
{
    if (strlen($password) < 8) {
        return Err::of("Password must be at least 8 characters (current: " . strlen($password) . " characters)");
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        return Err::of("Password must contain at least one uppercase letter");
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        return Err::of("Password must contain at least one digit");
    }
    
    return Ok::of($password);
}

// ❌ Bad example
function validatePassword(string $password): Result
{
    if (!isValidPassword($password)) {
        return Err::of("Invalid password"); // Unclear what's wrong
    }
    
    return Ok::of($password);
}
```

### 2. Providing Context Information

```php
// ✅ Good example
function processFile(string $filePath): Result
{
    if (!file_exists($filePath)) {
        return Err::of("File not found: $filePath");
    }
    
    $content = file_get_contents($filePath);
    if ($content === false) {
        return Err::of("Failed to read file: $filePath (please check permissions)");
    }
    
    return Ok::of($content);
}

// ❌ Bad example
function processFile(string $filePath): Result
{
    $content = file_get_contents($filePath);
    if ($content === false) {
        return Err::of("Error"); // Insufficient information
    }
    
    return Ok::of($content);
}
```

### 3. Unified Error Message Rules

```php
// Error message rules class
class ErrorMessages
{
    // Format: "Operation failed: reason (solution)"
    public static function fileNotFound(string $path): string
    {
        return "File not found: $path (please check the path)";
    }
    
    public static function validationFailed(string $field, string $rule): string
    {
        return "Input validation failed: {$field} field must be {$rule}";
    }
    
    public static function apiCallFailed(string $endpoint, int $statusCode): string
    {
        return "API call failed: $endpoint (HTTP status: $statusCode)";
    }
}

// Usage example
function validateEmail(string $email): Result
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return Err::of(ErrorMessages::validationFailed('email', 'a valid email address format'));
    }
    
    return Ok::of($email);
}
```

## 👥 Team Development Best Practices

### 1. Coding Conventions

#### Naming Rules

```php
// ✅ Good example - clear naming
function parseUserData(string $json): Result { /* ... */ }
function findActiveUser(int $id): Option { /* ... */ }
function validateRequiredFields(array $data): Result { /* ... */ }

// ❌ Bad example - ambiguous naming
function process(string $data): Result { /* ... */ }
function get(int $id): Option { /* ... */ }
function check(array $data): Result { /* ... */ }
```

#### Explicit Return Types

```php
// ✅ Good example
function loadConfig(string $path): Result
{
    // Implementation...
}

function findUser(int $id): Option
{
    // Implementation...
}

// ❌ Bad example - unclear type
function loadConfig(string $path)
{
    return Ok::of($config); // Return type unclear
}
```

### 2. Documentation Rules

```php
/**
 * Load user configuration file and perform validation
 *
 * @param string $configPath Path to configuration file
 * @return Result<array, string> Configuration array on success, error message on failure
 * 
 * @example
 * $config = loadUserConfig('/path/to/config.json');
 * if ($config->isOk()) {
 *     $settings = $config->unwrap();
 *     // Use configuration
 * } else {
 *     error_log($config->unwrapErr());
 * }
 */
function loadUserConfig(string $configPath): Result
{
    // Implementation...
}
```

### 3. Error Handling Strategy

#### Separation of Responsibilities by Layer

```php
// Data access layer - technical errors
class UserRepository
{
    public function findById(int $id): Result
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            
            if ($user === false) {
                return Err::of("User not found: ID $id");
            }
            
            return Ok::of($user);
        } catch (PDOException $e) {
            return Err::of("Database error: " . $e->getMessage());
        }
    }
}

// Business logic layer - business errors
class UserService
{
    public function activateUser(int $id): Result
    {
        return $this->userRepository->findById($id)
            ->andThen(fn($user) => $this->validateUserStatus($user))
            ->andThen(fn($user) => $this->performActivation($user))
            ->mapErr(fn($error) => "User activation error: $error");
    }
    
    private function validateUserStatus(array $user): Result
    {
        if ($user['status'] === 'active') {
            return Err::of("User is already activated");
        }
        
        if ($user['status'] === 'banned') {
            return Err::of("Banned users cannot be activated");
        }
        
        return Ok::of($user);
    }
}

// Presentation layer - user-facing messages
class UserController
{
    public function activateAction(int $id): JsonResponse
    {
        $result = $this->userService->activateUser($id);
        
        if ($result->isOk()) {
            return new JsonResponse([
                'success' => true,
                'message' => 'User successfully activated'
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

### 4. Testing Strategy

#### Testing Result Types

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
        $result = $userService->activateUser(2); // Already active user
        
        $this->assertTrue($result->isErr());
        $this->assertStringContains('already activated', $result->unwrapErr());
    }
}
```

#### Testing Option Types

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

### 5. Gradual Introduction Strategy

#### Phase 1: Introduction in new features

```php
// Start introduction with new features
class NewFeatureService
{
    public function processNewFeature(array $data): Result
    {
        return $this->validateNewFeatureData($data)
            ->andThen(fn($d) => $this->executeNewFeature($d));
    }
}
```

#### Phase 2: Partial migration of existing features

```php
// Wrap part of existing functionality
class LegacyUserService
{
    // New methods use Result type
    public function createUserSafely(array $userData): Result
    {
        try {
            $user = $this->createUser($userData); // Existing method
            return Ok::of($user);
        } catch (Exception $e) {
            return Err::of($e->getMessage());
        }
    }
    
    // Keep existing method unchanged
    public function createUser(array $userData): User
    {
        // Existing implementation
    }
}
```

#### Phase 3: Complete migration

```php
// Finally unify everything to Result type
class ModernUserService
{
    public function createUser(array $userData): Result { /* ... */ }
    public function updateUser(int $id, array $data): Result { /* ... */ }
    public function deleteUser(int $id): Result { /* ... */ }
    public function findUser(int $id): Option { /* ... */ }
}
```

## 🚨 Common Pitfalls and How to Avoid Them

### 1. Overuse of unwrap()

```php
// ❌ Bad example
function dangerousChain(int $id): string
{
    $user = findUser($id)->unwrap();           // Dangerous
    $profile = getProfile($user['id'])->unwrap(); // Dangerous
    return formatProfile($profile)->unwrap();     // Dangerous
}

// ✅ Good example
function safeChain(int $id): string
{
    return findUser($id)
        ->andThen(fn($user) => getProfile($user['id']))
        ->map(fn($profile) => formatProfile($profile))
        ->unwrapOr('No profile information');
}
```

### 2. Excessive nesting

```php
// ❌ Bad example
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

// ✅ Good example
function clearPipeline(array $data): Result
{
    return $this->validateStep($data)
        ->andThen(fn($d) => $this->enrichStep($d))
        ->andThen(fn($d) => $this->processStep($d))
        ->andThen(fn($d) => $this->saveStep($d))
        ->andThen(fn($d) => $this->notifyStep($d));
}
```

### 3. Inappropriate error granularity

```php
// ❌ Bad example - too coarse granularity
function validateUser(array $data): Result
{
    if (!$this->isValidUser($data)) {
        return Err::of("User data is invalid"); // Unclear what's invalid
    }
    return Ok::of($data);
}

// ✅ Good example - appropriate granularity
function validateUser(array $data): Result
{
    return $this->validateEmail($data['email'] ?? '')
        ->andThen(fn() => $this->validatePassword($data['password'] ?? ''))
        ->andThen(fn() => $this->validateAge($data['age'] ?? null))
        ->map(fn() => $data);
}
```

## 📊 Performance Guidelines

### 1. Method Chain Optimization

```php
// ✅ Efficient chain
function efficientProcessing(array $data): Result
{
    // Utilize early returns
    return $this->quickValidation($data)  // Light validation first
        ->andThen(fn($d) => $this->heavyProcessing($d)); // Heavy processing later
}

// ❌ Inefficient chain
function inefficientProcessing(array $data): Result
{
    return $this->heavyProcessing($data)  // Execute heavy processing first
        ->andThen(fn($d) => $this->quickValidation($d));
}
```

### 2. Utilizing Lazy Evaluation

```php
// ✅ Lazy evaluation
function lazyDefault(): string
{
    return getExpensiveValue()
        ->unwrapOrElse(fn() => calculateExpensiveDefault()); // Execute only when needed
}

// ❌ Eager evaluation
function eagerDefault(): string
{
    $default = calculateExpensiveDefault(); // Always executed
    return getExpensiveValue()->unwrapOr($default);
}
```

## 🎯 Summary

### Checklist

Check the following before introduction in your project:

- [ ] Appropriate usage scenarios are determined
- [ ] Method selection guidelines are understood
- [ ] Error messages are specific and actionable
- [ ] Coding conventions are agreed upon within the team
- [ ] Testing strategy is established
- [ ] Gradual introduction plan is formulated

### Recommended Learning Path

1. **Small-scale introduction**: Start with part of new features
2. **Team sharing**: Share best practices within the team
3. **Gradual expansion**: Expand application scope based on success stories
4. **Continuous improvement**: Refine conventions through operation

---

💡 **Practical Tip**: Introduce these guidelines gradually and customize them to fit your team's situation. The key to success is continuous improvement rather than seeking perfection.