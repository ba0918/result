# Basic Usage

Practical patterns and usage examples of Result and Option types that can be immediately applied in real projects.

## 🎯 What You'll Learn

- Before/after comparisons in real code
- Typical error handling patterns
- How to achieve null safety
- Declarative code with method chaining
- Practical use cases

## 📋 Prerequisites

- Completion of [Getting Started Tutorial](getting_started.md)
- Understanding of basic Result and Option type concepts

## 🔄 Real Code Replacement Examples

### Pattern 1: API Call Error Handling

#### Before (Traditional PHP)

```php
// Traditional code - dangerous and verbose
class UserService
{
    public function getUser(int $id): ?array
    {
        try {
            $response = file_get_contents("https://api.example.com/users/$id");
            if ($response === false) {
                return null; // Error information is lost
            }
            
            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("JSON parse error: " . json_last_error_msg());
                return null; // Error information is lost
            }
            
            if (!isset($data['id'])) {
                return null; // Why null is unclear
            }
            
            return $data;
        } catch (Exception $e) {
            error_log("API error: " . $e->getMessage());
            return null; // All errors become null
        }
    }
    
    public function displayUser(int $id): string
    {
        $user = $this->getUser($id);
        if ($user === null) {
            return "User not found"; // Error details unknown
        }
        
        return "User: " . $user['name'];
    }
}
```

#### After (Using Result Type)

```php
use Mizumi\Result\{Ok, Err, Result};

// Using Result type - safe and clear
class UserService
{
    public function getUser(int $id): Result
    {
        // API call
        $response = file_get_contents("https://api.example.com/users/$id");
        if ($response === false) {
            return new Err("API call failed");
        }
        
        // JSON parsing
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new Err("JSON parse error: " . json_last_error_msg());
        }
        
        // Data validation
        if (!isset($data['id'])) {
            return new Err("Invalid user data: ID not found");
        }
        
        return new Ok($data);
    }
    
    public function displayUser(int $id): string
    {
        return $this->getUser($id)
            ->map(fn($user) => "User: " . $user['name'])
            ->unwrapOr("User not found");
    }
    
    public function displayUserWithError(int $id): string
    {
        $result = $this->getUser($id);
        
        if ($result->isOk()) {
            $user = $result->unwrap();
            return "User: " . $user['name'];
        } else {
            $error = $result->unwrapErr();
            return "Error: " . $error;
        }
    }
}
```

### Pattern 2: Database Search and Null Safety

#### Before (Traditional PHP)

```php
// Traditional code - risk of null reference errors
class ProductRepository
{
    public function findById(int $id): ?array
    {
        // Simulate PDO search
        $products = [
            1 => ['id' => 1, 'name' => 'Laptop', 'price' => 80000],
            2 => ['id' => 2, 'name' => 'Mouse', 'price' => 1500],
        ];
        
        return $products[$id] ?? null;
    }
    
    public function getProductName(int $id): string
    {
        $product = $this->findById($id);
        
        // Easy to forget null check
        return $product['name']; // Fatal Error if null!
    }
    
    public function calculateTax(int $id): float
    {
        $product = $this->findById($id);
        if ($product === null) {
            return 0.0; // Error case is ambiguous
        }
        
        return $product['price'] * 0.1;
    }
}
```

#### After (Using Option Type)

```php
use Mizumi\Result\{Some, None, Option};

// Using Option type - null safe
class ProductRepository
{
    public function findById(int $id): Option
    {
        $products = [
            1 => ['id' => 1, 'name' => 'Laptop', 'price' => 80000],
            2 => ['id' => 2, 'name' => 'Mouse', 'price' => 1500],
        ];
        
        if (isset($products[$id])) {
            return new Some($products[$id]);
        }
        return None::instance();
    }
    
    public function getProductName(int $id): string
    {
        return $this->findById($id)
            ->map(fn($product) => $product['name'])
            ->unwrapOr('Product name unknown');
    }
    
    public function calculateTax(int $id): Option
    {
        return $this->findById($id)
            ->map(fn($product) => $product['price'] * 0.1);
    }
    
    public function getFormattedPrice(int $id): string
    {
        return $this->findById($id)
            ->map(fn($product) => number_format($product['price']) . ' USD')
            ->unwrapOr('Price information unavailable');
    }
}
```

## 🔗 Basic Method Chaining Patterns

### Pattern 1: Data transformation chains

```php
use Mizumi\Result\{Ok, Err, Some, None};

// String normalization and validation
function processUsername(string $input): Result
{
    return new Ok($input)
        ->map(fn($s) => trim($s))                    // 1. Remove whitespace
        ->map(fn($s) => strtolower($s))              // 2. Convert to lowercase
        ->andThen(fn($s) => strlen($s) >= 3 ? 
            new Ok($s) : 
            new Err("Username must be at least 3 characters"))  // 3. Length validation
        ->andThen(fn($s) => preg_match('/^[a-z0-9_]+$/', $s) ? 
            new Ok($s) : 
            new Err("Username contains invalid characters"));    // 4. Character validation
}

// Usage example
$result = processUsername("  John_123  ");
echo $result->unwrapOr("Invalid username"); // "john_123"

$error = processUsername("ab");
echo $error->unwrapErr(); // "Username must be at least 3 characters"
```

### Pattern 2: Combining multiple search results

```php
// Combine user and their settings
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
    return isset($users[$id]) ? new Some($users[$id]) : None::instance();
}

function getSettings(int $userId): Option
{
    $settings = [1 => ['theme' => 'dark', 'lang' => 'en']];
    return isset($settings[$userId]) ? new Some($settings[$userId]) : None::instance();
}

// Usage example
$userWithSettings = getUserWithSettings(1);
if ($userWithSettings->isSome()) {
    $data = $userWithSettings->unwrap();
    echo "User: " . $data['name'] . ", Theme: " . $data['settings']['theme'];
}
```

## 🛠️ Typical Error Handling Patterns

### Pattern 1: Configuration file loading

```php
use Mizumi\Result\{Ok, Err, Result};

function loadConfiguration(string $configPath): Result
{
    // Check file existence
    if (!file_exists($configPath)) {
        return new Err("Configuration file not found: $configPath");
    }
    
    // Read file
    $content = file_get_contents($configPath);
    if ($content === false) {
        return new Err("Failed to read configuration file: $configPath");
    }
    
    // Parse JSON
    $config = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new Err("Invalid JSON format in configuration file: " . json_last_error_msg());
    }
    
    // Check required fields
    $required = ['app_name', 'database'];
    foreach ($required as $key) {
        if (!isset($config[$key])) {
            return new Err("Missing required configuration key: $key");
        }
    }
    
    return new Ok($config);
}

// Usage example - merge with default configuration
function getAppConfig(): array
{
    $defaultConfig = [
        'app_name' => 'DefaultApp',
        'debug' => false,
        'database' => ['host' => 'localhost']
    ];
    
    return loadConfiguration('config.json')
        ->map(fn($config) => array_merge($defaultConfig, $config))
        ->unwrapOr($defaultConfig);
}
```

### Pattern 2: Validation processing

```php
use Mizumi\Result\{Ok, Err, Result};

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
            return new Err("Email address is required");
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new Err("Invalid email address format");
        }
        
        return new Ok($email);
    }
    
    private function validatePassword(string $password): Result
    {
        if (strlen($password) < 8) {
            return new Err("Password must be at least 8 characters");
        }
        
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            return new Err("Password must contain both letters and numbers");
        }
        
        return new Ok($password);
    }
    
    private function validateAge(?int $age): Result
    {
        if ($age === null) {
            return new Err("Age is required");
        }
        
        if ($age < 0 || $age > 150) {
            return new Err("Age must be between 0 and 150");
        }
        
        return new Ok($age);
    }
}

// Usage example
$validator = new UserValidator();
$result = $validator->validateUserData([
    'email' => 'user@example.com',
    'password' => 'password123',
    'age' => 25
]);

if ($result->isOk()) {
    $validData = $result->unwrap();
    echo "Validation successful: " . $validData['email'];
} else {
    echo "Validation error: " . $result->unwrapErr();
}
```

## 🔧 Achieving Null Safety

### Pattern 1: Safe value retrieval from associative arrays

```php
use Mizumi\Result\{Some, None, Option};

function safeGet(array $array, string $key): Option
{
    return isset($array[$key]) ? new Some($array[$key]) : None::instance();
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
    
    return new Some($current);
}

// Usage example
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

// Traditional dangerous approach
// $theme = $data['user']['profile']['settings']['theme']; // May cause errors

// Safe approach
$theme = safeGetNested($data, ['user', 'profile', 'settings', 'theme'])
    ->unwrapOr('light');

echo "Theme: $theme"; // "Theme: dark"
```

### Pattern 2: Safe processing of database results

```php
use Mizumi\Result\{Some, None, Option};

class UserRepository
{
    public function findByEmail(string $email): Option
    {
        // Simulate actual DB search
        $users = [
            'alice@example.com' => ['id' => 1, 'name' => 'Alice', 'role' => 'admin'],
            'bob@example.com' => ['id' => 2, 'name' => 'Bob', 'role' => 'user'],
        ];
        
        if (isset($users[$email])) {
            return new Some($users[$email]);
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
            ->unwrapOr('Guest User');
    }
}
```

## 🎯 Practical Use Cases

### Case 1: Form Processing

```php
use Mizumi\Result\{Ok, Err, Some, None, Result, Option};

class ContactFormProcessor
{
    public function processForm(array $formData): Result
    {
        return $this->validateForm($formData)
            ->andThen(fn($data) => $this->sendEmail($data))
            ->andThen(fn($result) => $this->saveToDatabase($formData))
            ->map(fn($id) => "Contact form submitted successfully. ID: $id");
    }
    
    private function validateForm(array $data): Result
    {
        $name = $this->getFormValue($data, 'name');
        $email = $this->getFormValue($data, 'email');
        $message = $this->getFormValue($data, 'message');
        
        if ($name->isNone()) {
            return new Err("Name is required");
        }
        
        if ($email->isNone()) {
            return new Err("Email address is required");
        }
        
        if ($message->isNone()) {
            return new Err("Message is required");
        }
        
        return new Ok($data);
    }
    
    private function getFormValue(array $data, string $key): Option
    {
        $value = trim($data[$key] ?? '');
        return empty($value) ? None::instance() : new Some($value);
    }
    
    private function sendEmail(array $data): Result
    {
        // Simulate email sending
        $success = rand(0, 1); // Random success/failure
        
        if ($success) {
            return new Ok("Email sent successfully");
        } else {
            return new Err("Failed to send email");
        }
    }
    
    private function saveToDatabase(array $data): Result
    {
        // Simulate DB save
        $id = rand(1000, 9999);
        return new Ok($id);
    }
}

// Usage example
$processor = new ContactFormProcessor();
$result = $processor->processForm([
    'name' => 'Alice',
    'email' => 'alice@example.com',
    'message' => 'Hello there'
]);

echo $result->unwrapOr('Processing failed');
```

### Case 2: External API Integration

```php
use Mizumi\Result\{Ok, Err, Result};

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
        // Simulate actual API call
        $validCities = ['Tokyo', 'Osaka', 'Nagoya'];
        
        if (!in_array($city, $validCities, true)) {
            return new Err("Unsupported city: $city");
        }
        
        // Simulate API response
        $response = json_encode([
            'weather' => [
                'main' => ['temp' => rand(15, 35)]
            ]
        ]);
        
        return new Ok($response);
    }
    
    private function parseResponse(string $response): Result
    {
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new Err("Failed to parse API response");
        }
        
        return new Ok($data);
    }
    
    private function extractTemperature(array $data): Result
    {
        if (!isset($data['weather']['main']['temp'])) {
            return new Err("Temperature data not found");
        }
        
        return new Ok($data['weather']['main']['temp']);
    }
    
    private function formatTemperature(int $temp): string
    {
        return "{$temp}°C";
    }
}

// Usage example
$weather = new WeatherService();
$result = $weather->getWeather('Tokyo');

echo $result->unwrapOr('Unable to retrieve weather information');
```

## 🚀 Next Steps

You've now understood the basic usage patterns with this tutorial.

### Learning Path

1. **✅ Complete**: [Getting Started Tutorial](getting_started.md)
2. **✅ Complete**: Basic Usage (this tutorial)
3. **➡️ Next**: [Advanced Patterns](advanced_patterns.md) - More complex usage examples and techniques

### Learn More

- **[Best Practices](../guide/best_practices.md)** - Operational guidelines for team development
- **[Performance Guide](../guide/performance_guide.md)** - Efficient usage methods
- **[Migration Guide](../guide/migration_guide.md)** - Introduction to existing projects

---

💡 **Practical Tip**: Try applying the patterns introduced in this chapter to small parts of your actual projects first. Gradual introduction is the key to success.