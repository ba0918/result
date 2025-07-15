<?php

declare(strict_types=1);

/**
 * HTTP API Client Implementation Example
 *
 * A practical example of safely handling HTTP API calls using Result types.
 * Can be copied and pasted for use in actual projects.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Mizumi\Result\{Err, Ok, Result};

/**
 * HTTP API Client
 *
 * Safely handles API call error handling using Result types.
 */
class ApiClient
{
    private string $baseUrl;

    private array $defaultHeaders;

    private int $timeout;

    public function __construct(
        string $baseUrl,
        array $defaultHeaders = [],
        int $timeout = 30,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->defaultHeaders = array_merge([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ], $defaultHeaders);
        $this->timeout = $timeout;
    }

    /**
     * Execute GET request
     *
     * @param string $endpoint Endpoint (e.g., "/users/123")
     * @param array $headers Additional headers
     *
     * @return Result<array, string> Response data on success, error message on failure
     */
    public function get(string $endpoint, array $headers = []): Result
    {
        return $this->request('GET', $endpoint, null, $headers);
    }

    /**
     * Execute POST request
     *
     * @param string $endpoint Endpoint
     * @param array|null $data Request data
     * @param array $headers Additional headers
     *
     * @return Result<array, string>
     */
    public function post(string $endpoint, ?array $data = null, array $headers = []): Result
    {
        return $this->request('POST', $endpoint, $data, $headers);
    }

    /**
     * Execute PUT request
     *
     * @param string $endpoint Endpoint
     * @param array|null $data Request data
     * @param array $headers Additional headers
     *
     * @return Result<array, string>
     */
    public function put(string $endpoint, ?array $data = null, array $headers = []): Result
    {
        return $this->request('PUT', $endpoint, $data, $headers);
    }

    /**
     * Execute DELETE request
     *
     * @param string $endpoint Endpoint
     * @param array $headers Additional headers
     *
     * @return Result<array, string>
     */
    public function delete(string $endpoint, array $headers = []): Result
    {
        return $this->request('DELETE', $endpoint, null, $headers);
    }

    /**
     * Execute HTTP request
     *
     * @param string $method HTTP method
     * @param string $endpoint Endpoint
     * @param array|null $data Request data
     * @param array $headers Additional headers
     *
     * @return Result<array, string>
     */
    private function request(string $method, string $endpoint, ?array $data, array $headers): Result
    {
        return $this->validateEndpoint($endpoint)
            ->andThen(fn ($ep) => $this->buildUrl($ep))
            ->andThen(fn ($url) => $this->executeRequest($method, $url, $data, $headers))
            ->andThen(fn ($response) => $this->parseResponse($response));
    }

    /**
     * Validate endpoint
     */
    private function validateEndpoint(string $endpoint): Result
    {
        if (empty($endpoint)) {
            return new Err('Endpoint not specified');
        }

        if (!str_starts_with($endpoint, '/')) {
            $endpoint = '/' . $endpoint;
        }

        return new Ok($endpoint);
    }

    /**
     * Build complete URL
     */
    private function buildUrl(string $endpoint): Result
    {
        $url = $this->baseUrl . $endpoint;

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return new Err("Invalid URL: $url");
        }

        return new Ok($url);
    }

    /**
     * Execute HTTP request
     */
    private function executeRequest(string $method, string $url, ?array $data, array $headers): Result
    {
        $ch = curl_init();

        if ($ch === false) {
            return new Err('Failed to initialize cURL session');
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

                return new Err('Failed to JSON encode request data: ' . json_last_error_msg());
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return new Err("HTTP request failed: $error");
        }

        return new Ok([
            'body' => $response,
            'status_code' => $httpCode,
            'url' => $url,
        ]);
    }

    /**
     * Parse response
     */
    private function parseResponse(array $response): Result
    {
        $statusCode = $response['status_code'];
        $body = $response['body'];

        // Check HTTP status code
        if ($statusCode >= 400) {
            return new Err("HTTP error: $statusCode - " . $this->getStatusMessage($statusCode));
        }

        // Parse JSON response
        if (empty($body)) {
            return new Ok([]);
        }

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new Err('Failed to parse JSON response: ' . json_last_error_msg());
        }

        return new Ok($data);
    }

    /**
     * Get message from HTTP status code
     */
    private function getStatusMessage(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'Bad Request',
            401 => 'Authentication Required',
            403 => 'Access Forbidden',
            404 => 'Resource Not Found',
            405 => 'Method Not Allowed',
            429 => 'Rate Limit Exceeded',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            default => 'Unknown Error'
        };
    }
}

/**
 * Authenticated API Client
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
     * Validate API key
     */
    public function validateApiKey(): Result
    {
        return $this->get('/auth/validate')
            ->andThen(
                fn ($response) => isset($response['valid']) && $response['valid']
                    ? new Ok($response)
                    : new Err('API key is invalid'),
            );
    }
}

/**
 * User Management API Client
 */
class UserApiClient
{
    private ApiClient $client;

    public function __construct(ApiClient $client)
    {
        $this->client = $client;
    }

    /**
     * Get user list
     *
     * @param int $page Page number
     * @param int $limit Items per page
     *
     * @return Result<array, string>
     */
    public function getUsers(int $page = 1, int $limit = 20): Result
    {
        return $this->validatePagination($page, $limit)
            ->andThen(
                fn ($params) => $this->client->get('/users?' . http_build_query($params)),
            );
    }

    /**
     * Get specific user
     *
     * @param int $userId User ID
     *
     * @return Result<array, string>
     */
    public function getUser(int $userId): Result
    {
        return $this->validateUserId($userId)
            ->andThen(fn ($id) => $this->client->get("/users/$id"));
    }

    /**
     * Create user
     *
     * @param array $userData User data
     *
     * @return Result<array, string>
     */
    public function createUser(array $userData): Result
    {
        return $this->validateUserData($userData)
            ->andThen(fn ($data) => $this->client->post('/users', $data));
    }

    /**
     * Update user
     *
     * @param int $userId User ID
     * @param array $userData Update data
     *
     * @return Result<array, string>
     */
    public function updateUser(int $userId, array $userData): Result
    {
        return $this->validateUserId($userId)
            ->andThen(fn ($id) => $this->validateUserData($userData))
            ->andThen(fn ($data) => $this->client->put("/users/$userId", $data));
    }

    /**
     * Delete user
     *
     * @param int $userId User ID
     *
     * @return Result<array, string>
     */
    public function deleteUser(int $userId): Result
    {
        return $this->validateUserId($userId)
            ->andThen(fn ($id) => $this->client->delete("/users/$id"));
    }

    /**
     * Validate pagination parameters
     */
    private function validatePagination(int $page, int $limit): Result
    {
        if ($page < 1) {
            return new Err('Page number must be 1 or greater');
        }

        if ($limit < 1 || $limit > 100) {
            return new Err('Limit must be between 1 and 100');
        }

        return new Ok(['page' => $page, 'limit' => $limit]);
    }

    /**
     * Validate user ID
     */
    private function validateUserId(int $userId): Result
    {
        if ($userId < 1) {
            return new Err('User ID must be 1 or greater');
        }

        return new Ok($userId);
    }

    /**
     * Validate user data
     */
    private function validateUserData(array $userData): Result
    {
        $required = ['name', 'email'];

        foreach ($required as $field) {
            if (!isset($userData[$field]) || empty($userData[$field])) {
                return new Err("Required field missing: $field");
            }
        }

        if (!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
            return new Err('Invalid email address format');
        }

        return new Ok($userData);
    }
}

// Usage examples
if ($_SERVER['SCRIPT_NAME'] === __FILE__) {
    // Basic API client usage example
    echo "=== API Client Example ===\n";

    $client = new ApiClient('https://jsonplaceholder.typicode.com');

    // Get user list
    $users = $client->get('/users');
    if ($users->isOk()) {
        $userList = $users->unwrap();
        echo 'User count: ' . count($userList) . "\n";
        echo 'First user: ' . $userList[0]['name'] . "\n";
    } else {
        echo 'Error: ' . $users->unwrapErr() . "\n";
    }

    // Get specific user
    $user = $client->get('/users/1');
    $message = $user
        ->map(fn ($data) => 'User name: ' . $data['name'])
        ->unwrapOr('User not found');

    echo $message . "\n";

    // Access non-existent endpoint
    $notFound = $client->get('/nonexistent');
    if ($notFound->isErr()) {
        echo 'Expected error: ' . $notFound->unwrapErr() . "\n";
    }

    // UserApiClient usage example
    echo "\n=== User API Client Example ===\n";

    $userApi = new UserApiClient($client);

    // Get user list (paginated)
    $paginatedUsers = $userApi->getUsers(1, 5);
    if ($paginatedUsers->isOk()) {
        $count = count($paginatedUsers->unwrap());
        echo "Pagination result: $count users\n";
    } else {
        echo "User fetch error\n";
    }

    // Get specific user details
    $userDetail = $userApi->getUser(1)
        ->map(fn ($user) => [
            'name' => $user['name'],
            'email' => $user['email'],
            'company' => $user['company']['name'] ?? 'N/A',
        ]);

    if ($userDetail->isOk()) {
        $detail = $userDetail->unwrap();
        echo "User details:\n";
        echo "  Name: {$detail['name']}\n";
        echo "  Email: {$detail['email']}\n";
        echo "  Company: {$detail['company']}\n";
    }

    echo "\n=== Error Handling Example ===\n";

    // Error handling example
    $invalidClient = new ApiClient('https://invalid-domain-that-does-not-exist.com');
    $errorResult = $invalidClient->get('/test');

    if ($errorResult->isErr()) {
        echo 'Error handling successful: ' . $errorResult->unwrapErr() . "\n";
    }

    // Validation error example
    $validationError = $userApi->getUsers(-1, 200);
    if ($validationError->isErr()) {
        echo 'Validation error: ' . $validationError->unwrapErr() . "\n";
    }

    echo "\nAPI Client example completed.\n";
}
