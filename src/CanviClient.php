<?php
declare(strict_types=1);

namespace R2Soft\Canvi;

use Psr\Http\Client\ClientInterface as Psr18Client;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use R2Soft\Canvi\Exceptions\ApiException;
use R2Soft\Canvi\Exceptions\UnauthorizedException;
use R2Soft\Canvi\Exceptions\ValidationException;
use R2Soft\Canvi\Exceptions\RateLimitException;
use R2Soft\Canvi\Exceptions\ServerException;
use R2Soft\Canvi\Exceptions\TokenStorageException;
use R2Soft\Canvi\Services\CanviAuthService;
use R2Soft\Canvi\Services\TokenStorage;

final class CanviClient
{
    private ?string $accessToken = null;
    private ?TokenStorage $tokenStorage = null;
    private const TOKEN_EXPIRES_IN = 604800; // 7 dias em segundos
    private const TOKEN_RENEW_BEFORE = 3600; // 1 hora antes de expirar

    public function __construct(
        private string $baseUrl,
        private ?string $apiKey = null,
        private ?string $apiSecret = null,
        private int $timeout = 10,
        private ?LoggerInterface $logger = null,
        private ?Psr18Client $httpClient = null,
        private ?RequestFactoryInterface $requestFactory = null,
        private ?StreamFactoryInterface $streamFactory = null,
        private int $maxRetries = 0,
        private int $retryDelayMs = 250,
        private ?string $userAgent = 'r2soft-canvi-php/1.x'
    ) {}

    /**
     * Obtém a instância do TokenStorage
     */
    private function getTokenStorage(): TokenStorage
    {
        if ($this->tokenStorage === null) {
            $this->tokenStorage = new TokenStorage(null, $this->apiSecret);
        }
        return $this->tokenStorage;
    }

    /**
     * Define o token de acesso e seu tempo de expiração
     *
     * @param string $token Token de acesso
     * @param int $expiresIn Tempo de expiração em segundos a partir de agora
     */
    public function setAccessToken(string $token, int $expiresIn = null): void
    {
        $this->accessToken = $token;
        $expiresIn = $expiresIn ?? self::TOKEN_EXPIRES_IN;

        try {
            $this->getTokenStorage()->saveToken($token, $expiresIn);
        } catch (TokenStorageException $e) {
            // Loga o erro, mas não interrompe o fluxo
            $this->logger?->error('Falha ao salvar o token: ' . $e->getMessage());
        }
    }

    /**
     * Carrega o token do armazenamento
     */
    private function loadToken(): ?string
    {
        try {
            $tokenData = $this->getTokenStorage()->loadToken();
            if ($tokenData && isset($tokenData['token'])) {
                $this->accessToken = $tokenData['token'];
                return $this->accessToken;
            }
        } catch (TokenStorageException $e) {
            $this->logger?->error('Falha ao carregar o token: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Verifica se o token atual está expirado ou próximo de expirar
     */
    private function isTokenExpired(): bool
    {
        try {
            $tokenData = $this->getTokenStorage()->loadToken();

            // Se não há token ou dados de expiração, considera expirado
            if (!$tokenData || !isset($tokenData['expires_at'])) {
                return true;
            }

            // Verifica se o token expirou ou está próximo de expirar
            $currentTime = time();
            return $tokenData['expires_at'] <= ($currentTime + self::TOKEN_RENEW_BEFORE);

        } catch (TokenStorageException $e) {
            $this->logger?->error('Erro ao verificar expiração do token: ' . $e->getMessage());
            return true; // Em caso de erro, considera expirado para forçar renovação
        }
    }

    public function getApiKey(): ?string { return $this->apiKey; }
    public function getApiSecret(): ?string { return $this->apiSecret; }

    /**
     * Envia uma requisição POST para a API Canvi.
     *
     * @param string $path O endpoint da API
     * @param array $json Dados a serem enviados no corpo da requisição
     * @param string|null $idempotencyKey Chave de idempotência para garantir que a requisição não seja repetida
     * @return array Resposta da API
     * @throws ApiException Em caso de erro na requisição
     */
    public function post(string $path, array $json, ?string $idempotencyKey = null): array
    {
        if (!is_string($path) || empty($path)) {
            throw new \InvalidArgumentException('O caminho da API deve ser uma string não vazia');
        }
        try {
            return $this->sendJson('POST', $path, $json, $idempotencyKey);
        }catch (\Throwable $e) {
            debug($e->getMessage());
            throw new ApiException($e->getMessage(), $e->getCode(), $e);
        }

    }

    /**
     * Envia uma requisição GET para a API Canvi.
     *
     * @param string $path O endpoint da API
     * @param array $query Parâmetros de query string
     * @return array Resposta da API
     * @throws ApiException Em caso de erro na requisição
     */
    public function get(string $path, array $query = []): array
    {
        if (!is_string($path) || empty($path)) {
            throw new \InvalidArgumentException('O caminho da API deve ser uma string não vazia');
        }
        $queryString = $query ? '?' . http_build_query($query) : '';
        return $this->sendJson('GET', $path . $queryString);
    }

    private function sendJson(string $method, string $path, ?array $json = null, ?string $idempotencyKey = null): array
    {
        $client = $this->requireHttpClient();
        $requestFactory = $this->requireRequestFactory();

        $uri = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');

        $headers = [
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
            'User-Agent'    => $this->userAgent ?? 'r2soft-canvi-php',
            'X-Client-Timeout' => (string)$this->timeout,
        ];
        // Tenta carregar o token do armazenamento
        $token = $this->loadToken();

        // Se não há token ou está expirado, gera um novo
        if ($this->isTokenExpired() || $token === null) {
            $authService = new CanviAuthService($this);
            $token = $authService->generateToken();
            // O token e seu tempo de expiração são definidos pelo método generateToken
            // através de setAccessToken
        }

        if ($token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }
        if ($idempotencyKey) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        $body = null;
        if ($json !== null) {
            $streamFactory = $this->requireStreamFactory();
            $encoded = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $body = $streamFactory->createStream($encoded);
        }
        $request = $requestFactory->createRequest($method, $uri);
        foreach ($headers as $k => $v) {
            $request = $request->withHeader($k, $v);
        }
        if ($body) {
            $request = $request->withBody($body);
        }


        $attempts = 0;
        while ($attempts <= $this->maxRetries) {
            $attempts++;
            try {
                $response = $client->sendRequest($request);
                $status = $response->getStatusCode();
                $raw = (string)$response->getBody();
                $data = $this->decodeJsonSafely($raw);

                if ($status >= 200 && $status < 300) {
                    if (is_array($data) && array_key_exists('data', $data)) {
                        return $data['data'];
                    }
                    return $data ?? [];
                }

                $ex = $this->mapApiError($status, $data, $raw);
                $this->logger?->warning('Canvi API error', [
                    'method' => $method,
                    'path'   => $path,
                    'status' => $status,
                    'error'  => $ex->getMessage(),
                ]);

                if (!$this->shouldRetry($status, $attempts)) {
                    debug($ex->getMessage());
                    throw $ex;
                }
                usleep($this->retryDelayMs * 1000);
            } catch (\Throwable $e) {
                $this->logger?->error('Canvi HTTP error', [
                    'method' => $method,
                    'path'   => $path,
                    'error'  => $e->getMessage(),
                ]);
                debug($e->getMessage());
                throw new ApiException('HTTP transport error', 0, $e);
            }
        }

        throw new ApiException('Max retries reached', 0);
    }

    private function decodeJsonSafely(string $raw): mixed
    {
        if ($raw === '') {
            return null;
        }
        try {
            return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
    }

    /**
     * Autentica no endpoint informado e armazena o access token para uso nos próximos requests.
     * O payload e o extractor são flexíveis para adequar ao formato da API.
     *
     * @param string $path ex.: '/api/v1/auth/token'
     * @param array $payload corpo da requisição (ex.: ['api_key' => '...', 'api_secret' => '...'])
     * @param callable|null $extractor função opcional para extrair o token da resposta: fn(array $data): ?string
     * @return string token obtido
     * @throws ApiException quando a autenticação falhar ou o token não for encontrado
     */
    public function authenticate(string $path, array $payload, ?callable $extractor = null): string
    {
        $client = $this->requireHttpClient();
        $requestFactory = $this->requireRequestFactory();
        $streamFactory = $this->requireStreamFactory();

        $uri = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');

        $headers = [
            'Accept'           => 'application/json',
            'Content-Type'     => 'application/json',
            'User-Agent'       => $this->userAgent ?? 'r2soft-canvi-php',
            'X-Client-Timeout' => (string)$this->timeout,
        ];

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $body = $streamFactory->createStream($encoded);

        $request = $requestFactory->createRequest('POST', $uri);
        foreach ($headers as $k => $v) {
            $request = $request->withHeader($k, $v);
        }

        $request = $request->withBody($body);

        try {
            $response = $client->sendRequest($request);
            $status = $response->getStatusCode();
            $raw = (string)$response->getBody();
            $data = $this->decodeJsonSafely($raw);
            if ($status >= 200 && $status < 300) {
                $token = null;
                if ($extractor) {
                    $token = $extractor(is_array($data) ? $data : []);
                }
                if (!$token) {
                    $arr = is_array($data) ? $data : [];
                    $container = $arr['data'] ?? $arr;
                    $token = $container['access_token'] ?? $container['token'] ?? null;
                }
                if (!is_string($token) || $token === '') {
                    throw new ApiException('Token not found in authentication response', 0, null, $status, $raw);
                }
                $this->setAccessToken($token);
                return $token;
            }

            $ex = $this->mapApiError($status, is_array($data) ? $data : null, $raw);
            throw $ex;
        } catch (\Throwable $e) {
            $this->logger?->error('Canvi auth error', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            if ($e instanceof ApiException) {
                throw $e;
            }
            throw new ApiException('HTTP transport error (auth)', 0, $e);
        }
    }

    private function mapApiError(int $status, ?array $data, string $raw): ApiException
    {
        $isArray = is_array($data);
        $message = $isArray ? ($data['mensagem'] ?? $data['message'] ?? $data['error'] ?? 'Unknown error') : 'Unknown error';
        $code = $isArray ? (int)($data['code'] ?? 0) : 0;
        $fields = $isArray ? ($data['erros'] ?? $data['fields'] ?? []) : [];

        return match (true) {
            $status === 401 || $status === 403 => new UnauthorizedException($message, $code),
            $status === 400 || $status === 422 => new ValidationException($message, $code, is_array($fields) ? $fields : []),
            $status === 429                     => new RateLimitException($message, $code),
            $status >= 500                      => new ServerException($message, $code),
            default                             => new ApiException($message, $code, null, $status, $raw),
        };
    }

    private function shouldRetry(int $status, int $attempts): bool
    {
        if ($this->maxRetries <= 0) return false;
        if ($attempts > $this->maxRetries + 1) return false;

        return $status === 429 || ($status >= 500 && $status <= 599);
    }

    private function requireHttpClient(): Psr18Client
    {
        if (!$this->httpClient) {
            throw new \RuntimeException('PSR-18 client não fornecido');
        }
        return $this->httpClient;
    }

    private function requireRequestFactory(): RequestFactoryInterface
    {
        if (!$this->requestFactory) {
            throw new \RuntimeException('RequestFactory (PSR-17) não fornecida');
        }
        return $this->requestFactory;
    }

    private function requireStreamFactory(): StreamFactoryInterface
    {
        if (!$this->streamFactory) {
            throw new \RuntimeException('StreamFactory (PSR-17) não fornecida');
        }
        return $this->streamFactory;
    }
}
