<?php

declare(strict_types=1);

/**
 * Form Validation Implementation Example
 *
 * A practical example of safely validating form data using Result and Option types.
 * Can be copied and pasted for use in actual projects.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use ba0918\Result\{Err, None, Ok, Option, Result, Some};

/**
 * Validation rule
 */
class ValidationRule
{
    public function __construct(
        public readonly string $field,
        public readonly string $rule,
        public readonly mixed $parameter = null,
        public readonly string $message = '',
    ) {
    }
}

/**
 * Validation result
 */
class ValidationResult
{
    public function __construct(
        public readonly bool $isValid,
        public readonly array $data = [],
        public readonly array $errors = [],
    ) {
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function getFirstError(): Option
    {
        if (empty($this->errors)) {
            return None::instance();
        }

        $firstField = array_key_first($this->errors);
        $firstError = $this->errors[$firstField][0] ?? '';

        return new Some($firstError);
    }

    public function toResult(): Result
    {
        if ($this->isValid) {
            return new Ok($this->data);
        }

        $errorMessage = $this->getFirstError()->unwrapOr('Validation error occurred');

        return new Err($errorMessage);
    }
}

/**
 * Form validator
 */
class FormValidator
{
    private array $rules = [];

    private array $customMessages = [];

    /**
     * Add validation rule
     */
    public function rule(string $field, string $rule, mixed $parameter = null, string $message = ''): self
    {
        $this->rules[] = new ValidationRule($field, $rule, $parameter, $message);

        return $this;
    }

    /**
     * Set custom error message
     */
    public function message(string $field, string $rule, string $message): self
    {
        $this->customMessages["$field.$rule"] = $message;

        return $this;
    }

    /**
     * Execute validation
     */
    public function validate(array $data): ValidationResult
    {
        $errors = [];
        $validData = [];

        foreach ($this->rules as $rule) {
            $result = $this->validateField($data, $rule);

            if ($result->isErr()) {
                $errors[$rule->field][] = $result->unwrapErr();
            } else {
                $validData[$rule->field] = $result->unwrap();
            }
        }

        $isValid = empty($errors);

        return new ValidationResult($isValid, $validData, $errors);
    }

    /**
     * Single field validation
     */
    private function validateField(array $data, ValidationRule $rule): Result
    {
        $value = $data[$rule->field] ?? null;

        $result = match ($rule->rule) {
            'required' => $this->validateRequired($value),
            'email' => $this->validateEmail($value),
            'min_length' => $this->validateMinLength($value, (int) $rule->parameter),
            'max_length' => $this->validateMaxLength($value, (int) $rule->parameter),
            'numeric' => $this->validateNumeric($value),
            'integer' => $this->validateInteger($value),
            'min' => $this->validateMin($value, (int) $rule->parameter),
            'max' => $this->validateMax($value, (int) $rule->parameter),
            'regex' => $this->validateRegex($value, (string) $rule->parameter),
            'in' => $this->validateIn($value, (array) $rule->parameter),
            'confirmed' => $this->validateConfirmed($data, $rule->field),
            'unique' => $this->validateUnique($value, (string) $rule->parameter),
            'date' => $this->validateDate($value),
            'url' => $this->validateUrl($value),
            default => new Err("Unknown validation rule: {$rule->rule}")
        };

        return $result->mapErr(fn ($error) => $this->getCustomMessage($rule, $error));
    }

    /**
     * Required validation
     */
    private function validateRequired($value): Result
    {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            return new Err('This field is required');
        }

        return new Ok($value);
    }

    /**
     * Email format validation
     */
    private function validateEmail($value): Result
    {
        if ($value === null || $value === '') {
            return new Ok($value);
        }

        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return new Err('Please enter a valid email address');
        }

        return new Ok($value);
    }

    /**
     * Minimum length validation
     */
    private function validateMinLength($value, int $min): Result
    {
        if ($value === null || $value === '') {
            return new Ok($value);
        }

        if (mb_strlen((string) $value) < $min) {
            return new Err("Please enter at least {$min} characters");
        }

        return new Ok($value);
    }

    /**
     * Maximum length validation
     */
    private function validateMaxLength($value, int $max): Result
    {
        if ($value === null || $value === '') {
            return new Ok($value);
        }

        if (mb_strlen((string) $value) > $max) {
            return new Err("Please enter no more than {$max} characters");
        }

        return new Ok($value);
    }

    /**
     * Numeric validation
     */
    private function validateNumeric($value): Result
    {
        if ($value === null || $value === '') {
            return new Ok($value);
        }

        if (!is_numeric($value)) {
            return new Err('Please enter a numeric value');
        }

        return new Ok($value);
    }

    /**
     * Integer validation
     */
    private function validateInteger($value): Result
    {
        if ($value === null || $value === '') {
            return new Ok($value);
        }

        if (!filter_var($value, FILTER_VALIDATE_INT)) {
            return new Err('Please enter an integer value');
        }

        return new Ok((int) $value);
    }

    /**
     * Minimum value validation
     */
    private function validateMin($value, int $min): Result
    {
        if ($value === null || $value === '') {
            return new Ok($value);
        }

        if (!is_numeric($value) || (int) $value < $min) {
            return new Err("Please enter a value of {$min} or greater");
        }

        return new Ok($value);
    }

    /**
     * Maximum value validation
     */
    private function validateMax($value, int $max): Result
    {
        if ($value === null || $value === '') {
            return new Ok($value);
        }

        if (!is_numeric($value) || (int) $value > $max) {
            return new Err("Please enter a value of {$max} or less");
        }

        return new Ok($value);
    }

    /**
     * Regular expression validation
     */
    private function validateRegex($value, string $pattern): Result
    {
        if ($value === null || $value === '') {
            return new Ok($value);
        }

        if (!preg_match($pattern, (string) $value)) {
            return new Err('Please enter in the correct format');
        }

        return new Ok($value);
    }

    /**
     * Choice validation
     */
    private function validateIn($value, array $options): Result
    {
        if ($value === null || $value === '') {
            return new Ok($value);
        }

        if (!in_array($value, $options, true)) {
            $optionList = implode(', ', $options);

            return new Err("Please select from: $optionList");
        }

        return new Ok($value);
    }

    /**
     * Confirmation field validation
     */
    private function validateConfirmed(array $data, string $field): Result
    {
        $value = $data[$field] ?? null;
        $confirmValue = $data[$field . '_confirmation'] ?? null;

        if ($value !== $confirmValue) {
            return new Err('Confirmation input does not match');
        }

        return new Ok($value);
    }

    /**
     * Uniqueness validation (e.g., database duplicate check)
     */
    private function validateUnique($value, string $table): Result
    {
        if ($value === null || $value === '') {
            return new Ok($value);
        }

        // In actual implementation, check database
        // This is just a simulation
        $existingValues = ['admin@example.com', 'test@example.com'];

        if (in_array($value, $existingValues, true)) {
            return new Err('This value is already in use');
        }

        return new Ok($value);
    }

    /**
     * Date format validation
     */
    private function validateDate($value): Result
    {
        if ($value === null || $value === '') {
            return new Ok($value);
        }

        $date = DateTime::createFromFormat('Y-m-d', (string) $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            return new Err('Please enter in correct date format (YYYY-MM-DD)');
        }

        return new Ok($value);
    }

    /**
     * URL format validation
     */
    private function validateUrl($value): Result
    {
        if ($value === null || $value === '') {
            return new Ok($value);
        }

        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return new Err('Please enter a valid URL format');
        }

        return new Ok($value);
    }

    /**
     * Get custom message
     */
    private function getCustomMessage(ValidationRule $rule, string $defaultMessage): string
    {
        // Rule-specific custom message
        if (!empty($rule->message)) {
            return $rule->message;
        }

        // Global custom message
        $key = "{$rule->field}.{$rule->rule}";
        if (isset($this->customMessages[$key])) {
            return $this->customMessages[$key];
        }

        return $defaultMessage;
    }
}

/**
 * Dedicated validator class
 */
class UserRegistrationValidator
{
    public static function validate(array $data): Result
    {
        $validator = new FormValidator();

        $validationResult = $validator
            ->rule('name', 'required')
            ->rule('name', 'min_length', 2)
            ->rule('name', 'max_length', 50)
            ->rule('email', 'required')
            ->rule('email', 'email')
            ->rule('email', 'unique', 'users')
            ->rule('password', 'required')
            ->rule('password', 'min_length', 8)
            ->rule('password', 'regex', '/^(?=.*[A-Za-z])(?=.*\d)/')
            ->rule('password', 'confirmed')
            ->rule('age', 'required')
            ->rule('age', 'integer')
            ->rule('age', 'min', 13)
            ->rule('age', 'max', 120)
            ->rule('gender', 'in', ['male', 'female', 'other'])
            ->rule('website', 'url')
            ->rule('birthday', 'date')
            ->message('name', 'required', 'Please enter your name')
            ->message('email', 'required', 'Please enter your email address')
            ->message('email', 'unique', 'This email address is already registered')
            ->message('password', 'regex', 'Password must contain both letters and numbers')
            ->validate($data);

        return $validationResult->toResult();
    }
}

/**
 * Product validator class
 */
class ProductValidator
{
    public static function validate(array $data): Result
    {
        $validator = new FormValidator();

        $validationResult = $validator
            ->rule('name', 'required')
            ->rule('name', 'max_length', 100)
            ->rule('description', 'max_length', 1000)
            ->rule('price', 'required')
            ->rule('price', 'numeric')
            ->rule('price', 'min', 1)
            ->rule('category', 'required')
            ->rule('category', 'in', ['electronics', 'clothing', 'books', 'home'])
            ->rule('weight', 'numeric')
            ->rule('weight', 'min', 0)
            ->message('name', 'required', 'Please enter the product name')
            ->message('price', 'required', 'Please enter the price')
            ->message('price', 'min', 'Please enter a price of 1 or more')
            ->validate($data);

        return $validationResult->toResult();
    }
}

/**
 * Multi-step validation
 */
class MultiStepValidator
{
    public static function validateStep1(array $data): Result
    {
        $validator = new FormValidator();

        return $validator
            ->rule('first_name', 'required')
            ->rule('last_name', 'required')
            ->rule('email', 'required')
            ->rule('email', 'email')
            ->validate($data)
            ->toResult();
    }

    public static function validateStep2(array $data): Result
    {
        $validator = new FormValidator();

        return $validator
            ->rule('address', 'required')
            ->rule('city', 'required')
            ->rule('postal_code', 'required')
            ->rule('postal_code', 'regex', '/^\d{3}-\d{4}$/')
            ->validate($data)
            ->toResult();
    }

    public static function validateStep3(array $data): Result
    {
        $validator = new FormValidator();

        return $validator
            ->rule('payment_method', 'required')
            ->rule('payment_method', 'in', ['credit_card', 'bank_transfer', 'paypal'])
            ->rule('terms_accepted', 'required')
            ->message('terms_accepted', 'required', 'Please accept the terms of service')
            ->validate($data)
            ->toResult();
    }

    /**
     * Validate all steps together
     */
    public static function validateAll(array $step1Data, array $step2Data, array $step3Data): Result
    {
        return self::validateStep1($step1Data)
            ->andThen(
                fn ($data1) => self::validateStep2($step2Data)
                    ->map(fn ($data2) => array_merge($data1, $data2)),
            )
            ->andThen(
                fn ($mergedData) => self::validateStep3($step3Data)
                    ->map(fn ($data3) => array_merge($mergedData, $data3)),
            );
    }
}

// Usage examples
if ($_SERVER['SCRIPT_NAME'] === __FILE__) {
    echo "=== Form Validation Example ===\n";

    // User registration form validation example
    echo "\n--- User Registration Validation ---\n";

    $userData = [
        'name' => 'Alice Johnson',
        'email' => 'alice@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'age' => 25,
        'gender' => 'female',
        'website' => 'https://alice.example.com',
        'birthday' => '1998-05-15',
    ];

    $userValidation = UserRegistrationValidator::validate($userData);

    if ($userValidation->isOk()) {
        $validData = $userValidation->unwrap();
        echo "✅ User registration validation successful\n";
        echo 'Registration data: ' . json_encode($validData, JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo '❌ User registration validation failed: ' . $userValidation->unwrapErr() . "\n";
    }

    // Error case testing
    echo "\n--- Validation Error Example ---\n";

    $invalidUserData = [
        'name' => '',  // Required error
        'email' => 'invalid-email',  // Format error
        'password' => '123',  // Too short
        'age' => 5,  // Age restriction error
        'gender' => 'unknown',  // Choice error
    ];

    $invalidValidation = UserRegistrationValidator::validate($invalidUserData);

    if ($invalidValidation->isErr()) {
        echo 'Expected error: ' . $invalidValidation->unwrapErr() . "\n";
    }

    // Product validation example
    echo "\n--- Product Validation ---\n";

    $productData = [
        'name' => 'Wireless Headphones',
        'description' => 'High-quality wireless headphones with noise cancellation',
        'price' => 15900,
        'category' => 'electronics',
        'weight' => 250.5,
    ];

    $productValidation = ProductValidator::validate($productData);

    $productMessage = $productValidation
        ->map(fn ($data) => '✅ Product validation successful: ' . $data['name'])
        ->unwrapOr('❌ Product validation failed');

    echo $productMessage . "\n";

    // Multi-step validation example
    echo "\n--- Multi-Step Validation ---\n";

    $step1 = [
        'first_name' => 'Alice',
        'last_name' => 'Johnson',
        'email' => 'alice@example.com',
    ];

    $step2 = [
        'address' => '123 Main St',
        'city' => 'Tokyo',
        'postal_code' => '123-4567',
    ];

    $step3 = [
        'payment_method' => 'credit_card',
        'terms_accepted' => true,
    ];

    $multiStepValidation = MultiStepValidator::validateAll($step1, $step2, $step3);

    if ($multiStepValidation->isOk()) {
        $allData = $multiStepValidation->unwrap();
        echo "✅ Multi-step validation successful\n";
        echo 'Integrated data: ' . json_encode($allData, JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo '❌ Multi-step validation failed: ' . $multiStepValidation->unwrapErr() . "\n";
    }

    // Custom validation chain example
    echo "\n--- Custom Validation Chain ---\n";

    $customValidator = new FormValidator();
    $customResult = $customValidator
        ->rule('username', 'required')
        ->rule('username', 'min_length', 3)
        ->rule('username', 'max_length', 20)
        ->rule('username', 'regex', '/^[a-zA-Z0-9_]+$/')
        ->message('username', 'regex', 'Username can only contain letters, numbers, and underscores')
        ->validate(['username' => 'alice_123']);

    $customMessage = $customResult->toResult()
        ->map(fn ($data) => '✅ Custom validation successful: ' . $data['username'])
        ->unwrapOr('❌ Custom validation failed');

    echo $customMessage . "\n";

    echo "\nForm validation example completed.\n";
}
