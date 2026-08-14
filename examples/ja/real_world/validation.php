<?php

declare(strict_types=1);

/**
 * フォームバリデーション処理の実装例
 *
 * Result型とOption型を使用してフォームデータの検証を安全に行う実用的な例です。
 * 実際のプロジェクトでコピー&ペーストして使用できます。
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use ba0918\Result\{Err, None, Ok, Option, Result, Some};

/**
 * バリデーションルール
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
 * バリデーション結果
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

        return Some::of($firstError);
    }

    public function toResult(): Result
    {
        if ($this->isValid) {
            return Ok::of($this->data);
        }

        $errorMessage = $this->getFirstError()->unwrapOr('バリデーションエラーが発生しました');

        return Err::of($errorMessage);
    }
}

/**
 * フォームバリデーター
 */
class FormValidator
{
    private array $rules = [];

    private array $customMessages = [];

    /**
     * バリデーションルールを追加
     */
    public function rule(string $field, string $rule, mixed $parameter = null, string $message = ''): self
    {
        $this->rules[] = new ValidationRule($field, $rule, $parameter, $message);

        return $this;
    }

    /**
     * カスタムエラーメッセージを設定
     */
    public function message(string $field, string $rule, string $message): self
    {
        $this->customMessages["$field.$rule"] = $message;

        return $this;
    }

    /**
     * バリデーションを実行
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
     * 単一フィールドのバリデーション
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
            default => Err::of("未知のバリデーションルール: {$rule->rule}")
        };

        return $result->mapErr(fn ($error) => $this->getCustomMessage($rule, $error));
    }

    /**
     * 必須チェック
     */
    private function validateRequired($value): Result
    {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            return Err::of('必須項目です');
        }

        return Ok::of($value);
    }

    /**
     * メールアドレス形式チェック
     */
    private function validateEmail($value): Result
    {
        if ($value === null || $value === '') {
            return Ok::of($value);
        }

        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return Err::of('正しいメールアドレス形式で入力してください');
        }

        return Ok::of($value);
    }

    /**
     * 最小文字数チェック
     */
    private function validateMinLength($value, int $min): Result
    {
        if ($value === null || $value === '') {
            return Ok::of($value);
        }

        if (mb_strlen((string) $value) < $min) {
            return Err::of("{$min}文字以上で入力してください");
        }

        return Ok::of($value);
    }

    /**
     * 最大文字数チェック
     */
    private function validateMaxLength($value, int $max): Result
    {
        if ($value === null || $value === '') {
            return Ok::of($value);
        }

        if (mb_strlen((string) $value) > $max) {
            return Err::of("{$max}文字以内で入力してください");
        }

        return Ok::of($value);
    }

    /**
     * 数値チェック
     */
    private function validateNumeric($value): Result
    {
        if ($value === null || $value === '') {
            return Ok::of($value);
        }

        if (!is_numeric($value)) {
            return Err::of('数値で入力してください');
        }

        return Ok::of($value);
    }

    /**
     * 整数チェック
     */
    private function validateInteger($value): Result
    {
        if ($value === null || $value === '') {
            return Ok::of($value);
        }

        if (!filter_var($value, FILTER_VALIDATE_INT)) {
            return Err::of('整数で入力してください');
        }

        return Ok::of((int) $value);
    }

    /**
     * 最小値チェック
     */
    private function validateMin($value, int $min): Result
    {
        if ($value === null || $value === '') {
            return Ok::of($value);
        }

        if (!is_numeric($value) || (int) $value < $min) {
            return Err::of("{$min}以上の値を入力してください");
        }

        return Ok::of($value);
    }

    /**
     * 最大値チェック
     */
    private function validateMax($value, int $max): Result
    {
        if ($value === null || $value === '') {
            return Ok::of($value);
        }

        if (!is_numeric($value) || (int) $value > $max) {
            return Err::of("{$max}以下の値を入力してください");
        }

        return Ok::of($value);
    }

    /**
     * 正規表現チェック
     */
    private function validateRegex($value, string $pattern): Result
    {
        if ($value === null || $value === '') {
            return Ok::of($value);
        }

        if (!preg_match($pattern, (string) $value)) {
            return Err::of('正しい形式で入力してください');
        }

        return Ok::of($value);
    }

    /**
     * 選択肢チェック
     */
    private function validateIn($value, array $options): Result
    {
        if ($value === null || $value === '') {
            return Ok::of($value);
        }

        if (!in_array($value, $options, true)) {
            $optionList = implode(', ', $options);

            return Err::of("次の値から選択してください: $optionList");
        }

        return Ok::of($value);
    }

    /**
     * 確認フィールドチェック
     */
    private function validateConfirmed(array $data, string $field): Result
    {
        $value = $data[$field] ?? null;
        $confirmValue = $data[$field . '_confirmation'] ?? null;

        if ($value !== $confirmValue) {
            return Err::of('確認入力が一致しません');
        }

        return Ok::of($value);
    }

    /**
     * 一意性チェック（例：データベースでの重複チェック）
     */
    private function validateUnique($value, string $table): Result
    {
        if ($value === null || $value === '') {
            return Ok::of($value);
        }

        // 実際の実装ではデータベースをチェック
        // ここではシミュレーション
        $existingValues = ['admin@example.com', 'test@example.com'];

        if (in_array($value, $existingValues, true)) {
            return Err::of('この値は既に使用されています');
        }

        return Ok::of($value);
    }

    /**
     * 日付形式チェック
     */
    private function validateDate($value): Result
    {
        if ($value === null || $value === '') {
            return Ok::of($value);
        }

        $date = DateTime::createFromFormat('Y-m-d', (string) $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            return Err::of('正しい日付形式（YYYY-MM-DD）で入力してください');
        }

        return Ok::of($value);
    }

    /**
     * URL形式チェック
     */
    private function validateUrl($value): Result
    {
        if ($value === null || $value === '') {
            return Ok::of($value);
        }

        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return Err::of('正しいURL形式で入力してください');
        }

        return Ok::of($value);
    }

    /**
     * カスタムメッセージの取得
     */
    private function getCustomMessage(ValidationRule $rule, string $defaultMessage): string
    {
        // ルール固有のカスタムメッセージ
        if (!empty($rule->message)) {
            return $rule->message;
        }

        // グローバルカスタムメッセージ
        $key = "{$rule->field}.{$rule->rule}";
        if (isset($this->customMessages[$key])) {
            return $this->customMessages[$key];
        }

        return $defaultMessage;
    }
}

/**
 * 専用バリデータークラス
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
            ->message('name', 'required', 'お名前を入力してください')
            ->message('email', 'required', 'メールアドレスを入力してください')
            ->message('email', 'unique', 'このメールアドレスは既に登録されています')
            ->message('password', 'regex', 'パスワードは英数字を含む必要があります')
            ->validate($data);

        return $validationResult->toResult();
    }
}

/**
 * 商品バリデータークラス
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
            ->message('name', 'required', '商品名を入力してください')
            ->message('price', 'required', '価格を入力してください')
            ->message('price', 'min', '価格は1円以上で入力してください')
            ->validate($data);

        return $validationResult->toResult();
    }
}

/**
 * 複数ステップバリデーション
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
            ->message('terms_accepted', 'required', '利用規約に同意してください')
            ->validate($data)
            ->toResult();
    }

    /**
     * 全ステップを統合してバリデーション
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

// 使用例
if ($_SERVER['SCRIPT_NAME'] === __FILE__) {
    echo "=== Form Validation Example ===\n";

    // ユーザー登録フォームのバリデーション例
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
        echo "✅ ユーザー登録バリデーション成功\n";
        echo '登録データ: ' . json_encode($validData, JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo '❌ ユーザー登録バリデーション失敗: ' . $userValidation->unwrapErr() . "\n";
    }

    // エラーケースのテスト
    echo "\n--- Validation Error Example ---\n";

    $invalidUserData = [
        'name' => '',  // 必須エラー
        'email' => 'invalid-email',  // 形式エラー
        'password' => '123',  // 短すぎる
        'age' => 5,  // 年齢制限エラー
        'gender' => 'unknown',  // 選択肢エラー
    ];

    $invalidValidation = UserRegistrationValidator::validate($invalidUserData);

    if ($invalidValidation->isErr()) {
        echo '期待通りのエラー: ' . $invalidValidation->unwrapErr() . "\n";
    }

    // 商品バリデーションの例
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
        ->map(fn ($data) => '✅ 商品バリデーション成功: ' . $data['name'])
        ->unwrapOr('❌ 商品バリデーション失敗');

    echo $productMessage . "\n";

    // 複数ステップバリデーションの例
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
        echo "✅ 複数ステップバリデーション成功\n";
        echo '統合データ: ' . json_encode($allData, JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo '❌ 複数ステップバリデーション失敗: ' . $multiStepValidation->unwrapErr() . "\n";
    }

    // カスタムバリデーションチェーンの例
    echo "\n--- Custom Validation Chain ---\n";

    $customValidator = new FormValidator();
    $customResult = $customValidator
        ->rule('username', 'required')
        ->rule('username', 'min_length', 3)
        ->rule('username', 'max_length', 20)
        ->rule('username', 'regex', '/^[a-zA-Z0-9_]+$/')
        ->message('username', 'regex', 'ユーザー名は英数字とアンダースコアのみ使用可能です')
        ->validate(['username' => 'alice_123']);

    $customMessage = $customResult->toResult()
        ->map(fn ($data) => '✅ カスタムバリデーション成功: ' . $data['username'])
        ->unwrapOr('❌ カスタムバリデーション失敗');

    echo $customMessage . "\n";

    echo "\nForm validation example completed.\n";
}
