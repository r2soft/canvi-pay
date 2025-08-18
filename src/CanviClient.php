<?php
declare(strict_types=1);

namespace R2Soft\Canvi;

use Psr\Http\Client\ClientInterface as Psr18Client;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use R2Soft\Canvi\Contracts\CanviClientInterface;
use R2Soft\Canvi\Exceptions\ApiException;
use R2Soft\Canvi\Exceptions\UnauthorizedException;
use R2Soft\Canvi\Exceptions\ValidationException;
use R2Soft\Canvi\Exceptions\RateLimitException;
use R2Soft\Canvi\Exceptions\ServerException;
use R2Soft\Canvi\Exceptions\TokenStorageException;
use R2Soft\Canvi\Services\CanviAuthService;
use R2Soft\Canvi\Services\TokenStorage;

class CanviClient implements CanviClientInterface
{
    private ?string $accessToken = null;
    private ?TokenStorage $tokenStorage = null;
    private const TOKEN_EXPIRES_IN = 604800; // 7 dias em segundos
    private const TOKEN_RENEW_BEFORE = 3600; // 1 hora antes de expirar
    private ?Psr18Client $httpClient = null;
    private ?RequestFactoryInterface $requestFactory = null;
    private ?StreamFactoryInterface $streamFactory = null;

    public function __construct(
        private string $baseUrl,
        private ?string $apiKey = null,
        private ?string $apiSecret = null,
        private int $timeout = 10,
        private ?LoggerInterface $logger = null,
        ?Psr18Client $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        private int $maxRetries = 0,
        private int $retryDelayMs = 250,
        private ?string $userAgent = 'r2soft-canvi-php/1.x'
    ) {
        $this->httpClient = $httpClient;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function getBaseUri(): string
    {
        return $this->baseUrl;
    }
    
    /**
     * Obtém a chave da API
     */
    public function getApiKey(): ?string 
    { 
        return $this->apiKey; 
    }
    
    /**
     * Obtém o segredo da API
     */
    public function getApiSecret(): ?string 
    { 
        return $this->apiSecret; 
    }
    
    /**
     * @inheritDoc
     */
    public function request(string $method, string $endpoint, array $options = []): ResponseInterface
    {
        $httpClient = $this->httpClient ?? $this->requireHttpClient();
        $requestFactory = $this->requestFactory ?? $this->requireRequestFactory();
        $streamFactory = $this->streamFactory ?? $this->requireStreamFactory();

        $uri = $this->baseUrl . '/' . ltrim($endpoint, '/');
        $request = $requestFactory->createRequest($method, $uri);

        // Adiciona headers padrão
        $headers = array_merge([
            'User-Agent' => $this->userAgent,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ], $options['headers'] ?? []);

        // Adiciona o token de acesso se disponível
        if ($this->accessToken) {
            $headers['Authorization'] = 'Bearer ' . $this->accessToken;
        }

        // Adiciona autenticação básica se fornecida
        if (isset($options['auth']) && is_array($options['auth']) && count($options['auth']) === 2) {
            $authValue = base64_encode($options['auth'][0] . ':' . $options['auth'][1]);
            $headers['Authorization'] = 'Basic ' . $authValue;
        }

        // Aplica os headers à requisição
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        // Adiciona o corpo da requisição se fornecido
        if (isset($options['json'])) {
            $body = $streamFactory->createStream(json_encode($options['json']));
            $request = $request->withBody($body);
        }

        // Executa a requisição com tratamento de erros
        try {
            return $httpClient->sendRequest($request);
        } catch (\Psr\Http\Client\ClientExceptionInterface $e) {
            $this->logger?->error('Erro na requisição HTTP: ' . $e->getMessage(), [
                'method' => $method,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            throw new ApiException('Erro na requisição HTTP: ' . $e->getMessage(), $e->getCode(), $e);
        } catch (\Exception $e) {
            $this->logger?->error('Erro inesperado na requisição: ' . $e->getMessage(), [
                'method' => $method,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            throw new ApiException('Erro inesperado: ' . $e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }
    
    /**
     * Autentica no endpoint informado e armazena o access token para uso nos próximos requests.
     * 
     * @param string $path Endpoint de autenticação
     * @param array $payload Dados de autenticação
     * @param callable|null $extractor Função para extrair o token da resposta
     * @return string Token de acesso
     * @throws ApiException
     */
    public function authenticate(string $path, array $payload, ?callable $extractor = null): string
    {
        try {
            $response = $this->request('POST', $path, [
                'json' => $payload
            ]);
            
            $data = json_decode((string) $response->getBody(), true);
            
            if ($extractor) {
                $token = $extractor($data);
            } else {
                $token = $data['access_token'] ?? $data['token'] ?? null;
                
                if ($token === null) {
                    throw new ApiException('Token não encontrado na resposta da API');
                }
                
                if (isset($data['expires_in'])) {
                    $this->setAccessToken($token, (int) $data['expires_in']);
                } else {
                    $this->setAccessToken($token);
                }
            }
            
            if (empty($token)) {
                throw new ApiException('Token não encontrado na resposta da API');
            }
            
            return $token;
        } catch (\Exception $e) {
            throw new ApiException('Falha na autenticação: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }
    
    /**
     * Obtém a instância do TokenStorage
     */
    private function getTokenStorage(): TokenStorage
    {
        if ($this->tokenStorage === null) {
            $this->tokenStorage = new TokenStorage(
                sys_get_temp_dir() . '/canvi-tokens',
                $this->apiKey ?? 'default',
                $this->logger
            );
        }
        
        return $this->tokenStorage;
    }
    
    /**
     * Define o token de acesso e seu tempo de expiração
     * 
     * @param string $token Token de acesso
     * @param int|null $expiresIn Tempo de expiração em segundos a partir de agora. Se não informado, usa o valor padrão de 7 dias.
     */
    public function setAccessToken(string $token, ?int $expiresIn = null): void
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
            $this->logger?->error('API request failed: ' . $e->getMessage(), ['exception' => $e]);
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
        
        // Skip token for auth requests to prevent infinite loops
        if (!str_ends_with($path, '/token') && !str_ends_with($path, '/token/')) {
            // Tenta carregar o token do armazenamento
            $token = $this->loadToken();

            // Se não há token ou está expirado, tenta obter um novo
            if (($this->isTokenExpired() || $token === null) && !empty($this->apiKey) && !empty($this->apiSecret)) {
                // Chama o endpoint de autenticação diretamente
                $token = $this->authenticate(
                    '/bt/token',
                    [
                        'client_id' => $this->apiKey,
                        'private_key' => $this->apiSecret
                    ]
                );
            }

            // Only add Authorization header if we have a token
            if (!empty($token)) {
                $headers['Authorization'] = 'Bearer ' . $token;
            }
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
                    $this->logger?->error('API request failed after retries', [
                        'error' => $ex->getMessage(),
                        'status' => $status,
                        'attempt' => $attempts
                    ]);
                    throw $ex;
                }
                usleep($this->retryDelayMs * 1000);
            } catch (\Throwable $e) {
                $this->logger?->error('Canvi HTTP error', [
                    'method' => $method,
                    'path'   => $path,
                    'error'  => $e->getMessage(),
                    'trace'  => $e->getTraceAsString()
                ]);
                
                // Log the response details if available
                if (isset($response)) {
                    $this->logger?->error('Response details', [
                        'status' => $response->getStatusCode(),
                        'headers' => $response->getHeaders(),
                        'body' => (string) $response->getBody()
                    ]);
                }
                
                throw new ApiException('HTTP transport error: ' . $e->getMessage(), 0, $e);
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


    private function mapApiError(int $status, ?array $data, string $raw): ApiException
    {
        $isArray = is_array($data);
        $message = $isArray ? ($data['mensagem'] ?? $data['message'] ?? $data['error'] ?? 'Erro desconhecido') : 'Erro desconhecido';
        $code = $isArray ? (int)($data['code'] ?? 0) : 0;
        $fields = $isArray ? ($data['erros'] ?? $data['fields'] ?? []) : [];

        // Garante que $fields seja sempre um array
        if (!is_array($fields)) {
            $fields = [];
        }

        return match (true) {
            $status === 401 || $status === 403 => new UnauthorizedException($message, $code),
            $status === 400 || $status === 422 => new ValidationException($message, $code, $fields),
            $status === 429 => new RateLimitException($message, $code),
            $status >= 500 => new ServerException($message, $code),
            default => new ApiException($message, $code, null, $status, $raw),
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
