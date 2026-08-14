# Advanced Patterns and Techniques

This tutorial explains advanced patterns and techniques for effectively using all the features of the library.

## 🎯 What You'll Learn in This Tutorial

- Building complex method chains
- Advanced error handling patterns
- Using special methods such as `flatten` and `transpose`
- Performance considerations
- Anti-patterns and how to avoid them

## 📋 Prerequisites

- Completion of the [Getting Started Tutorial](getting_started.md) and [Basic Usage](basic_usage.md)
- Practical experience with the Result and Option types

## 🔄 Complex Method Chains

### Pattern 1: Flattening with flatten()

This method flattens nested Result and Option types by one level.

```php
use ba0918\Result\{Ok, Err, Some, None, Result, Option};

// Result<Result<T, E>, E> → Result<T, E>
function complexOperation(int $value): Result
{
    return new Ok($value)
        ->map(fn($v) => $v > 0 ? new Ok($v * 2) : new Err("Negative value"))
        ->flatten(); // Ok(Ok(4)) → Ok(4), Ok(Err("Negative value")) → Err("Negative value")
}

// Usage example
$result1 = complexOperation(2);  // Ok(4)
$result2 = complexOperation(-1); // Err("Negative value")

echo $result1->unwrapOr(0); // 4
echo $result2->unwrapErr(); // "Negative value"
```

```php
// Usage in multi-layer processing
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
        new Err("Missing ID");
}

function enrichData(array $data): Result
{
    // Enrich with information from an external data source
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

### Pattern 2: Type Conversion with transpose()

This method converts between Result and Option types.

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
        new Err("Empty input");
}

// Using transpose
function handleOptionalValidation(?string $input): Result
{
    return processOptionalValue($input)  // Option<Result<string, string>>
        ->transpose()                    // Result<Option<string>, string>
        ->map(fn($opt) => $opt->unwrapOr("default value"));
}

// Usage example
$result1 = handleOptionalValidation("hello");  // Ok("hello")
$result2 = handleOptionalValidation(null);     // Ok("default value")
$result3 = handleOptionalValidation("");       // Err("Empty input")
```

### Pattern 3: Combined Error Handling

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
                'message' => 'Data processing completed',
                'response' => $response
            ]);
    }
    
    private function validateStructure(array $input): Result
    {
        $required = ['type', 'data', 'metadata'];
        
        foreach ($required as $field) {
            if (!isset($input[$field])) {
                return new Err("Missing required field: $field");
            }
        }
        
        return new Ok($input);
    }
    
    private function enrichWithMetadata(array $data): Result
    {
        // Fetch metadata from an external service
        $metadata = $this->fetchExternalMetadata($data['type']);
        
        if ($metadata->isErr()) {
            return $metadata; // Propagate the error as-is
        }
        
        $enriched = array_merge($data, [
            'external_metadata' => $metadata->unwrap()
        ]);
        
        return new Ok($enriched);
    }
    
    private function fetchExternalMetadata(string $type): Result
    {
        // Simulate an external API call
        $metadataMap = [
            'user' => ['schema_version' => '1.0', 'category' => 'personal'],
            'product' => ['schema_version' => '2.1', 'category' => 'catalog']
        ];
        
        if (!isset($metadataMap[$type])) {
            return new Err("Unsupported data type: $type");
        }
        
        return new Ok($metadataMap[$type]);
    }
    
    private function validateBusinessRules(array $data): Result
    {
        // Business rule validation
        if ($data['type'] === 'user' && !isset($data['data']['email'])) {
            return new Err("User data requires an email address");
        }
        
        if ($data['type'] === 'product' && !isset($data['data']['price'])) {
            return new Err("Product data requires a price");
        }
        
        return new Ok($data);
    }
    
    private function persistData(array $data): Result
    {
        // Simulate a database save
        $id = 'record_' . uniqid();
        
        // Simulate success/failure randomly
        if (rand(0, 10) < 8) {
            return new Ok($id);
        } else {
            return new Err("Failed to save to database");
        }
    }
    
    private function sendNotification(string $recordId): Result
    {
        // Simulate sending a notification
        if (rand(0, 10) < 9) {
            return new Ok("Notification sent: $recordId");
        } else {
            return new Err("Failed to send notification");
        }
    }
}

// Usage example
$processor = new DataProcessor();
$result = $processor->processComplexData([
    'type' => 'user',
    'data' => ['email' => 'user@example.com', 'name' => 'Alice'],
    'metadata' => ['source' => 'web']
]);

if ($result->isOk()) {
    $response = $result->unwrap();
    echo "Success: " . $response['message'];
} else {
    echo "Error: " . $result->unwrapErr();
}
```

## 🔧 Advanced Error Handling Patterns

### Pattern 1: Error Classification and Recovery

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
                return $result; // Return unrecoverable errors immediately
            }
            
            $attempt++;
            if ($attempt < $maxRetries) {
                sleep(pow(2, $attempt)); // Exponential backoff
            }
        }
        
        return new Err(new DetailedError(
            ErrorType::SYSTEM,
            "Maximum retry attempts reached",
            "Attempts: $maxRetries"
        ));
    }
    
    private function processData(array $data): Result
    {
        // Simulate a network error (recoverable)
        if (rand(0, 10) < 3) {
            return new Err(new DetailedError(
                ErrorType::NETWORK,
                "Network connection failed",
                "timeout after 30s"
            ));
        }
        
        // Validation error (unrecoverable)
        if (!isset($data['id'])) {
            return new Err(new DetailedError(
                ErrorType::VALIDATION,
                "ID field is required",
                "required field missing"
            ));
        }
        
        return new Ok("Data processed: " . $data['id']);
    }
}
```

### Pattern 2: Error Transformation and Aggregation

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
        
        // Validate each field
        $emailValidation = $this->validateEmail($data['email'] ?? '');
        $passwordValidation = $this->validatePassword($data['password'] ?? '');
        $ageValidation = $this->validateAge($data['age'] ?? null);
        
        // Aggregate errors
        if ($emailValidation->isErr()) {
            $validation = $validation->addError('email', $emailValidation->unwrapErr());
        }
        
        if ($passwordValidation->isErr()) {
            $validation = $validation->addError('password', $passwordValidation->unwrapErr());
        }
        
        if ($ageValidation->isErr()) {
            $validation = $validation->addError('age', $ageValidation->unwrapErr());
        }
        
        // Return the result
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
```

## 🔄 Advanced Option Operations

### Pattern 1: Combining Multiple Options

```php
use ba0918\Result\{Some, None, Option};

class UserProfileBuilder
{
    public function buildProfile(int $userId): Option
    {
        $user = $this->findUser($userId);
        $settings = $this->findSettings($userId);
        $preferences = $this->findPreferences($userId);
        
        // Combine using zip()
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
        
        // Exclusive selection using xor()
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

### Pattern 2: Filtering and Conditional Branching

```php
use ba0918\Result\{Some, None, Option};

class ProductFilter
{
    public function findAvailableProduct(int $productId): Option
    {
        return $this->findProduct($productId)
            ->filter(fn($product) => $product['available'])  // Only available products
            ->filter(fn($product) => $product['price'] > 0);  // Only products with a positive price
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

## ⚡ Performance Considerations

### 1. Leveraging Lazy Evaluation

```php
use ba0918\Result\{Ok, Err, Result};

class OptimizedProcessor
{
    public function processLargeDataset(array $items): Result
    {
        // Leverage lazy evaluation with unwrapOrElse()
        return $this->validateDataset($items)
            ->andThen(fn($data) => $this->processInBatches($data))
            ->unwrapOrElse(fn() => $this->fallbackToCache());
    }
    
    private function validateDataset(array $items): Result
    {
        if (count($items) > 10000) {
            return new Err("Dataset too large");
        }
        return new Ok($items);
    }
    
    private function processInBatches(array $items): Result
    {
        // Simulate batch processing
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
        // Batch processing implementation
        return new Ok(array_map(fn($item) => $item * 2, $batch));
    }
    
    private function fallbackToCache(): array
    {
        // Fallback from cache (expensive operation)
        return range(1, 100); // In practice, read from cache
    }
}
```

### 2. Memory Efficiency Optimization

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
            
            // Explicitly unset to prevent memory leaks
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

// Usage example
$processor = new MemoryEfficientProcessor();
$largeDataset = range(1, 1000000);

foreach ($processor->processStream($largeDataset) as $processed) {
    // Memory-efficient processing
    echo $processed . "\n";
}
```

## ❌ Anti-patterns and How to Avoid Them

### Anti-pattern 1: Excessive Use of unwrap()

```php
// ❌ Bad example - overusing unwrap()
function badExample(): string
{
    $user = findUser(1)->unwrap();           // May throw an exception
    $settings = getSettings($user['id'])->unwrap(); // May throw an exception
    $theme = $settings['theme']->unwrap();   // May throw an exception
    
    return $theme;
}

// ✅ Good example - safe processing
function goodExample(): string
{
    return findUser(1)
        ->andThen(fn($user) => getSettings($user['id']))
        ->map(fn($settings) => $settings['theme'])
        ->unwrapOr('default');
}
```

### Anti-pattern 2: Losing Error Information

```php
// ❌ Bad example - losing error information
function badErrorHandling(int $id): ?array
{
    $result = fetchUserData($id);
    return $result->isOk() ? $result->unwrap() : null; // Error information is lost
}

// ✅ Good example - preserving error information
function goodErrorHandling(int $id): Result
{
    return fetchUserData($id)
        ->mapErr(fn($error) => "Failed to fetch user data: $error");
}
```

### Anti-pattern 3: Using Inappropriate Types

```php
// ❌ Bad example - mixing with nullable types
function badMixing(?string $input): Option
{
    if ($input === null) {
        return None::instance();
    }
    return new Some($input); // Null checks are still required
}

// ✅ Good example - consistent type usage
function goodTyping(Option $input): Option
{
    return $input->filter(fn($value) => !empty($value));
}
```

## 🚀 Practical Integration Example

### Complete Workflow Implementation

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
                'message' => 'Order processed successfully'
            ]);
    }
    
    private function validateOrder(array $data): Result
    {
        if (!isset($data['items']) || empty($data['items'])) {
            return new Err("No items specified in order");
        }
        
        if (!isset($data['customer_id'])) {
            return new Err("Customer ID is required");
        }
        
        return new Ok($data);
    }
    
    private function checkInventory(array $order): Result
    {
        foreach ($order['items'] as $item) {
            $available = $this->getInventoryCount($item['product_id']);
            
            if ($available->isNone() || $available->unwrap() < $item['quantity']) {
                return new Err("Insufficient inventory for product ID {$item['product_id']}");
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
                return new Err("Price information not found for product ID {$item['product_id']}");
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
        // Simulate payment processing
        if ($order['total_amount'] > 100000) {
            return new Err("Payment amount exceeds the limit");
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
        // Simulate sending notifications
        return new Ok($invoice);
    }
}

// Usage example
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
    echo "Order processed: " . $response['order_id'];
} else {
    echo "Order processing error: " . $result->unwrapErr();
}
```

## 🚀 Summary and Next Steps

You have now learned advanced patterns and techniques.

### Learning Path

1. **✅ Completed**: [Getting Started Tutorial](getting_started.md)
2. **✅ Completed**: [Basic Usage](basic_usage.md)
3. **✅ Completed**: Advanced Patterns and Techniques (this tutorial)

### Learn More

- **[Best Practices](../guide/best_practices.md)** - Operational guidelines for team development
- **[Performance Guide](../guide/performance_guide.md)** - Detailed optimization techniques
- **[API Reference](../api/result_api_reference.md)** - Complete specification of all methods

### Applying to Practice

- Introduce patterns gradually, starting with small modules
- Share patterns through team code reviews
- Identify bottlenecks through performance measurement
- Continue learning and improving patterns

---

💡 **Pro tip**: Combining these patterns lets you build robust, maintainable codebases. Always apply them with a balance between your business requirements and the cost of complexity.
