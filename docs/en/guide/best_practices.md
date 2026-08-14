# Best Practices Guide

Practical guidelines and operational best practices for effectively utilizing Result and Option types in your projects.

## 🎯 What You'll Learn from This Guide

- Decision criteria for appropriate usage scenarios
- Guidelines for method selection and usage patterns
- Error message design principles
- Team development operational methods
- Integration strategies for real projects

## 🔍 Decision Criteria for Appropriate Usage

The deciding question for every failure is:

> Does the caller want to handle this failure as a normal branch of its flow?

If yes, express it with `Result` / `Option`. If no, let it escape as an
`Exception`. The rest of this section is about applying this question.

| Situation | Expression |
|---|---|
| Normal "no value" where the reason does not matter | `Option<T>` |
| Expected failure that the caller should branch on (validation, business rules) | `Result<T, E>` |
| Invariant violation, programming mistake | `Exception` |
| Infrastructure failure (file, DB, network) that this layer cannot recover from | `Exception` |
| Failure that an upper layer wants to retry or fall back on | Convert to `Result` at the boundary |

### When to Use Result Type

#### ✅ Failures that belong in Result

These are failures the caller is expected to branch on. The caller has
something meaningful to do for each variant, so enumerating them in the type
pays off.

```php
// 1. Validation - the caller branches per field
/** @return Result<array, InvalidField[]> */
function validateForm(array $input): Result
{
    // returns Err with a list of invalid fields
}

// 2. Business rule violations - the caller handles each variant
/** @return Result<User, UserAlreadyExists|InvalidPassword> */
function addUser(Username $user, string $password): Result
{
    if ($this->userExists($user)) {
        return Err::of(new UserAlreadyExists($user));
    }

    // ... remaining validation and registration logic
}

// 3. A set of expected failures the caller must distinguish
/** @return Result<Order, InsufficientBalance|OrderCancelled> */
function placeOrder(Order $order): Result
{
    // ... order placement logic
}
```

The caller then has a real branch for each error variant:

```php
$result = addUser($user, $password);

if ($result->isErr()) {
    return match (true) {
        $result->unwrapErr() instanceof UserAlreadyExists => 'exists',
        $result->unwrapErr() instanceof InvalidPassword   => 'invalid',
    };
}
```

#### ❌ Failures that should stay as exceptions

Do **not** put every failure into `Result`. Two common cases look natural at
first but hurt the caller:

```php
// ❌ Bad: infrastructure failure wrapped in Err - the caller cannot do anything
function readConfigFile(string $path): Result
{
    $content = file_get_contents($path); // Failure here is not a branch
    if ($content === false) {
        return Err::of('Failed to read file: ' . $path);
    }
    return Ok::of($content);
}

// The caller ends up like this - there is no meaningful "else":
$config = readConfigFile($path)->unwrapOr([]);
// A missing file and a permission error are both silently ignored.
```

```php
// ❌ Bad: programming mistakes expressed as Err
function getConfig(): Result
{
    if (!class_exists('Config')) {
        return Err::of('Config class does not exist'); // This is a bug, not a branch
    }
    return Ok::of(new Config());
}
```

If the failure has no meaningful branch on the caller side, leave it as an
exception:

```php
// ✅ Good: infrastructure failure stays an exception
function readConfigFile(string $path): array
{
    if (!file_exists($path)) {
        throw new RuntimeException('Configuration file not found: ' . $path);
    }
    
    $config = parse_ini_file($path, true);
    if ($config === false) {
        throw new RuntimeException('Failed to parse configuration file: ' . $path);
    }
    
    return $config;
}
```

#### Converting at the boundary (when exceptions become Result)

"Infrastructure failures are exceptions" is a default, not an absolute rule.
A layer whose job is retrying or offering alternatives may convert failures
into `Result` at its boundary:

```text
PDOException
    ↓ meaning is attached at the repository boundary
InfrastructureException
    ↓ converted at the use case boundary when recovery is possible
Result<User, ServiceUnavailable>
```

```php
class UserService
{
    /**
     * This layer can offer a fallback, so the failure becomes a branch.
     *
     * @throws InfrastructureException
     */
    public function fetchUser(int $id): Result
    {
        try {
            return Ok::of($this->repository->find($id));
        } catch (UserNotFound $e) {
            return Err::of($e);            // Expected absence → Result
        }
        // InfrastructureException propagates - the caller has no fallback here
    }
}
```

The same failure can switch representation depending on the layer. Decide per
boundary where the branch becomes meaningful, not globally.

### When to Use Option Type

`None` means a **normal absence** — the "not found" case that the caller
expects as a possibility. Never collapse abnormal failures into `None`: a
database outage and an absent user are different situations and the caller
must be able to tell them apart.

#### ✅ Recommended Scenarios

```php
// 1. Database queries - absence is a normal branch, outages are exceptions
function findUserById(int $id): Option
{
    // DatabaseException propagates; only "no row" becomes None
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

#### Keeping chains readable: split by responsibility

PHP has no `?` operator like Rust, so every `andThen` in a chain is written
as a nested callback. The reader must track the current `Ok` type, the next
type, captured variables, short-circuit conditions, and side effects at the
same time. The chain length is therefore a readability cost you pay upfront.

The rule that keeps chains readable:

> One chain = one responsibility. When the responsibility changes, extract
> a named method.

Compare the two versions of the same flow (validate → check existence →
update → audit):

```php
// ❌ Hard to read: every step looks identical
class UnreadableAdd
{
    public function add(Username $user, string $password, string $actor, int $now): Result
    {
        $path = $this->config->htpasswdPath;

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
}

// ✅ Readable: the public method states the business flow at one glance
class ReadableAdd
{
    public function add(Username $user, string $password, string $actor, int $now): Result
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
}
```

Practical guidelines:

- **One chain, one responsibility.** Validation, I/O, and audit logging are
  different responsibilities. Do not stack them in a single chain — split
  the chain where the responsibility changes.
- **Around 4 `andThen` steps per method is the comfortable limit** for a
  reader. More steps usually mean the method does several jobs.
- **Never nest an `andThen` inside another `andThen`.** Extract the nested
  logic into a named private method.
- **Imperative code is fine inside the implementation.** The boundary is
  where `Result` matters; the body may use plain `if` and early returns
  (see [Combining Result with imperative code](#combining-result-with-imperative-code)).

Keep in mind that `Result` does **not** provide atomicity for side effects.
If a later step (e.g. audit logging) fails after an earlier step already
committed (e.g. the user was added), returning `Err` makes the caller retry
and hit "already exists". Design such sequences so that either the failure
cannot be recovered by retrying, or the side effects are handled transactionally.

### Combining Result with imperative code

The library does not force you to express everything as chains. The
`Result` contract matters at the **boundary** of a method — what the caller
receives. Inside the method, plain PHP reads better for sequences that are
naturally imperative:

```php
class UserManager
{
    /**
     * @return Result<null, UserAlreadyExists|InvalidPassword>
     *
     * @throws InfrastructureException
     */
    public function add(Username $user, string $password, string $actor, int $now): Result
    {
        $path = $this->config->htpasswdPath;

        $validation = $this->validatePassword($password);
        if ($validation->isErr()) {
            return $validation;
        }

        if ($this->userExists($path, $user)) {
            return Err::of(new UserAlreadyExists($user));
        }

        // Infrastructure failures escape as exceptions
        $this->addUserAtomically($path, $user, $password);
        $this->audit->record($actor, 'user.add', $path, 'ok', $user->value, $now);

        return Ok::of(null);
    }
}
```

This is the pragmatic middle ground:

- **Boundary**: return `Result` when the caller should branch on the failure
- **Body**: use plain `if` / early returns / exceptions for everything else
- **Conversion point**: catch expected failures and turn them into `Err`
  where the branch becomes meaningful (see
  [Converting at the boundary](#converting-at-the-boundary-when-exceptions-become-result))

Chains shine for short transformations and for composing a few
already-tested methods. They are not the only way to use this library.

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
function parseJson(string $json): Result
{
    $data = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return Err::of("Failed to parse JSON: " . json_last_error_msg() . " (input: " . substr($json, 0, 80) . ")");
    }
    
    return Ok::of($data);
}

// ❌ Bad example
function parseJson(string $json): Result
{
    $data = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return Err::of("Error"); // Insufficient information
    }
    
    return Ok::of($data);
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

### 2. Overlong chains and nested andThen

```php
// ❌ Bad example - nested andThen: the reader loses track of types and captures
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

// ✅ Good example - flat chain, but only while the responsibility is one
class PipelineExample
{
    public function clearPipeline(array $data): Result
    {
        return $this->validateStep($data)
            ->andThen(fn($d) => $this->enrichStep($d))
            ->andThen(fn($d) => $this->processStep($d))
            ->andThen(fn($d) => $this->saveStep($d))
            ->andThen(fn($d) => $this->notifyStep($d));
        // All five steps are the same responsibility (pipeline processing).
        // This is near the comfortable limit - one more step means split.
    }
}

// ❌ Bad example - one chain, many responsibilities
class BadRegister
{
    public function registerUser(array $data): Result
    {
        return $this->validateInput($data)              // validation
            ->andThen(fn($d) => $this->saveToDatabase($d))  // I/O
            ->andThen(fn() => $this->sendWelcomeMail())     // notification
            ->andThen(fn() => $this->audit->record('registered'))
            ->andThen(fn() => $this->notifyAdmins());       // another notification
    }
}

// ✅ Good example - split where the responsibility changes
class GoodRegister
{
    public function registerUser(array $data): Result
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