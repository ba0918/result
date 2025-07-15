<?php

declare(strict_types=1);

/**
 * Configuration File Loading Implementation Example
 *
 * A practical example of safely loading and managing configuration files using Result and Option types.
 * Can be copied and pasted for use in actual projects.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Mizumi\Result\{Err, None, Ok, Option, Result, Some};

/**
 * Configuration value type definition
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
 * Configuration value class
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
     * Get as string
     */
    public function asString(): Option
    {
        if ($this->type === ConfigType::STRING) {
            return new Some((string) $this->value);
        }

        if (is_scalar($this->value)) {
            return new Some((string) $this->value);
        }

        return None::instance();
    }

    /**
     * Get as integer
     */
    public function asInt(): Option
    {
        if ($this->type === ConfigType::INTEGER) {
            return new Some((int) $this->value);
        }

        if (is_numeric($this->value)) {
            return new Some((int) $this->value);
        }

        return None::instance();
    }

    /**
     * Get as boolean
     */
    public function asBool(): Option
    {
        if ($this->type === ConfigType::BOOLEAN) {
            return new Some((bool) $this->value);
        }

        if (is_string($this->value)) {
            $lower = strtolower($this->value);
            if (in_array($lower, ['true', '1', 'yes', 'on'])) {
                return new Some(true);
            }
            if (in_array($lower, ['false', '0', 'no', 'off'])) {
                return new Some(false);
            }
        }

        return None::instance();
    }

    /**
     * Get as array
     */
    public function asArray(): Option
    {
        if ($this->type === ConfigType::ARRAY && is_array($this->value)) {
            return new Some($this->value);
        }

        return None::instance();
    }

    /**
     * Get as float
     */
    public function asFloat(): Option
    {
        if ($this->type === ConfigType::FLOAT) {
            return new Some((float) $this->value);
        }

        if (is_numeric($this->value)) {
            return new Some((float) $this->value);
        }

        return None::instance();
    }
}

/**
 * Configuration loader interface
 */
interface ConfigLoader
{
    public function load(string $path): Result;

    public function supports(string $path): bool;
}

/**
 * JSON configuration loader
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
            return new Err("Configuration file not found: $path");
        }

        if (!is_readable($path)) {
            return new Err("Configuration file not readable: $path");
        }

        return new Ok($path);
    }

    private function readFile(string $path): Result
    {
        $content = file_get_contents($path);

        if ($content === false) {
            return new Err("Failed to read file: $path");
        }

        return new Ok($content);
    }

    private function parseJson(string $content): Result
    {
        if (empty(trim($content))) {
            return new Ok([]);
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new Err('JSON parsing error: ' . json_last_error_msg());
        }

        return new Ok($data);
    }
}

/**
 * PHP configuration loader
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
            return new Err("Configuration file not found: $path");
        }

        if (!is_readable($path)) {
            return new Err("Configuration file not readable: $path");
        }

        return new Ok($path);
    }

    private function loadPhpFile(string $path): Result
    {
        try {
            $config = include $path;

            if (!is_array($config)) {
                return new Err("PHP configuration file must return an array: $path");
            }

            return new Ok($config);
        } catch (Throwable $e) {
            return new Err('PHP configuration file loading error: ' . $e->getMessage());
        }
    }
}

/**
 * Environment variable configuration loader
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
            return new Err("Environment file not found: $path");
        }

        return new Ok($path);
    }

    private function readFile(string $path): Result
    {
        $content = file_get_contents($path);

        if ($content === false) {
            return new Err("Failed to read environment file: $path");
        }

        return new Ok($content);
    }

    private function parseEnv(string $content): Result
    {
        $config = [];
        $lines = explode("\n", $content);

        foreach ($lines as $lineNumber => $line) {
            $line = trim($line);

            // Skip empty lines and comments
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

        return new Ok($config);
    }

    private function parseLine(string $line, int $lineNumber): Result
    {
        if (!str_contains($line, '=')) {
            return new Err("Line $lineNumber: Invalid format ('=' not found)");
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if (empty($key)) {
            return new Err("Line $lineNumber: Key is empty");
        }

        // Remove quotes
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }

        return new Ok([$key, $value]);
    }
}

/**
 * Configuration management class
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
     * Load configuration file
     */
    public function loadFile(string $path, string $namespace = ''): Result
    {
        $loader = $this->findLoader($path);

        if ($loader->isNone()) {
            return new Err("Unsupported file format: $path");
        }

        return $loader->unwrap()->load($path)
            ->andThen(fn ($data) => $this->mergeConfig($data, $namespace, $path));
    }

    /**
     * Load multiple configuration files
     */
    public function loadFiles(array $paths): Result
    {
        foreach ($paths as $path) {
            $result = $this->loadFile($path);
            if ($result->isErr()) {
                return $result;
            }
        }

        return new Ok($this->config);
    }

    /**
     * Batch load configuration files from directory
     */
    public function loadDirectory(string $directory): Result
    {
        if (!is_dir($directory)) {
            return new Err("Directory not found: $directory");
        }

        $files = glob($directory . '/*.{json,php,env}', GLOB_BRACE);

        if (empty($files)) {
            return new Err("No configuration files found: $directory");
        }

        return $this->loadFiles($files);
    }

    /**
     * Get configuration value
     */
    public function get(string $key, mixed $default = null): Option
    {
        $value = $this->getNestedValue($this->config, $key);

        if ($value === null && $default !== null) {
            return new Some($default);
        }

        if ($value === null) {
            return None::instance();
        }

        return new Some($value);
    }

    /**
     * Get type-safe configuration value
     */
    public function getTyped(string $key, ConfigType $type, mixed $default = null): Option
    {
        return $this->get($key, $default)
            ->andThen(fn ($value) => $this->convertType($value, $type));
    }

    /**
     * Get required configuration value
     */
    public function getRequired(string $key): Result
    {
        $value = $this->get($key);

        if ($value->isNone()) {
            return new Err("Required configuration not found: $key");
        }

        return new Ok($value->unwrap());
    }

    /**
     * Set configuration value
     */
    public function set(string $key, mixed $value): void
    {
        $this->setNestedValue($this->config, $key, $value);
    }

    /**
     * Check configuration value existence
     */
    public function has(string $key): bool
    {
        return $this->get($key)->isSome();
    }

    /**
     * Get all configuration
     */
    public function all(): array
    {
        return $this->config;
    }

    /**
     * Load configuration from environment variables
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

        return new Ok($this->config);
    }

    /**
     * Validate configuration
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
            return new Err('Configuration validation error: ' . implode(', ', $errors));
        }

        return new Ok($this->config);
    }

    private function findLoader(string $path): Option
    {
        foreach ($this->loaders as $loader) {
            if ($loader->supports($path)) {
                return new Some($loader);
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

        // Record source information
        $this->recordSources($data, $source, $namespace);

        return new Ok($this->config);
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
            ConfigType::STRING => new Some((string) $value),
            ConfigType::INTEGER => is_numeric($value) ? new Some((int) $value) : None::instance(),
            ConfigType::BOOLEAN => $this->convertToBool($value),
            ConfigType::ARRAY => is_array($value) ? new Some($value) : None::instance(),
            ConfigType::FLOAT => is_numeric($value) ? new Some((float) $value) : None::instance(),
        };
    }

    private function convertToBool(mixed $value): Option
    {
        if (is_bool($value)) {
            return new Some($value);
        }

        if (is_string($value)) {
            $lower = strtolower($value);
            if (in_array($lower, ['true', '1', 'yes', 'on'])) {
                return new Some(true);
            }
            if (in_array($lower, ['false', '0', 'no', 'off'])) {
                return new Some(false);
            }
        }

        return None::instance();
    }

    private function validateRule(string $key, array $rule): Result
    {
        $value = $this->get($key);

        // Required check
        if (isset($rule['required']) && $rule['required'] && $value->isNone()) {
            return new Err("Required configuration not found: $key");
        }

        if ($value->isNone()) {
            return new Ok(null);
        }

        $val = $value->unwrap();

        // Type check
        if (isset($rule['type'])) {
            $typeResult = $this->convertType($val, ConfigType::from($rule['type']));
            if ($typeResult->isNone()) {
                return new Err("Configuration $key has incorrect type (expected: {$rule['type']})");
            }
        }

        // Minimum value check
        if (isset($rule['min']) && is_numeric($val) && $val < $rule['min']) {
            return new Err("Configuration $key must be {$rule['min']} or greater");
        }

        // Maximum value check
        if (isset($rule['max']) && is_numeric($val) && $val > $rule['max']) {
            return new Err("Configuration $key must be {$rule['max']} or less");
        }

        return new Ok($val);
    }
}

// Usage examples
if ($_SERVER['SCRIPT_NAME'] === __FILE__) {
    echo "=== Config Loader Example ===\n";

    // Create sample configuration files
    $tempDir = sys_get_temp_dir() . '/config_example';
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }

    // JSON configuration file
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

    // PHP configuration file
    $phpConfig = "<?php\nreturn [\n    'cache' => [\n        'driver' => 'redis',\n        'ttl' => 3600\n    ],\n    'mail' => [\n        'driver' => 'smtp',\n        'host' => 'localhost'\n    ]\n];";
    file_put_contents($tempDir . '/services.php', $phpConfig);

    // ENV configuration file
    $envConfig = "APP_ENV=production\nLOG_LEVEL=info\nMAX_UPLOAD_SIZE=10485760\nFEATURE_FLAG=true\n";
    file_put_contents($tempDir . '/config.env', $envConfig);

    // ConfigManager usage example
    echo "\n--- Loading Configuration Files ---\n";

    $configManager = new ConfigManager();

    // Load individual files
    $loadResult = $configManager
        ->loadFile($tempDir . '/app.json')
        ->andThen(fn () => $configManager->loadFile($tempDir . '/services.php'))
        ->andThen(fn () => $configManager->loadFile($tempDir . '/config.env'));

    if ($loadResult->isOk()) {
        echo "✅ Configuration file loading successful\n";

        // Get configuration values
        echo "\n--- Configuration Values ---\n";

        $appName = $configManager->get('app.name')->unwrapOr('Unknown App');
        echo "App Name: $appName\n";

        $dbPort = $configManager->getTyped('database.port', ConfigType::INTEGER)->unwrapOr(3306);
        echo "Database Port: $dbPort\n";

        $isDebug = $configManager->getTyped('app.debug', ConfigType::BOOLEAN)->unwrapOr(false);
        echo 'Debug Mode: ' . ($isDebug ? 'true' : 'false') . "\n";

        $logLevel = $configManager->get('LOG_LEVEL')->unwrapOr('error');
        echo "Log Level: $logLevel\n";

        // Get required configuration
        $requiredResult = $configManager->getRequired('app.name');
        if ($requiredResult->isOk()) {
            echo 'Required Config: ' . $requiredResult->unwrap() . "\n";
        }

        // Check nested configuration
        echo "\n--- Nested Configuration ---\n";

        $cacheDriver = $configManager->get('cache.driver')->unwrapOr('file');
        echo "Cache Driver: $cacheDriver\n";

        $mailHost = $configManager->get('mail.host')->unwrapOr('localhost');
        echo "Mail Host: $mailHost\n";

        // Configuration existence check
        echo "\n--- Configuration Checks ---\n";

        echo 'Has app.name: ' . ($configManager->has('app.name') ? 'Yes' : 'No') . "\n";
        echo 'Has nonexistent.key: ' . ($configManager->has('nonexistent.key') ? 'Yes' : 'No') . "\n";
    } else {
        echo '❌ Configuration file loading failed: ' . $loadResult->unwrapErr() . "\n";
    }

    // Directory batch loading
    echo "\n--- Directory Loading ---\n";

    $dirConfigManager = new ConfigManager();
    $dirLoadResult = $dirConfigManager->loadDirectory($tempDir);

    if ($dirLoadResult->isOk()) {
        echo "✅ Batch loading from directory successful\n";
        $allConfig = $dirConfigManager->all();
        echo 'Total config keys: ' . count($allConfig, COUNT_RECURSIVE) . "\n";
    } else {
        echo '❌ Directory loading failed: ' . $dirLoadResult->unwrapErr() . "\n";
    }

    // Environment variable configuration
    echo "\n--- Environment Variables ---\n";

    $_ENV['APP_SECRET'] = 'secret-key-123';
    $_ENV['REDIS_URL'] = 'redis://localhost:6379';

    $envMapping = [
        'APP_SECRET' => 'app.secret',
        'REDIS_URL' => 'redis.url',
    ];

    $envResult = $configManager->loadFromEnvironment($envMapping);
    if ($envResult->isOk()) {
        echo "✅ Environment variable configuration loading successful\n";

        $secret = $configManager->get('app.secret')->unwrapOr('default-secret');
        echo 'App Secret: ' . substr($secret, 0, 6) . "...\n";

        $redisUrl = $configManager->get('redis.url')->unwrapOr('redis://localhost:6379');
        echo "Redis URL: $redisUrl\n";
    }

    // Configuration validation
    echo "\n--- Configuration Validation ---\n";

    $validationRules = [
        'app.name' => ['required' => true, 'type' => 'string'],
        'database.port' => ['required' => true, 'type' => 'integer', 'min' => 1, 'max' => 65535],
        'app.debug' => ['type' => 'boolean'],
        'nonexistent.key' => ['required' => false],
    ];

    $validationResult = $configManager->validate($validationRules);

    if ($validationResult->isOk()) {
        echo "✅ Configuration validation successful\n";
    } else {
        echo '❌ Configuration validation failed: ' . $validationResult->unwrapErr() . "\n";
    }

    // Error case testing
    echo "\n--- Error Handling Example ---\n";

    $errorConfigManager = new ConfigManager();
    $nonExistentResult = $errorConfigManager->loadFile('/nonexistent/path/config.json');

    if ($nonExistentResult->isErr()) {
        echo 'Expected error: ' . $nonExistentResult->unwrapErr() . "\n";
    }

    // Cleanup
    unlink($tempDir . '/app.json');
    unlink($tempDir . '/services.php');
    unlink($tempDir . '/config.env');
    rmdir($tempDir);

    echo "\nConfig loader example completed.\n";
}
