<?php

declare(strict_types=1);

/**
 * 複雑なエラーハンドリングの実装例
 *
 * Result型とOption型を使用して複雑なワークフローとエラーハンドリングを
 * 安全に管理する実用的な例です。実際のプロジェクトでコピー&ペーストして使用できます。
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Mizumi\Result\{Err, Ok, Result};

/**
 * エラーレベル定義
 */
enum ErrorLevel: string
{
    case CRITICAL = 'critical';
    case ERROR = 'error';
    case WARNING = 'warning';
    case INFO = 'info';
}

/**
 * エラー情報クラス
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
 * エラー蓄積器
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
            return 'エラーはありません';
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

            return new Err($critical->toString());
        }

        if ($this->hasErrors()) {
            return new Err($this->getSummary());
        }

        return new Ok('処理完了');
    }
}

/**
 * リトライ設定
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
 * 回復可能な操作の実行
 */
class ResilientExecutor
{
    public function __construct(
        private RetryConfig $retryConfig = new RetryConfig(),
    ) {
    }

    /**
     * リトライ機能付きで操作を実行
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

            // リトライ判定
            if ($shouldRetry && !$shouldRetry($error, $attempt)) {
                break;
            }

            if ($attempt < $this->retryConfig->maxAttempts) {
                $delay = $this->retryConfig->getDelay($attempt);
                usleep($delay * 1000); // マイクロ秒に変換
            }
        }

        return new Err("最大試行回数に達しました。最後のエラー: $lastError");
    }

    private function executeOperation(callable $operation): Result
    {
        try {
            $result = $operation();

            if ($result instanceof Result) {
                return $result;
            }

            return new Ok($result);
        } catch (Throwable $e) {
            return new Err($e->getMessage());
        }
    }
}

/**
 * バッチ処理クラス
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
     * アイテムのバッチ処理
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
                    "アイテム $index の処理に失敗: " . $result->unwrapErr(),
                    ErrorLevel::ERROR,
                    ['index' => $index, 'item' => $item],
                );
            }
        }

        // 成功したアイテムがある場合は部分成功として処理
        if (!empty($results) && !$this->errorCollector->hasCriticalErrors()) {
            if ($this->errorCollector->hasErrors()) {
                return new Ok([
                    'results' => $results,
                    'partial_success' => true,
                    'errors' => $this->errorCollector->getSummary(),
                ]);
            }

            return new Ok(['results' => $results, 'partial_success' => false]);
        }

        return $this->errorCollector->toResult();
    }

    /**
     * 並行バッチ処理（シミュレーション）
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
                        "チャンク $chunkIndex, アイテム $itemIndex の処理に失敗: " . $result->unwrapErr(),
                        ErrorLevel::ERROR,
                        ['chunk' => $chunkIndex, 'item_index' => $itemIndex, 'item' => $item],
                    );
                }
            }

            $allResults = array_merge($allResults, $chunkResults);
        }

        if (!empty($allResults) && !$this->errorCollector->hasCriticalErrors()) {
            return new Ok([
                'results' => $allResults,
                'errors' => $this->errorCollector->getSummary(),
            ]);
        }

        return $this->errorCollector->toResult();
    }

    /**
     * 単一アイテムの処理
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
 * ワークフロー処理クラス
 */
class WorkflowProcessor
{
    private ErrorCollector $errorCollector;

    public function __construct()
    {
        $this->errorCollector = new ErrorCollector();
    }

    /**
     * 複雑なワークフローを実行
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
     * 入力データの検証
     */
    private function validateInput(array $data): Result
    {
        $required = ['user_id', 'action', 'payload'];

        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return new Err("必須フィールドが不足しています: $field");
            }
        }

        if (!is_numeric($data['user_id']) || $data['user_id'] <= 0) {
            return new Err('user_id は正の整数である必要があります');
        }

        $validActions = ['create', 'update', 'delete', 'process'];
        if (!in_array($data['action'], $validActions)) {
            return new Err("無効なアクション: {$data['action']}");
        }

        return new Ok($data);
    }

    /**
     * データの前処理
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

            // アクション固有の前処理
            return match ($data['action']) {
                'create' => $this->preprocessCreate($preprocessed),
                'update' => $this->preprocessUpdate($preprocessed),
                'delete' => $this->preprocessDelete($preprocessed),
                'process' => $this->preprocessProcess($preprocessed),
                default => new Err("未対応のアクション: {$data['action']}")
            };
        } catch (Throwable $e) {
            return new Err('前処理エラー: ' . $e->getMessage());
        }
    }

    private function preprocessCreate(array $data): Result
    {
        if (empty($data['payload']['name'])) {
            return new Err('作成処理には名前が必要です');
        }

        $data['payload']['created_at'] = date('Y-m-d H:i:s');

        return new Ok($data);
    }

    private function preprocessUpdate(array $data): Result
    {
        if (empty($data['payload']['id'])) {
            return new Err('更新処理にはIDが必要です');
        }

        $data['payload']['updated_at'] = date('Y-m-d H:i:s');

        return new Ok($data);
    }

    private function preprocessDelete(array $data): Result
    {
        if (empty($data['payload']['id'])) {
            return new Err('削除処理にはIDが必要です');
        }

        $data['payload']['deleted_at'] = date('Y-m-d H:i:s');

        return new Ok($data);
    }

    private function preprocessProcess(array $data): Result
    {
        if (empty($data['payload']['items'])) {
            return new Err('処理には items が必要です');
        }

        $data['payload']['processed_count'] = count($data['payload']['items']);

        return new Ok($data);
    }

    /**
     * メイン処理の実行
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
        // 処理の成功/失敗をランダムにシミュレート
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
                'result' => "処理成功: {$data['action']}",
                'processed_at' => date('Y-m-d H:i:s'),
                'payload' => $data['payload'],
            ];

            return new Ok($result);
        }
        $errorType = rand(1, 100) <= 10 ? 'fatal' : 'recoverable';

        return new Err("$errorType error in {$data['action']} processing");
    }

    /**
     * 後処理
     */
    private function postProcess(array $result): Result
    {
        try {
            $result['post_processed'] = true;
            $result['validation_hash'] = md5(json_encode($result['payload']));

            // 後処理バリデーション
            if (empty($result['result'])) {
                return new Err('後処理: 処理結果が空です');
            }

            return new Ok($result);
        } catch (Throwable $e) {
            return new Err('後処理エラー: ' . $e->getMessage());
        }
    }

    /**
     * 結果の保存
     */
    private function saveResults(array $result): Result
    {
        try {
            // データベース保存をシミュレート
            $saveSuccess = rand(1, 100) <= 90;

            if (!$saveSuccess) {
                return new Err('データベース保存に失敗しました');
            }

            $result['saved'] = true;
            $result['save_id'] = 'save_' . uniqid();

            return new Ok($result);
        } catch (Throwable $e) {
            return new Err('保存エラー: ' . $e->getMessage());
        }
    }

    /**
     * 通知送信
     */
    private function sendNotifications(array $result): Result
    {
        try {
            // 通知送信をシミュレート
            $notificationSuccess = rand(1, 100) <= 85;

            if (!$notificationSuccess) {
                // 通知失敗は警告レベル（処理は続行）
                $this->errorCollector->addError(
                    '通知送信に失敗しましたが、処理は継続します',
                    ErrorLevel::WARNING,
                    ['request_id' => $result['request_id']],
                );
            }

            $result['notification_sent'] = $notificationSuccess;

            return new Ok($result);
        } catch (Throwable $e) {
            // 通知エラーは警告レベル
            $this->errorCollector->addError(
                '通知送信エラー: ' . $e->getMessage(),
                ErrorLevel::WARNING,
            );

            $result['notification_sent'] = false;

            return new Ok($result);
        }
    }

    /**
     * 成功メタデータの追加
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
 * サーキットブレーカーパターン
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
                return new Err('サーキットブレーカーが開いています');
            }
        }

        try {
            $result = $operation();

            if ($result instanceof Result && $result->isErr()) {
                return $this->onFailure($result->unwrapErr());
            }

            $this->onSuccess();

            return $result instanceof Result ? $result : new Ok($result);
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

        return new Err($error);
    }

    private function shouldAttemptReset(): bool
    {
        return $this->lastFailureTime !== null &&
               (time() - $this->lastFailureTime) >= $this->timeoutSeconds;
    }
}

// 使用例
if ($_SERVER['SCRIPT_NAME'] === __FILE__) {
    echo "=== Complex Error Handling Example ===\n";

    // バッチ処理の例
    echo "\n--- Batch Processing ---\n";

    $batchProcessor = new BatchProcessor();

    $items = [
        ['id' => 1, 'name' => 'Item 1', 'value' => 100],
        ['id' => 2, 'name' => 'Item 2', 'value' => 200],
        ['id' => 3, 'name' => 'Item 3', 'value' => -50], // エラーケース
        ['id' => 4, 'name' => 'Item 4', 'value' => 300],
        ['id' => 5, 'name' => 'Item 5', 'value' => 400],
    ];

    $processor = function ($item, $index) {
        if ($item['value'] < 0) {
            return new Err("負の値は処理できません: {$item['value']}");
        }

        // 処理をシミュレート
        return new Ok([
            'id' => $item['id'],
            'processed_name' => strtoupper($item['name']),
            'processed_value' => $item['value'] * 2,
            'processed_at' => date('Y-m-d H:i:s'),
        ]);
    };

    $batchResult = $batchProcessor->processItems($items, $processor);

    if ($batchResult->isOk()) {
        $result = $batchResult->unwrap();
        echo "✅ バッチ処理完了\n";
        echo '処理済みアイテム数: ' . count($result['results']) . "\n";

        if ($result['partial_success']) {
            echo '⚠️ 部分的成功: ' . $result['errors'] . "\n";
        }
    } else {
        echo '❌ バッチ処理失敗: ' . $batchResult->unwrapErr() . "\n";
    }

    // ワークフロー処理の例
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
        echo "✅ ワークフロー処理成功\n";
        echo '処理ID: ' . $result['request_id'] . "\n";
        echo '処理アクション: ' . $result['action'] . "\n";

        if (!empty($result['warnings'])) {
            echo '⚠️ 警告: ' . count($result['warnings']) . " 件\n";
        }
    } else {
        echo '❌ ワークフロー処理失敗: ' . $workflowResult->unwrapErr() . "\n";
    }

    // リトライ処理の例
    echo "\n--- Resilient Execution ---\n";

    $executor = new ResilientExecutor(new RetryConfig(maxAttempts: 3, baseDelayMs: 100));

    $unreliableOperation = function () {
        static $attempts = 0;
        $attempts++;

        echo "実行試行: $attempts\n";

        // 最初の2回は失敗、3回目で成功
        if ($attempts < 3) {
            return new Err("一時的なエラー (試行 $attempts)");
        }

        return new Ok("成功 (試行 $attempts)");
    };

    $retryResult = $executor->execute($unreliableOperation);

    if ($retryResult->isOk()) {
        echo '✅ リトライ処理成功: ' . $retryResult->unwrap() . "\n";
    } else {
        echo '❌ リトライ処理失敗: ' . $retryResult->unwrapErr() . "\n";
    }

    // サーキットブレーカーの例
    echo "\n--- Circuit Breaker ---\n";

    $circuitBreaker = new CircuitBreaker(failureThreshold: 3, timeoutSeconds: 1);

    $flakyService = function () {
        static $callCount = 0;
        $callCount++;

        // 最初の4回は失敗
        if ($callCount <= 4) {
            throw new Exception("サービスエラー (呼び出し $callCount)");
        }

        return "サービス成功 (呼び出し $callCount)";
    };

    for ($i = 1; $i <= 8; $i++) {
        $cbResult = $circuitBreaker->execute($flakyService);

        echo "呼び出し $i: ";
        if ($cbResult->isOk()) {
            echo '✅ ' . $cbResult->unwrap();
        } else {
            echo '❌ ' . $cbResult->unwrapErr();
        }
        echo " (状態: {$circuitBreaker->getState()}, 失敗数: {$circuitBreaker->getFailureCount()})\n";

        // タイムアウト後のリセットをテスト
        if ($i === 6) {
            echo "1秒待機（タイムアウト後のリセットテスト）\n";
            sleep(1);
        }
    }

    // エラー収集の例
    echo "\n--- Error Collection ---\n";

    $errorCollector = new ErrorCollector();

    // 様々なレベルのエラーを追加
    $errorCollector->addError('軽微な問題', ErrorLevel::WARNING);
    $errorCollector->addError('重要な問題', ErrorLevel::ERROR);
    $errorCollector->addError('システム停止', ErrorLevel::CRITICAL);
    $errorCollector->addError('情報メッセージ', ErrorLevel::INFO);

    echo 'エラーサマリー: ' . $errorCollector->getSummary() . "\n";
    echo 'クリティカルエラーあり: ' . ($errorCollector->hasCriticalErrors() ? 'Yes' : 'No') . "\n";

    $collectorResult = $errorCollector->toResult();
    if ($collectorResult->isErr()) {
        echo 'エラーコレクター結果: ' . $collectorResult->unwrapErr() . "\n";
    }

    echo "\nComplex error handling example completed.\n";
}
