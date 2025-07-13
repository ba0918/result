<?php declare(strict_types=1);

/**
 * HTTP APIクライアントの実装例
 * 
 * Result型を使用してHTTP API呼び出しを安全に処理する実用的な例です。
 * 実際のプロジェクトでコピー&ペーストして使用できます。
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Mizumi\Result\{Ok, Err, Result};

/**
 * HTTP APIクライアント
 * 
 * Result型を使用してAPI呼び出しのエラーハンドリングを安全に行います。
 */
class ApiClient
{
    private string $baseUrl;
    private array $defaultHeaders;
    private int $timeout;
    
    public function __construct(
        string $baseUrl,
        array $defaultHeaders = [],
        int $timeout = 30
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->defaultHeaders = array_merge([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ], $defaultHeaders);
        $this->timeout = $timeout;
    }
    
    /**
     * GET リクエストを実行
     * 
     * @param string $endpoint エンドポイント（例: "/users/123"）
     * @param array $headers 追加ヘッダー
     * @return Result<array, string> 成功時はレスポンスデータ、失敗時はエラーメッセージ
     */
    public function get(string $endpoint, array $headers = []): Result
    {
        return $this->request('GET', $endpoint, null, $headers);
    }
    
    /**
     * POST リクエストを実行
     * 
     * @param string $endpoint エンドポイント
     * @param array|null $data 送信データ
     * @param array $headers 追加ヘッダー
     * @return Result<array, string>
     */
    public function post(string $endpoint, ?array $data = null, array $headers = []): Result
    {
        return $this->request('POST', $endpoint, $data, $headers);
    }
    
    /**
     * PUT リクエストを実行
     * 
     * @param string $endpoint エンドポイント
     * @param array|null $data 送信データ
     * @param array $headers 追加ヘッダー
     * @return Result<array, string>
     */
    public function put(string $endpoint, ?array $data = null, array $headers = []): Result
    {
        return $this->request('PUT', $endpoint, $data, $headers);
    }
    
    /**
     * DELETE リクエストを実行
     * 
     * @param string $endpoint エンドポイント
     * @param array $headers 追加ヘッダー
     * @return Result<array, string>
     */
    public function delete(string $endpoint, array $headers = []): Result
    {
        return $this->request('DELETE', $endpoint, null, $headers);
    }
    
    /**
     * HTTP リクエストを実行
     * 
     * @param string $method HTTPメソッド
     * @param string $endpoint エンドポイント
     * @param array|null $data 送信データ
     * @param array $headers 追加ヘッダー
     * @return Result<array, string>
     */
    private function request(string $method, string $endpoint, ?array $data, array $headers): Result
    {
        return $this->validateEndpoint($endpoint)
            ->andThen(fn($ep) => $this->buildUrl($ep))
            ->andThen(fn($url) => $this->executeRequest($method, $url, $data, $headers))
            ->andThen(fn($response) => $this->parseResponse($response));
    }
    
    /**
     * エンドポイントのバリデーション
     */
    private function validateEndpoint(string $endpoint): Result
    {
        if (empty($endpoint)) {
            return new Err("エンドポイントが指定されていません");
        }
        
        if (!str_starts_with($endpoint, '/')) {
            $endpoint = '/' . $endpoint;
        }
        
        return new Ok($endpoint);
    }
    
    /**
     * 完全URLの構築
     */
    private function buildUrl(string $endpoint): Result
    {
        $url = $this->baseUrl . $endpoint;
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return new Err("無効なURL: $url");
        }
        
        return new Ok($url);
    }
    
    /**
     * HTTP リクエストの実行
     */
    private function executeRequest(string $method, string $url, ?array $data, array $headers): Result
    {
        $ch = curl_init();
        
        if ($ch === false) {
            return new Err("cURLセッションの初期化に失敗しました");
        }
        
        $allHeaders = array_merge($this->defaultHeaders, $headers);
        $headerList = [];
        foreach ($allHeaders as $key => $value) {
            $headerList[] = "$key: $value";
        }
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_HTTPHEADER => $headerList,
            CURLOPT_CUSTOMREQUEST => $method,
        ]);
        
        if ($data !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $jsonData = json_encode($data);
            if (json_last_error() !== JSON_ERROR_NONE) {
                curl_close($ch);
                return new Err("リクエストデータのJSON変換に失敗: " . json_last_error_msg());
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($response === false) {
            return new Err("HTTP リクエストに失敗: $error");
        }
        
        return new Ok([
            'body' => $response,
            'status_code' => $httpCode,
            'url' => $url
        ]);
    }
    
    /**
     * レスポンスの解析
     */
    private function parseResponse(array $response): Result
    {
        $statusCode = $response['status_code'];
        $body = $response['body'];
        
        // HTTPステータスコードのチェック
        if ($statusCode >= 400) {
            return new Err("HTTPエラー: $statusCode - " . $this->getStatusMessage($statusCode));
        }
        
        // JSONレスポンスの解析
        if (empty($body)) {
            return new Ok([]);
        }
        
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new Err("レスポンスのJSON解析に失敗: " . json_last_error_msg());
        }
        
        return new Ok($data);
    }
    
    /**
     * HTTPステータスコードからメッセージを取得
     */
    private function getStatusMessage(int $statusCode): string
    {
        return match($statusCode) {
            400 => "不正なリクエスト",
            401 => "認証が必要です",
            403 => "アクセスが禁止されています",
            404 => "リソースが見つかりません",
            405 => "許可されていないメソッドです",
            429 => "リクエスト制限に達しました",
            500 => "サーバー内部エラー",
            502 => "不正なゲートウェイ",
            503 => "サービス利用不可",
            504 => "ゲートウェイタイムアウト",
            default => "不明なエラー"
        };
    }
}

/**
 * 認証付きAPIクライアント
 */
class AuthenticatedApiClient extends ApiClient
{
    private string $apiKey;
    
    public function __construct(string $baseUrl, string $apiKey, int $timeout = 30)
    {
        parent::__construct($baseUrl, [
            'Authorization' => "Bearer $apiKey",
        ], $timeout);
        $this->apiKey = $apiKey;
    }
    
    /**
     * APIキーの検証
     */
    public function validateApiKey(): Result
    {
        return $this->get('/auth/validate')
            ->andThen(fn($response) => 
                isset($response['valid']) && $response['valid'] 
                    ? new Ok($response) 
                    : new Err("APIキーが無効です")
            );
    }
}

/**
 * ユーザー管理APIクライアント
 */
class UserApiClient
{
    private ApiClient $client;
    
    public function __construct(ApiClient $client)
    {
        $this->client = $client;
    }
    
    /**
     * ユーザー一覧を取得
     * 
     * @param int $page ページ番号
     * @param int $limit 1ページあたりの件数
     * @return Result<array, string>
     */
    public function getUsers(int $page = 1, int $limit = 20): Result
    {
        return $this->validatePagination($page, $limit)
            ->andThen(fn($params) => 
                $this->client->get("/users?" . http_build_query($params))
            );
    }
    
    /**
     * 特定ユーザーの取得
     * 
     * @param int $userId ユーザーID
     * @return Result<array, string>
     */
    public function getUser(int $userId): Result
    {
        return $this->validateUserId($userId)
            ->andThen(fn($id) => $this->client->get("/users/$id"));
    }
    
    /**
     * ユーザーの作成
     * 
     * @param array $userData ユーザーデータ
     * @return Result<array, string>
     */
    public function createUser(array $userData): Result
    {
        return $this->validateUserData($userData)
            ->andThen(fn($data) => $this->client->post('/users', $data));
    }
    
    /**
     * ユーザーの更新
     * 
     * @param int $userId ユーザーID
     * @param array $userData 更新データ
     * @return Result<array, string>
     */
    public function updateUser(int $userId, array $userData): Result
    {
        return $this->validateUserId($userId)
            ->andThen(fn($id) => $this->validateUserData($userData))
            ->andThen(fn($data) => $this->client->put("/users/$userId", $data));
    }
    
    /**
     * ユーザーの削除
     * 
     * @param int $userId ユーザーID
     * @return Result<array, string>
     */
    public function deleteUser(int $userId): Result
    {
        return $this->validateUserId($userId)
            ->andThen(fn($id) => $this->client->delete("/users/$id"));
    }
    
    /**
     * ページネーションパラメータのバリデーション
     */
    private function validatePagination(int $page, int $limit): Result
    {
        if ($page < 1) {
            return new Err("ページ番号は1以上である必要があります");
        }
        
        if ($limit < 1 || $limit > 100) {
            return new Err("件数は1-100の範囲で指定してください");
        }
        
        return new Ok(['page' => $page, 'limit' => $limit]);
    }
    
    /**
     * ユーザーIDのバリデーション
     */
    private function validateUserId(int $userId): Result
    {
        if ($userId < 1) {
            return new Err("ユーザーIDは1以上である必要があります");
        }
        
        return new Ok($userId);
    }
    
    /**
     * ユーザーデータのバリデーション
     */
    private function validateUserData(array $userData): Result
    {
        $required = ['name', 'email'];
        
        foreach ($required as $field) {
            if (!isset($userData[$field]) || empty($userData[$field])) {
                return new Err("必須フィールドが不足しています: $field");
            }
        }
        
        if (!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
            return new Err("メールアドレスの形式が正しくありません");
        }
        
        return new Ok($userData);
    }
}

// 使用例
if (__FILE__ === $_SERVER['SCRIPT_NAME']) {
    // 基本的なAPIクライアントの使用例
    echo "=== API Client Example ===\n";
    
    $client = new ApiClient('https://jsonplaceholder.typicode.com');
    
    // ユーザー一覧の取得
    $users = $client->get('/users');
    if ($users->isOk()) {
        $userList = $users->unwrap();
        echo "ユーザー数: " . count($userList) . "\n";
        echo "最初のユーザー: " . $userList[0]['name'] . "\n";
    } else {
        echo "エラー: " . $users->unwrapErr() . "\n";
    }
    
    // 特定ユーザーの取得
    $user = $client->get('/users/1');
    $message = $user
        ->map(fn($data) => "ユーザー名: " . $data['name'])
        ->unwrapOr("ユーザーが見つかりませんでした");
    
    echo $message . "\n";
    
    // 存在しないエンドポイントへのアクセス
    $notFound = $client->get('/nonexistent');
    if ($notFound->isErr()) {
        echo "期待通りのエラー: " . $notFound->unwrapErr() . "\n";
    }
    
    // UserApiClientの使用例
    echo "\n=== User API Client Example ===\n";
    
    $userApi = new UserApiClient($client);
    
    // ユーザー一覧の取得（ページング）
    $paginatedUsers = $userApi->getUsers(1, 5);
    if ($paginatedUsers->isOk()) {
        $count = count($paginatedUsers->unwrap());
        echo "ページング結果: $count ユーザー\n";
    } else {
        echo "ユーザー取得エラー\n";
    }
    
    // 特定ユーザーの詳細取得
    $userDetail = $userApi->getUser(1)
        ->map(fn($user) => [
            'name' => $user['name'],
            'email' => $user['email'],
            'company' => $user['company']['name'] ?? 'N/A'
        ]);
    
    if ($userDetail->isOk()) {
        $detail = $userDetail->unwrap();
        echo "ユーザー詳細:\n";
        echo "  名前: {$detail['name']}\n";
        echo "  メール: {$detail['email']}\n";
        echo "  会社: {$detail['company']}\n";
    }
    
    echo "\n=== Error Handling Example ===\n";
    
    // エラーハンドリングの例
    $invalidClient = new ApiClient('https://invalid-domain-that-does-not-exist.com');
    $errorResult = $invalidClient->get('/test');
    
    if ($errorResult->isErr()) {
        echo "エラーハンドリング成功: " . $errorResult->unwrapErr() . "\n";
    }
    
    // バリデーションエラーの例
    $validationError = $userApi->getUsers(-1, 200);
    if ($validationError->isErr()) {
        echo "バリデーションエラー: " . $validationError->unwrapErr() . "\n";
    }
    
    echo "\nAPI Client example completed.\n";
}