<?php

declare(strict_types=1);

/**
 * 設定ファイル読み込み処理の実装例
 *
 * Result型とOption型を使用して設定ファイルの読み込みと管理を安全に行う実用的な例です。
 * 実際のプロジェクトでコピー&ペーストして使用できます。
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use ba0918\Result\{Err, None, Ok, Option, Result, Some};

/**
 * 設定値の型定義
 */
enum ConfigType: string
{
    case STRING = 'string';
    case INTEGER = 'integer';
    case BOOLEAN = 'boolean';
    case ARRAY = 'array';
    case FLOAT = 'float';
}

/**
 * 設定値クラス
 */
class ConfigValue
{
    public function __construct(
        public readonly mixed $value,
        public readonly ConfigType $type,
        public readonly string $source = 'default',
    ) {
    }

    /**
     * 文字列として取得
     */
    public function asString(): Option
    {
        if ($this->type === ConfigType::STRING) {
            return Some::of((string) $this->value);
        }

        if (is_scalar($this->value)) {
            return Some::of((string) $this->value);
        }

        return None::instance();
    }

    /**
     * 整数として取得
     */
    public function asInt(): Option
    {
        if ($this->type === ConfigType::INTEGER) {
            return Some::of((int) $this->value);
        }

        if (is_numeric($this->value)) {
            return Some::of((int) $this->value);
        }

        return None::instance();
    }

    /**
     * 真偽値として取得
     */
    public function asBool(): Option
    {
        if ($this->type === ConfigType::BOOLEAN) {
            return Some::of((bool) $this->value);
        }

        if (is_string($this->value)) {
            $lower = strtolower($this->value);
            if (in_array($lower, ['true', '1', 'yes', 'on'])) {
                return Some::of(true);
            }
            if (in_array($lower, ['false', '0', 'no', 'off'])) {
                return Some::of(false);
            }
        }

        return None::instance();
    }

    /**
     * 配列として取得
     */
    public function asArray(): Option
    {
        if ($this->type === ConfigType::ARRAY && is_array($this->value)) {
            return Some::of($this->value);
        }

        return None::instance();
    }

    /**
     * 浮動小数点数として取得
     */
    public function asFloat(): Option
    {
        if ($this->type === ConfigType::FLOAT) {
            return Some::of((float) $this->value);
        }

        if (is_numeric($this->value)) {
            return Some::of((float) $this->value);
        }

        return None::instance();
    }
}

/**
 * 設定ローダーインターフェース
 */
interface ConfigLoader
{
    public function load(string $path): Result;

    public function supports(string $path): bool;
}

/**
 * JSON設定ローダー
 */
class JsonConfigLoader implements ConfigLoader
{
    public function load(string $path): Result
    {
        return $this->validatePath($path)
            ->andThen(fn ($p) => $this->readFile($p))
            ->andThen(fn ($content) => $this->parseJson($content));
    }

    public function supports(string $path): bool
    {
        return str_ends_with(strtolower($path), '.json');
    }

    private function validatePath(string $path): Result
    {
        if (!file_exists($path)) {
            return Err::of("設定ファイルが見つかりません: $path");
        }

        if (!is_readable($path)) {
            return Err::of("設定ファイルが読み込めません: $path");
        }

        return Ok::of($path);
    }

    private function readFile(string $path): Result
    {
        $content = file_get_contents($path);

        if ($content === false) {
            return Err::of("ファイルの読み込みに失敗しました: $path");
        }

        return Ok::of($content);
    }

    private function parseJson(string $content): Result
    {
        if (empty(trim($content))) {
            return Ok::of([]);
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return Err::of('JSON解析エラー: ' . json_last_error_msg());
        }

        return Ok::of($data);
    }
}

/**
 * PHP設定ローダー
 */
class PhpConfigLoader implements ConfigLoader
{
    public function load(string $path): Result
    {
        return $this->validatePath($path)
            ->andThen(fn ($p) => $this->loadPhpFile($p));
    }

    public function supports(string $path): bool
    {
        return str_ends_with(strtolower($path), '.php');
    }

    private function validatePath(string $path): Result
    {
        if (!file_exists($path)) {
            return Err::of("設定ファイルが見つかりません: $path");
        }

        if (!is_readable($path)) {
            return Err::of("設定ファイルが読み込めません: $path");
        }

        return Ok::of($path);
    }

    private function loadPhpFile(string $path): Result
    {
        try {
            $config = include $path;

            if (!is_array($config)) {
                return Err::of("PHP設定ファイルは配列を返す必要があります: $path");
            }

            return Ok::of($config);
        } catch (Throwable $e) {
            return Err::of('PHP設定ファイルの読み込みエラー: ' . $e->getMessage());
        }
    }
}

/**
 * 環境変数設定ローダー
 */
class EnvConfigLoader implements ConfigLoader
{
    public function load(string $path): Result
    {
        return $this->validatePath($path)
            ->andThen(fn ($p) => $this->readFile($p))
            ->andThen(fn ($content) => $this->parseEnv($content));
    }

    public function supports(string $path): bool
    {
        return str_ends_with(strtolower($path), '.env');
    }

    private function validatePath(string $path): Result
    {
        if (!file_exists($path)) {
            return Err::of("環境変数ファイルが見つかりません: $path");
        }

        return Ok::of($path);
    }

    private function readFile(string $path): Result
    {
        $content = file_get_contents($path);

        if ($content === false) {
            return Err::of("環境変数ファイルの読み込みに失敗しました: $path");
        }

        return Ok::of($content);
    }

    private function parseEnv(string $content): Result
    {
        $config = [];
        $lines = explode("\n", $content);

        foreach ($lines as $lineNumber => $line) {
            $line = trim($line);

            // 空行やコメント行をスキップ
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            $result = $this->parseLine($line, $lineNumber + 1);
            if ($result->isErr()) {
                return $result;
            }

            [$key, $value] = $result->unwrap();
            $config[$key] = $value;
        }

        return Ok::of($config);
    }

    private function parseLine(string $line, int $lineNumber): Result
    {
        if (!str_contains($line, '=')) {
            return Err::of("行 $lineNumber: 無効な形式（'='が見つかりません）");
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if (empty($key)) {
            return Err::of("行 $lineNumber: キーが空です");
        }

        // クォートを除去
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }

        return Ok::of([$key, $value]);
    }
}

/**
 * 設定管理クラス
 */
class ConfigManager
{
    private array $config = [];

    private array $loaders = [];

    private array $sources = [];

    public function __construct()
    {
        $this->loaders = [
            new JsonConfigLoader(),
            new PhpConfigLoader(),
            new EnvConfigLoader(),
        ];
    }

    /**
     * 設定ファイルを読み込み
     */
    public function loadFile(string $path, string $namespace = ''): Result
    {
        $loader = $this->findLoader($path);

        if ($loader->isNone()) {
            return Err::of("サポートされていないファイル形式: $path");
        }

        return $loader->unwrap()->load($path)
            ->andThen(fn ($data) => $this->mergeConfig($data, $namespace, $path));
    }

    /**
     * 複数の設定ファイルを読み込み
     */
    public function loadFiles(array $paths): Result
    {
        foreach ($paths as $path) {
            $result = $this->loadFile($path);
            if ($result->isErr()) {
                return $result;
            }
        }

        return Ok::of($this->config);
    }

    /**
     * ディレクトリ内の設定ファイルを一括読み込み
     */
    public function loadDirectory(string $directory): Result
    {
        if (!is_dir($directory)) {
            return Err::of("ディレクトリが見つかりません: $directory");
        }

        $files = glob($directory . '/*.{json,php,env}', GLOB_BRACE);

        if (empty($files)) {
            return Err::of("設定ファイルが見つかりません: $directory");
        }

        return $this->loadFiles($files);
    }

    /**
     * 設定値を取得
     */
    public function get(string $key, mixed $default = null): Option
    {
        $value = $this->getNestedValue($this->config, $key);

        if ($value === null && $default !== null) {
            return Some::of($default);
        }

        if ($value === null) {
            return None::instance();
        }

        return Some::of($value);
    }

    /**
     * 型安全な設定値取得
     */
    public function getTyped(string $key, ConfigType $type, mixed $default = null): Option
    {
        return $this->get($key, $default)
            ->andThen(fn ($value) => $this->convertType($value, $type));
    }

    /**
     * 必須設定値を取得
     */
    public function getRequired(string $key): Result
    {
        $value = $this->get($key);

        if ($value->isNone()) {
            return Err::of("必須設定が見つかりません: $key");
        }

        return Ok::of($value->unwrap());
    }

    /**
     * 設定値を設定
     */
    public function set(string $key, mixed $value): void
    {
        $this->setNestedValue($this->config, $key, $value);
    }

    /**
     * 設定値の存在確認
     */
    public function has(string $key): bool
    {
        return $this->get($key)->isSome();
    }

    /**
     * 全設定を取得
     */
    public function all(): array
    {
        return $this->config;
    }

    /**
     * 環境変数から設定を読み込み
     */
    public function loadFromEnvironment(array $mapping = []): Result
    {
        foreach ($mapping as $envKey => $configKey) {
            $value = $_ENV[$envKey] ?? getenv($envKey);
            if ($value !== false) {
                $this->set($configKey, $value);
                $this->sources[$configKey] = 'environment';
            }
        }

        return Ok::of($this->config);
    }

    /**
     * 設定のバリデーション
     */
    public function validate(array $rules): Result
    {
        $errors = [];

        foreach ($rules as $key => $rule) {
            $result = $this->validateRule($key, $rule);
            if ($result->isErr()) {
                $errors[$key] = $result->unwrapErr();
            }
        }

        if (!empty($errors)) {
            return Err::of('設定バリデーションエラー: ' . implode(', ', $errors));
        }

        return Ok::of($this->config);
    }

    private function findLoader(string $path): Option
    {
        foreach ($this->loaders as $loader) {
            if ($loader->supports($path)) {
                return Some::of($loader);
            }
        }

        return None::instance();
    }

    private function mergeConfig(array $data, string $namespace, string $source): Result
    {
        if (empty($namespace)) {
            $this->config = array_merge($this->config, $data);
        } else {
            $this->config[$namespace] = array_merge(
                $this->config[$namespace] ?? [],
                $data,
            );
        }

        // ソース情報を記録
        $this->recordSources($data, $source, $namespace);

        return Ok::of($this->config);
    }

    private function recordSources(array $data, string $source, string $namespace): void
    {
        $prefix = empty($namespace) ? '' : $namespace . '.';

        foreach ($data as $key => $value) {
            $fullKey = $prefix . $key;
            $this->sources[$fullKey] = $source;

            if (is_array($value)) {
                $this->recordSources($value, $source, $fullKey);
            }
        }
    }

    private function getNestedValue(array $array, string $key): mixed
    {
        if (str_contains($key, '.')) {
            $keys = explode('.', $key);
            $current = $array;

            foreach ($keys as $nestedKey) {
                if (!is_array($current) || !array_key_exists($nestedKey, $current)) {
                    return null;
                }
                $current = $current[$nestedKey];
            }

            return $current;
        }

        return $array[$key] ?? null;
    }

    private function setNestedValue(array &$array, string $key, mixed $value): void
    {
        if (str_contains($key, '.')) {
            $keys = explode('.', $key);
            $current = &$array;

            foreach ($keys as $nestedKey) {
                if (!isset($current[$nestedKey]) || !is_array($current[$nestedKey])) {
                    $current[$nestedKey] = [];
                }
                $current = &$current[$nestedKey];
            }

            $current = $value;
        } else {
            $array[$key] = $value;
        }
    }

    private function convertType(mixed $value, ConfigType $type): Option
    {
        return match ($type) {
            ConfigType::STRING => Some::of((string) $value),
            ConfigType::INTEGER => is_numeric($value) ? Some::of((int) $value) : None::instance(),
            ConfigType::BOOLEAN => $this->convertToBool($value),
            ConfigType::ARRAY => is_array($value) ? Some::of($value) : None::instance(),
            ConfigType::FLOAT => is_numeric($value) ? Some::of((float) $value) : None::instance(),
        };
    }

    private function convertToBool(mixed $value): Option
    {
        if (is_bool($value)) {
            return Some::of($value);
        }

        if (is_string($value)) {
            $lower = strtolower($value);
            if (in_array($lower, ['true', '1', 'yes', 'on'])) {
                return Some::of(true);
            }
            if (in_array($lower, ['false', '0', 'no', 'off'])) {
                return Some::of(false);
            }
        }

        return None::instance();
    }

    private function validateRule(string $key, array $rule): Result
    {
        $value = $this->get($key);

        // 必須チェック
        if (isset($rule['required']) && $rule['required'] && $value->isNone()) {
            return Err::of("必須設定が見つかりません: $key");
        }

        if ($value->isNone()) {
            return Ok::of(null);
        }

        $val = $value->unwrap();

        // 型チェック
        if (isset($rule['type'])) {
            $typeResult = $this->convertType($val, ConfigType::from($rule['type']));
            if ($typeResult->isNone()) {
                return Err::of("設定 $key の型が正しくありません（期待: {$rule['type']}）");
            }
        }

        // 最小値チェック
        if (isset($rule['min']) && is_numeric($val) && $val < $rule['min']) {
            return Err::of("設定 $key は {$rule['min']} 以上である必要があります");
        }

        // 最大値チェック
        if (isset($rule['max']) && is_numeric($val) && $val > $rule['max']) {
            return Err::of("設定 $key は {$rule['max']} 以下である必要があります");
        }

        return Ok::of($val);
    }
}

// 使用例
if ($_SERVER['SCRIPT_NAME'] === __FILE__) {
    echo "=== Config Loader Example ===\n";

    // サンプル設定ファイルを作成
    $tempDir = sys_get_temp_dir() . '/config_example';
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }

    // JSON設定ファイル
    $jsonConfig = [
        'app' => [
            'name' => 'Example App',
            'version' => '1.0.0',
            'debug' => true,
        ],
        'database' => [
            'host' => 'localhost',
            'port' => 3306,
            'name' => 'example_db',
        ],
    ];
    file_put_contents($tempDir . '/app.json', json_encode($jsonConfig, JSON_PRETTY_PRINT));

    // PHP設定ファイル
    $phpConfig = "<?php\nreturn [\n    'cache' => [\n        'driver' => 'redis',\n        'ttl' => 3600\n    ],\n    'mail' => [\n        'driver' => 'smtp',\n        'host' => 'localhost'\n    ]\n];";
    file_put_contents($tempDir . '/services.php', $phpConfig);

    // ENV設定ファイル
    $envConfig = "APP_ENV=production\nLOG_LEVEL=info\nMAX_UPLOAD_SIZE=10485760\nFEATURE_FLAG=true\n";
    file_put_contents($tempDir . '/config.env', $envConfig);

    // ConfigManagerの使用例
    echo "\n--- Loading Configuration Files ---\n";

    $configManager = new ConfigManager();

    // 個別ファイル読み込み
    $loadResult = $configManager
        ->loadFile($tempDir . '/app.json')
        ->andThen(fn () => $configManager->loadFile($tempDir . '/services.php'))
        ->andThen(fn () => $configManager->loadFile($tempDir . '/config.env'));

    if ($loadResult->isOk()) {
        echo "✅ 設定ファイルの読み込み成功\n";

        // 設定値の取得
        echo "\n--- Configuration Values ---\n";

        $appName = $configManager->get('app.name')->unwrapOr('Unknown App');
        echo "App Name: $appName\n";

        $dbPort = $configManager->getTyped('database.port', ConfigType::INTEGER)->unwrapOr(3306);
        echo "Database Port: $dbPort\n";

        $isDebug = $configManager->getTyped('app.debug', ConfigType::BOOLEAN)->unwrapOr(false);
        echo 'Debug Mode: ' . ($isDebug ? 'true' : 'false') . "\n";

        $logLevel = $configManager->get('LOG_LEVEL')->unwrapOr('error');
        echo "Log Level: $logLevel\n";

        // 必須設定の取得
        $requiredResult = $configManager->getRequired('app.name');
        if ($requiredResult->isOk()) {
            echo 'Required Config: ' . $requiredResult->unwrap() . "\n";
        }

        // ネストした設定の確認
        echo "\n--- Nested Configuration ---\n";

        $cacheDriver = $configManager->get('cache.driver')->unwrapOr('file');
        echo "Cache Driver: $cacheDriver\n";

        $mailHost = $configManager->get('mail.host')->unwrapOr('localhost');
        echo "Mail Host: $mailHost\n";

        // 設定の存在確認
        echo "\n--- Configuration Checks ---\n";

        echo 'Has app.name: ' . ($configManager->has('app.name') ? 'Yes' : 'No') . "\n";
        echo 'Has nonexistent.key: ' . ($configManager->has('nonexistent.key') ? 'Yes' : 'No') . "\n";
    } else {
        echo '❌ 設定ファイルの読み込み失敗: ' . $loadResult->unwrapErr() . "\n";
    }

    // ディレクトリ一括読み込み
    echo "\n--- Directory Loading ---\n";

    $dirConfigManager = new ConfigManager();
    $dirLoadResult = $dirConfigManager->loadDirectory($tempDir);

    if ($dirLoadResult->isOk()) {
        echo "✅ ディレクトリからの一括読み込み成功\n";
        $allConfig = $dirConfigManager->all();
        echo 'Total config keys: ' . count($allConfig, COUNT_RECURSIVE) . "\n";
    } else {
        echo '❌ ディレクトリ読み込み失敗: ' . $dirLoadResult->unwrapErr() . "\n";
    }

    // 環境変数設定
    echo "\n--- Environment Variables ---\n";

    $_ENV['APP_SECRET'] = 'secret-key-123';
    $_ENV['REDIS_URL'] = 'redis://localhost:6379';

    $envMapping = [
        'APP_SECRET' => 'app.secret',
        'REDIS_URL' => 'redis.url',
    ];

    $envResult = $configManager->loadFromEnvironment($envMapping);
    if ($envResult->isOk()) {
        echo "✅ 環境変数設定の読み込み成功\n";

        $secret = $configManager->get('app.secret')->unwrapOr('default-secret');
        echo 'App Secret: ' . substr($secret, 0, 6) . "...\n";

        $redisUrl = $configManager->get('redis.url')->unwrapOr('redis://localhost:6379');
        echo "Redis URL: $redisUrl\n";
    }

    // 設定バリデーション
    echo "\n--- Configuration Validation ---\n";

    $validationRules = [
        'app.name' => ['required' => true, 'type' => 'string'],
        'database.port' => ['required' => true, 'type' => 'integer', 'min' => 1, 'max' => 65535],
        'app.debug' => ['type' => 'boolean'],
        'nonexistent.key' => ['required' => false],
    ];

    $validationResult = $configManager->validate($validationRules);

    if ($validationResult->isOk()) {
        echo "✅ 設定バリデーション成功\n";
    } else {
        echo '❌ 設定バリデーション失敗: ' . $validationResult->unwrapErr() . "\n";
    }

    // エラーケースのテスト
    echo "\n--- Error Handling Example ---\n";

    $errorConfigManager = new ConfigManager();
    $nonExistentResult = $errorConfigManager->loadFile('/nonexistent/path/config.json');

    if ($nonExistentResult->isErr()) {
        echo '期待通りのエラー: ' . $nonExistentResult->unwrapErr() . "\n";
    }

    // クリーンアップ
    unlink($tempDir . '/app.json');
    unlink($tempDir . '/services.php');
    unlink($tempDir . '/config.env');
    rmdir($tempDir);

    echo "\nConfig loader example completed.\n";
}
