<?php

declare(strict_types=1);

/**
 * Complex Error Handling Implementation Example
 *
 * A practical example of safely managing complex workflows and error handling
 * using Result and Option types. Can be copied and pasted for use in actual projects.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use ba0918\Result\{Err, Ok, Result};

/**
 * Error level definition
 */
enum ErrorLevel: string
{
    case CRITICAL = 'critical';
    case ERROR = 'error';
    case WARNING = 'warning';
    case INFO = 'info';
}

/**
 * Error information class
 */
class ErrorInfo
{
    public function __construct(
        public readonly string $message,
        public readonly ErrorLevel $level,
        public readonly string $code = '',
        public readonly array $context = [],
        public readonly ?Throwable $previous = null,
    ) {
    }

    public function isCritical(): bool
    {
        return $this->level === ErrorLevel::CRITICAL;
    }

    public function isRetryable(): bool
    {
        return in_array($this->level, [ErrorLevel::WARNING, ErrorLevel::INFO]);
    }

    public function toString(): string
    {
        $context = empty($this->context) ? '' : ' Context: ' . json_encode($this->context);
        $code = empty($this->code) ? '' : " [{$this->code}]";

        return "[{$this->level->value}]{$code} {$this->message}{$context}";
    }
}

/**
 * Error accumulator
 */
class ErrorCollector
{
    private array $errors = [];

    public function add(ErrorInfo $error): void
    {
        $this->errors[] = $error;
    }

    public function addError(string $message, ErrorLevel $level = ErrorLevel::ERROR, array $context = []): void
    {
        $this->add(new ErrorInfo($message, $level, '', $context));
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function hasCriticalErrors(): bool
    {
        return !empty(array_filter($this->errors, fn ($e) => $e->isCritical()));
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getCriticalErrors(): array
    {
        return array_filter($this->errors, fn ($e) => $e->isCritical());
    }

    public function getErrorsByLevel(ErrorLevel $level): array
    {
        return array_filter($this->errors, fn ($e) => $e->level === $level);
    }

    public function clear(): void
    {
        $this->errors = [];
    }

    public function getSummary(): string
    {
        if (empty($this->errors)) {
            return 'No errors';
        }

        $counts = [];
        foreach (ErrorLevel::cases() as $level) {
            $count = count($this->getErrorsByLevel($level));
            if ($count > 0) {
                $counts[] = "{$level->value}: {$count}";
            }
        }

        return implode(', ', $counts);
    }

    public function toResult(): Result
    {
        if ($this->hasCriticalErrors()) {
            $critical = $this->getCriticalErrors()[0];

            return Err::of($critical->toString());
        }

        if ($this->hasErrors()) {
            return Err::of($this->getSummary());
        }

        return Ok::of('Processing completed');
    }
}

/**
 * Retry configuration
 */
class RetryConfig
{
    public function __construct(
        public readonly int $maxAttempts = 3,
        public readonly int $baseDelayMs = 1000,
        public readonly float $backoffMultiplier = 2.0,
        public readonly int $maxDelayMs = 30000,
    ) {
    }

    public function getDelay(int $attempt): int
    {
        $delay = (int) ($this->baseDelayMs * pow($this->backoffMultiplier, $attempt - 1));

        return min($delay, $this->maxDelayMs);
    }
}

/**
 * Resilient operation executor
 */
class ResilientExecutor
{
    public function __construct(
        private RetryConfig $retryConfig = new RetryConfig(),
    ) {
    }

    /**
     * Execute operation with retry functionality
     */
    public function execute(callable $operation, ?callable $shouldRetry = null): Result
    {
        $lastError = null;

        for ($attempt = 1; $attempt <= $this->retryConfig->maxAttempts; $attempt++) {
            $result = $this->executeOperation($operation);

            if ($result->isOk()) {
                return $result;
            }

            $error = $result->unwrapErr();
            $lastError = $error;

            // Retry determination
            if ($shouldRetry && !$shouldRetry($error, $attempt)) {
                break;
            }

            if ($attempt < $this->retryConfig->maxAttempts) {
                $delay = $this->retryConfig->getDelay($attempt);
                usleep($delay * 1000); // Convert to microseconds
            }
        }

        return Err::of("Maximum retry attempts reached. Last error: $lastError");
    }

    private function executeOperation(callable $operation): Result
    {
        try {
            $result = $operation();

            if ($result instanceof Result) {
                return $result;
            }

            return Ok::of($result);
        } catch (Throwable $e) {
            return Err::of($e->getMessage());
        }
    }
}

/**
 * Batch processor class
 */
class BatchProcessor
{
    private ErrorCollector $errorCollector;

    private ResilientExecutor $executor;

    public function __construct()
    {
        $this->errorCollector = new ErrorCollector();
        $this->executor = new ResilientExecutor();
    }

    /**
     * Batch process items
     */
    public function processItems(array $items, callable $processor): Result
    {
        $results = [];
        $this->errorCollector->clear();

        foreach ($items as $index => $item) {
            $result = $this->processItem($item, $processor, $index);

            if ($result->isOk()) {
                $results[] = $result->unwrap();
            } else {
                $this->errorCollector->addError(
                    "Failed to process item $index: " . $result->unwrapErr(),
                    ErrorLevel::ERROR,
                    ['index' => $index, 'item' => $item],
                );
            }
        }

        // If there are successful items, treat as partial success
        if (!empty($results) && !$this->errorCollector->hasCriticalErrors()) {
            if ($this->errorCollector->hasErrors()) {
                return Ok::of([
                    'results' => $results,
                    'partial_success' => true,
                    'errors' => $this->errorCollector->getSummary(),
                ]);
            }

            return Ok::of(['results' => $results, 'partial_success' => false]);
        }

        return $this->errorCollector->toResult();
    }

    /**
     * Concurrent batch processing (simulation)
     */
    public function processItemsConcurrently(array $items, callable $processor, int $maxConcurrency = 3): Result
    {
        $chunks = array_chunk($items, $maxConcurrency);
        $allResults = [];
        $this->errorCollector->clear();

        foreach ($chunks as $chunkIndex => $chunk) {
            $chunkResults = [];

            foreach ($chunk as $itemIndex => $item) {
                $globalIndex = $chunkIndex * $maxConcurrency + $itemIndex;
                $result = $this->processItem($item, $processor, $globalIndex);

                if ($result->isOk()) {
                    $chunkResults[] = $result->unwrap();
                } else {
                    $this->errorCollector->addError(
                        "Failed to process chunk $chunkIndex, item $itemIndex: " . $result->unwrapErr(),
                        ErrorLevel::ERROR,
                        ['chunk' => $chunkIndex, 'item_index' => $itemIndex, 'item' => $item],
                    );
                }
            }

            $allResults = array_merge($allResults, $chunkResults);
        }

        if (!empty($allResults) && !$this->errorCollector->hasCriticalErrors()) {
            return Ok::of([
                'results' => $allResults,
                'errors' => $this->errorCollector->getSummary(),
            ]);
        }

        return $this->errorCollector->toResult();
    }

    /**
     * Process single item
     */
    private function processItem(mixed $item, callable $processor, int $index): Result
    {
        return $this->executor->execute(
            fn () => $processor($item, $index),
            fn ($error, $attempt) => !str_contains($error, 'critical'),
        );
    }
}

/**
 * Workflow processor class
 */
class WorkflowProcessor
{
    private ErrorCollector $errorCollector;

    public function __construct()
    {
        $this->errorCollector = new ErrorCollector();
    }

    /**
     * Execute complex workflow
     */
    public function executeWorkflow(array $data): Result
    {
        $this->errorCollector->clear();

        return $this->validateInput($data)
            ->andThen(fn ($validData) => $this->preprocessData($validData))
            ->andThen(fn ($processedData) => $this->executeMainProcess($processedData))
            ->andThen(fn ($result) => $this->postProcess($result))
            ->andThen(fn ($finalResult) => $this->saveResults($finalResult))
            ->andThen(fn ($savedResult) => $this->sendNotifications($savedResult))
            ->map(fn ($result) => $this->addSuccessMetadata($result));
    }

    /**
     * Validate input data
     */
    private function validateInput(array $data): Result
    {
        $required = ['user_id', 'action', 'payload'];

        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return Err::of("Required field missing: $field");
            }
        }

        if (!is_numeric($data['user_id']) || $data['user_id'] <= 0) {
            return Err::of('user_id must be a positive integer');
        }

        $validActions = ['create', 'update', 'delete', 'process'];
        if (!in_array($data['action'], $validActions)) {
            return Err::of("Invalid action: {$data['action']}");
        }

        return Ok::of($data);
    }

    /**
     * Preprocess data
     */
    private function preprocessData(array $data): Result
    {
        try {
            $preprocessed = [
                'user_id' => (int) $data['user_id'],
                'action' => $data['action'],
                'payload' => $data['payload'],
                'timestamp' => time(),
                'request_id' => uniqid('req_', true),
            ];

            // Action-specific preprocessing
            return match ($data['action']) {
                'create' => $this->preprocessCreate($preprocessed),
                'update' => $this->preprocessUpdate($preprocessed),
                'delete' => $this->preprocessDelete($preprocessed),
                'process' => $this->preprocessProcess($preprocessed),
                default => Err::of("Unsupported action: {$data['action']}")
            };
        } catch (Throwable $e) {
            return Err::of('Preprocessing error: ' . $e->getMessage());
        }
    }

    private function preprocessCreate(array $data): Result
    {
        if (empty($data['payload']['name'])) {
            return Err::of('Create operation requires a name');
        }

        $data['payload']['created_at'] = date('Y-m-d H:i:s');

        return Ok::of($data);
    }

    private function preprocessUpdate(array $data): Result
    {
        if (empty($data['payload']['id'])) {
            return Err::of('Update operation requires an ID');
        }

        $data['payload']['updated_at'] = date('Y-m-d H:i:s');

        return Ok::of($data);
    }

    private function preprocessDelete(array $data): Result
    {
        if (empty($data['payload']['id'])) {
            return Err::of('Delete operation requires an ID');
        }

        $data['payload']['deleted_at'] = date('Y-m-d H:i:s');

        return Ok::of($data);
    }

    private function preprocessProcess(array $data): Result
    {
        if (empty($data['payload']['items'])) {
            return Err::of('Process operation requires items');
        }

        $data['payload']['processed_count'] = count($data['payload']['items']);

        return Ok::of($data);
    }

    /**
     * Execute main process
     */
    private function executeMainProcess(array $data): Result
    {
        $executor = new ResilientExecutor(new RetryConfig(maxAttempts: 3));

        return $executor->execute(
            fn () => $this->performMainProcess($data),
            fn ($error, $attempt) => !str_contains($error, 'fatal'),
        );
    }

    private function performMainProcess(array $data): Result
    {
        // Simulate processing success/failure randomly
        $successRate = match ($data['action']) {
            'create' => 0.9,
            'update' => 0.8,
            'delete' => 0.95,
            'process' => 0.7,
            default => 0.5
        };

        if (rand(1, 100) <= $successRate * 100) {
            $result = [
                'action' => $data['action'],
                'user_id' => $data['user_id'],
                'request_id' => $data['request_id'],
                'result' => "Processing successful: {$data['action']}",
                'processed_at' => date('Y-m-d H:i:s'),
                'payload' => $data['payload'],
            ];

            return Ok::of($result);
        }
        $errorType = rand(1, 100) <= 10 ? 'fatal' : 'recoverable';

        return Err::of("$errorType error in {$data['action']} processing");
    }

    /**
     * Post-process
     */
    private function postProcess(array $result): Result
    {
        try {
            $result['post_processed'] = true;
            $result['validation_hash'] = md5(json_encode($result['payload']));

            // Post-process validation
            if (empty($result['result'])) {
                return Err::of('Post-process: Processing result is empty');
            }

            return Ok::of($result);
        } catch (Throwable $e) {
            return Err::of('Post-processing error: ' . $e->getMessage());
        }
    }

    /**
     * Save results
     */
    private function saveResults(array $result): Result
    {
        try {
            // Simulate database save
            $saveSuccess = rand(1, 100) <= 90;

            if (!$saveSuccess) {
                return Err::of('Database save failed');
            }

            $result['saved'] = true;
            $result['save_id'] = 'save_' . uniqid();

            return Ok::of($result);
        } catch (Throwable $e) {
            return Err::of('Save error: ' . $e->getMessage());
        }
    }

    /**
     * Send notifications
     */
    private function sendNotifications(array $result): Result
    {
        try {
            // Simulate notification sending
            $notificationSuccess = rand(1, 100) <= 85;

            if (!$notificationSuccess) {
                // Notification failure is warning level (processing continues)
                $this->errorCollector->addError(
                    'Notification sending failed but processing continues',
                    ErrorLevel::WARNING,
                    ['request_id' => $result['request_id']],
                );
            }

            $result['notification_sent'] = $notificationSuccess;

            return Ok::of($result);
        } catch (Throwable $e) {
            // Notification error is warning level
            $this->errorCollector->addError(
                'Notification sending error: ' . $e->getMessage(),
                ErrorLevel::WARNING,
            );

            $result['notification_sent'] = false;

            return Ok::of($result);
        }
    }

    /**
     * Add success metadata
     */
    private function addSuccessMetadata(array $result): array
    {
        $result['success'] = true;
        $result['completed_at'] = date('Y-m-d H:i:s');
        $result['warnings'] = $this->errorCollector->getErrorsByLevel(ErrorLevel::WARNING);

        return $result;
    }
}

/**
 * Circuit breaker pattern
 */
class CircuitBreaker
{
    private int $failureCount = 0;

    private ?int $lastFailureTime = null;

    private string $state = 'closed'; // closed, open, half-open

    public function __construct(
        private int $failureThreshold = 5,
        private int $timeoutSeconds = 60,
    ) {
    }

    public function execute(callable $operation): Result
    {
        if ($this->state === 'open') {
            if ($this->shouldAttemptReset()) {
                $this->state = 'half-open';
            } else {
                return Err::of('Circuit breaker is open');
            }
        }

        try {
            $result = $operation();

            if ($result instanceof Result && $result->isErr()) {
                return $this->onFailure($result->unwrapErr());
            }

            $this->onSuccess();

            return $result instanceof Result ? $result : Ok::of($result);
        } catch (Throwable $e) {
            return $this->onFailure($e->getMessage());
        }
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function getFailureCount(): int
    {
        return $this->failureCount;
    }

    private function onSuccess(): void
    {
        $this->failureCount = 0;
        $this->state = 'closed';
    }

    private function onFailure(string $error): Result
    {
        $this->failureCount++;
        $this->lastFailureTime = time();

        if ($this->failureCount >= $this->failureThreshold) {
            $this->state = 'open';
        }

        return Err::of($error);
    }

    private function shouldAttemptReset(): bool
    {
        return $this->lastFailureTime !== null &&
               (time() - $this->lastFailureTime) >= $this->timeoutSeconds;
    }
}

// Usage examples
if ($_SERVER['SCRIPT_NAME'] === __FILE__) {
    echo "=== Complex Error Handling Example ===\n";

    // Batch processing example
    echo "\n--- Batch Processing ---\n";

    $batchProcessor = new BatchProcessor();

    $items = [
        ['id' => 1, 'name' => 'Item 1', 'value' => 100],
        ['id' => 2, 'name' => 'Item 2', 'value' => 200],
        ['id' => 3, 'name' => 'Item 3', 'value' => -50], // Error case
        ['id' => 4, 'name' => 'Item 4', 'value' => 300],
        ['id' => 5, 'name' => 'Item 5', 'value' => 400],
    ];

    $processor = function ($item, $index) {
        if ($item['value'] < 0) {
            return Err::of("Cannot process negative value: {$item['value']}");
        }

        // Simulate processing
        return Ok::of([
            'id' => $item['id'],
            'processed_name' => strtoupper($item['name']),
            'processed_value' => $item['value'] * 2,
            'processed_at' => date('Y-m-d H:i:s'),
        ]);
    };

    $batchResult = $batchProcessor->processItems($items, $processor);

    if ($batchResult->isOk()) {
        $result = $batchResult->unwrap();
        echo "✅ Batch processing completed\n";
        echo 'Processed items count: ' . count($result['results']) . "\n";

        if ($result['partial_success']) {
            echo '⚠️ Partial success: ' . $result['errors'] . "\n";
        }
    } else {
        echo '❌ Batch processing failed: ' . $batchResult->unwrapErr() . "\n";
    }

    // Workflow processing example
    echo "\n--- Workflow Processing ---\n";

    $workflowProcessor = new WorkflowProcessor();

    $workflowData = [
        'user_id' => 123,
        'action' => 'create',
        'payload' => [
            'name' => 'New Product',
            'description' => 'A great new product',
            'price' => 2999,
        ],
    ];

    $workflowResult = $workflowProcessor->executeWorkflow($workflowData);

    if ($workflowResult->isOk()) {
        $result = $workflowResult->unwrap();
        echo "✅ Workflow processing successful\n";
        echo 'Processing ID: ' . $result['request_id'] . "\n";
        echo 'Processing action: ' . $result['action'] . "\n";

        if (!empty($result['warnings'])) {
            echo '⚠️ Warnings: ' . count($result['warnings']) . " items\n";
        }
    } else {
        echo '❌ Workflow processing failed: ' . $workflowResult->unwrapErr() . "\n";
    }

    // Retry processing example
    echo "\n--- Resilient Execution ---\n";

    $executor = new ResilientExecutor(new RetryConfig(maxAttempts: 3, baseDelayMs: 100));

    $unreliableOperation = function () {
        static $attempts = 0;
        $attempts++;

        echo "Execution attempt: $attempts\n";

        // First 2 attempts fail, 3rd succeeds
        if ($attempts < 3) {
            return Err::of("Temporary error (attempt $attempts)");
        }

        return Ok::of("Success (attempt $attempts)");
    };

    $retryResult = $executor->execute($unreliableOperation);

    if ($retryResult->isOk()) {
        echo '✅ Retry processing successful: ' . $retryResult->unwrap() . "\n";
    } else {
        echo '❌ Retry processing failed: ' . $retryResult->unwrapErr() . "\n";
    }

    // Circuit breaker example
    echo "\n--- Circuit Breaker ---\n";

    $circuitBreaker = new CircuitBreaker(failureThreshold: 3, timeoutSeconds: 1);

    $flakyService = function () {
        static $callCount = 0;
        $callCount++;

        // First 4 calls fail
        if ($callCount <= 4) {
            throw new Exception("Service error (call $callCount)");
        }

        return "Service success (call $callCount)";
    };

    for ($i = 1; $i <= 8; $i++) {
        $cbResult = $circuitBreaker->execute($flakyService);

        echo "Call $i: ";
        if ($cbResult->isOk()) {
            echo '✅ ' . $cbResult->unwrap();
        } else {
            echo '❌ ' . $cbResult->unwrapErr();
        }
        echo " (state: {$circuitBreaker->getState()}, failures: {$circuitBreaker->getFailureCount()})\n";

        // Test reset after timeout
        if ($i === 6) {
            echo "Waiting 1 second (testing reset after timeout)\n";
            sleep(1);
        }
    }

    // Error collection example
    echo "\n--- Error Collection ---\n";

    $errorCollector = new ErrorCollector();

    // Add various levels of errors
    $errorCollector->addError('Minor issue', ErrorLevel::WARNING);
    $errorCollector->addError('Important issue', ErrorLevel::ERROR);
    $errorCollector->addError('System shutdown', ErrorLevel::CRITICAL);
    $errorCollector->addError('Information message', ErrorLevel::INFO);

    echo 'Error summary: ' . $errorCollector->getSummary() . "\n";
    echo 'Has critical errors: ' . ($errorCollector->hasCriticalErrors() ? 'Yes' : 'No') . "\n";

    $collectorResult = $errorCollector->toResult();
    if ($collectorResult->isErr()) {
        echo 'Error collector result: ' . $collectorResult->unwrapErr() . "\n";
    }

    echo "\nComplex error handling example completed.\n";
}
