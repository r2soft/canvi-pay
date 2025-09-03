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
use R2Soft\Canvi\Exceptions\ValidationException as FullValidationException;
use R2Soft\Canvi\Exceptions\RateLimitException;
use R2Soft\Canvi\Exceptions\ServerException;
use R2Soft\Canvi\Exceptions\TokenStorageException;
use R2Soft\Canvi\Services\CanviAuthService;
use R2Soft\Canvi\Services\TokenStorage;

/**
 * Cliente HTTP para integração com a API Canvi.
 *
 * Esta classe é responsável por gerenciar a comunicação com a API Canvi, incluindo
 * autenticação, tratamento de erros e execução de requisições HTTP.
 *
 * @package R2Soft\Canvi
 * @author R2Soft <dev@r2soft.com.br>
 * @version 1.0.0
 */
class CanviClient implements CanviClientInterface
{
    private ?string $accessToken = null;
    private ?TokenStorage $tokenStorage = null;
    private const TOKEN_EXPIRES_IN = 604800; // 7 dias em segundos
    private const TOKEN_RENEW_BEFORE = 3600; // 1 hora antes de expirar
    private ?Psr18Client $httpClient = null;
    private ?RequestFactoryInterface $requestFactory = null;
    private ?StreamFactoryInterface $streamFactory = null;

    /**
     * Constrói uma nova instância do cliente Canvi.
     *
     * @param string $baseUrl URL base da API Canvi (ex: https://api.canvi.com.br)
     * @param string|null $apiKey Chave de API para autenticação
     * @param string|null $apiSecret Segredo da API para autenticação
     * @param int $timeout Tempo máximo de espera em segundos para as requisições
     * @param LoggerInterface|null $logger Instância do logger para registro de eventos
     * @param Psr18Client|null $httpClient Cliente HTTP personalizado (opcional)
     * @param RequestFactoryInterface|null $requestFactory Fábrica de requisições PSR-17 (opcional)
     * @param StreamFactoryInterface|null $streamFactory Fábrica de streams PSR-17 (opcional)
     * @param int $maxRetries Número máximo de tentativas em caso de falha
     * @param int $retryDelayMs Tempo de espera entre tentativas em milissegundos
     * @param string $userAgent User-Agent personalizado para as requisições
     * @throws \InvalidArgumentException Se a URL base for inválida
     */
    public function __construct(
        private string           $baseUrl,
        private ?string          $apiKey = null,
        private ?string          $apiSecret = null,
        private int              $timeout = 10,
        private ?LoggerInterface $logger = null,
        ?Psr18Client             $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface  $streamFactory = null,
        private int              $maxRetries = 0,
        private int              $retryDelayMs = 250,
        private ?string          $userAgent = 'r2soft-canvi-php/1.x'
    )
    {
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
     * Executa uma requisição HTTP para a API Canvi.
     *
     * Este método é responsável por enviar requisições HTTP para a API Canvi,
     * adicionando cabeçalhos necessários e tratando erros comuns.
     *
     * @param string $method Método HTTP (GET, POST, PUT, DELETE, etc.)
     * @param string $endpoint Caminho do endpoint (ex: /api/v1/boletos)
     * @param array $options Opções da requisição (headers, body, query, etc.)
     * @return ResponseInterface Resposta da API
     * @throws ApiException Em caso de erro na requisição
     * @throws \InvalidArgumentException Se o método ou endpoint forem inválidos
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
     * Autentica no endpoint informado e armazena o token de acesso para uso nos próximos requests.
     *
     * Este método é responsável por autenticar o cliente na API Canvi usando as credenciais fornecidas
     * e armazenar o token de acesso retornado para uso em requisições subsequentes.
     *
     * @param string $path Caminho do endpoint de autenticação (ex: /oauth/token)
     * @param array $payload Dados de autenticação (geralmente client_id e client_secret)
     * @param callable|null $extrator Função personalizada para extrair o token da resposta
     * @return string Token de acesso obtido
     * @throws ApiException Se a autenticação falhar ou o token não for encontrado na resposta
     * @throws \InvalidArgumentException Se o caminho ou payload forem inválidos
     *
     * @example
     * // Exemplo de uso com credenciais diretas:
     * $token = $client->authenticate('/oauth/token', [
     *     'client_id' => 'seu-client-id',
     *     'client_secret' => 'seu-client-secret',
     *     'grant_type' => 'client_credentials'
     * ]);
     *
     * // Exemplo com extrator personalizado:
     * $token = $client->authenticate('/custom-auth', $credenciais, function($data) {
     *     return $data['accessToken'] ?? null;
     * });
     */
    public function authenticate(string $path, array $payload, ?callable $extrator = null): string
    {
        try {
            $response = $this->request('POST', $path, [
                'json' => $payload
            ]);

            $data = json_decode((string)$response->getBody(), true);

            if ($extrator) {
                $token = $extrator($data);
            } else {
                $token = $data['access_token'] ?? $data['token'] ?? null;

                if ($token === null) {
                    throw new ApiException('Token não encontrado na resposta da API');
                }

                if (isset($data['expires_in'])) {
                    $this->setAccessToken($token, (int)$data['expires_in']);
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
     * Este método simplifica o envio de requisições POST para a API Canvi,
     * adicionando automaticamente o cabeçalho Content-Type: application/json
     * e tratando erros comuns.
     *
     * @param string $path Caminho do endpoint (ex: /api/v1/boletos)
     * @param array $json Dados a serem enviados no corpo da requisição (serão convertidos para JSON)
     * @param string|null $idempotencyKey Chave de idempotência (opcional)
     * @return array Resposta da API decodificada
     * @throws ApiException Em caso de erro na requisição ou resposta inválida
     * @throws \InvalidArgumentException Se o caminho for inválido
     * @throws ValidationException Em caso de erro de validação nos dados
     *
     * @example
     * // Exemplo de uso:
     * $client->post('/api/v1/boletos', [
     *     'valor' => 100.00,
     *     'vencimento' => '2023-12-31',
     *     'pagador' => [
     *         'nome' => 'Fulano de Tal',
     *         'documento' => '123.456.789-09'
     *     ]
     * ], 'chave-idempotencia-123');
     */
    public function post(string $path, array $json, ?string $idempotencyKey = null): array
    {
        $this->logger?->debug('post method called', [
            'path' => $path,
            'json' => $json,
            'idempotencyKey' => $idempotencyKey
        ]);

        if (!is_string($path) || empty($path)) {
            throw new \InvalidArgumentException('O caminho da API deve ser uma string não vazia');
        }
        try {
            $result = $this->sendJson('POST', $path, $json, $idempotencyKey);
            $this->logger?->debug('post method completed successfully', ['result' => $result]);
            return $result;
        } catch (\R2Soft\Canvi\Exceptions\ValidationException $e) {
            // Propaga exceções de validação sem encapsular
            throw $e;
        } catch (ApiException $e) {
            // Deixa exceções da hierarquia ApiException passarem sem encapsular
            throw $e;
        } catch (\Throwable $e) {
            $this->logger?->error('API request failed: ' . $e->getMessage(), ['exception' => $e]);
            throw new ApiException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Envia uma requisição PUT com JSON para a API Canvi.
     *
     * @param string $path Endpoint (ex.: /ac/comerciante)
     * @param array $json Corpo JSON
     * @param string|null $idempotencyKey Chave de idempotência (opcional)
     * @return array
     * @throws \InvalidArgumentException
     * @throws \R2Soft\Canvi\Exceptions\ValidationException
     * @throws \R2Soft\Canvi\Exceptions\ApiException
     */
    public function put(string $path, array $json, ?string $idempotencyKey = null): array
    {
        $this->logger?->debug('put method called', [
            'path' => $path,
            'json' => $json,
            'idempotencyKey' => $idempotencyKey,
        ]);

        if ($path === '') {
            throw new \InvalidArgumentException('O caminho da API deve ser uma string não vazia');
        }

        try {
            $result = $this->sendJson('PUT', $path, $json, $idempotencyKey);
            $this->logger?->debug('put method completed successfully', ['result' => $result]);
            return $result;
        } catch (\R2Soft\Canvi\Exceptions\ValidationException $e) {
            throw $e; // repassa validação
        } catch (ApiException $e) {
            throw $e; // repassa erros de API da hierarquia
        } catch (\Throwable $e) {
            $this->logger?->error('API request failed: ' . $e->getMessage(), ['exception' => $e]);
            throw new ApiException($e->getMessage(), $e->getCode(), $e);
        }
    }


    /**
     * Envia uma requisição GET para a API Canvi.
     *
     * Este método simplifica o envio de requisições GET para a API Canvi,
     * adicionando automaticamente parâmetros de consulta à URL.
     *
     * @param string $path Caminho do endpoint (ex: /api/v1/boletos/123)
     * @param array $query Parâmetros de consulta a serem adicionados à URL
     * @return array Resposta da API decodificada
     * @throws ApiException Em caso de erro na requisição ou resposta inválida
     * @throws \InvalidArgumentException Se o caminho for inválido
     *
     * @example
     * // Exemplo de uso:
     * $boletos = $client->get('/api/v1/boletos', [
     *     'status' => 'pending',
     *     'data_inicio' => '2023-01-01',
     *     'data_fim' => '2023-12-31'
     * ]);
     */
    public function get(string $path, array $query = []): array
    {
        if (!is_string($path) || empty($path)) {
            throw new \InvalidArgumentException('O caminho da API deve ser uma string não vazia');
        }
        try {
            $queryString = $query ? '?' . http_build_query($query) : '';
            return $this->sendJson('GET', $path . $queryString);
        } catch (ApiException $e) {
            // Deixa exceções da hierarquia ApiException passarem sem encapsular
            throw $e;
        } catch (\Throwable $e) {
            $this->logger?->error('API request failed: ' . $e->getMessage(), ['exception' => $e]);
            throw new ApiException($e->getMessage(), $e->getCode(), $e);
        }
    }

    private function sendJson(string $method, string $path, ?array $json = null, ?string $idempotencyKey = null): array
    {
        $client         = $this->requireHttpClient();
        $requestFactory = $this->requireRequestFactory();

        $uri = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');

        $headers = [
            'Accept'           => 'application/json',
            'Content-Type'     => 'application/json',
            'User-Agent'       => $this->userAgent ?? 'r2soft-canvi-php',
            'X-Client-Timeout' => (string)$this->timeout,
        ];

        // Skip token for auth requests to prevent infinite loops
        if (!str_ends_with($path, '/token') && !str_ends_with($path, '/token/')) {
            $token = $this->loadToken();
            if (($this->isTokenExpired() || $token === null) && !empty($this->apiKey) && !empty($this->apiSecret)) {
                $token = $this->authenticate('/bt/token', [
                    'client_id'   => $this->apiKey,
                    'private_key' => $this->apiSecret
                ]);
            }
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
                $status   = $response->getStatusCode();
                $raw      = (string)$response->getBody();

                // 204 No Content => array vazio
                if ($status === 204 || $raw === '') {
                    return [];
                }

                $decoded    = $this->decodeJsonSafely($raw);
                $normalized = $this->normalizeDecoded($decoded, $raw); // <-- SEMPRE array

                if ($status >= 200 && $status < 300) {
                    if (isset($normalized['data']) && is_array($normalized['data'])) {
                        return $normalized['data'];
                    }
                    return $normalized;
                }

                // Erro: normalize também, para mapApiError não receber escalar
                $ex = $this->mapApiError($normalized, $status, $path);

                $this->logger?->warning('Canvi API error', [
                    'method' => $method,
                    'path'   => $path,
                    'status' => $status,
                    'error'  => $ex->getMessage(),
                ]);

                if (!$this->shouldRetry($response)) {
                    $this->logger?->error('API request failed after retries', [
                        'error'   => $ex->getMessage(),
                        'status'  => $status,
                        'attempt' => $attempts
                    ]);
                    throw $ex;
                }
                usleep($this->retryDelayMs * 1000);

            } catch (\R2Soft\Canvi\Exceptions\ValidationException $e) {
                throw $e;
            } catch (ApiException $e) {
                throw $e;
            } catch (\Throwable $e) {
                $this->logger?->error('Canvi HTTP error', [
                    'method' => $method,
                    'path'   => $path,
                    'error'  => $e->getMessage(),
                    'trace'  => $e->getTraceAsString()
                ]);

                if (isset($response)) {
                    $this->logger?->error('Response details', [
                        'status'  => $response->getStatusCode(),
                        'headers' => $response->getHeaders(),
                        'body'    => (string)$response->getBody()
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
            return json_decode($raw, true, 512, JSON_THROW_ON_ERROR); // assoc=true
        } catch (\JsonException) {
            return null; // não-JSON
        }
    }

    /**
     * Normaliza o retorno para SEMPRE ser array.
     * - Se $decoded é array: retorna como está.
     * - Se $decoded é escalar/bool/null mas JSON válido: ['value' => $decoded]
     * - Se não é JSON: ['raw' => $raw]
     */
    private function normalizeDecoded(mixed $decoded, string $raw): array
    {
        if (is_array($decoded)) {
            return $decoded;
        }
        // "null" JSON (literal) ou escalares válidos
        if ($decoded !== null || trim($raw) === 'null') {
            return ['value' => $decoded];
        }
        // Não-JSON (ex.: text/plain OK)
        return ['raw' => $raw];
    }


    /**
     * Converte payload de erro da API em exceções específicas.
     * - 400 com {mensagem, erros} => ValidationException
     * - outros => ApiException genérica com details
     */
    private function mapApiError(array $data, int $status, ?string $path = null): \Throwable
    {
        $msg   = $data['mensagem'] ?? $data['message'] ?? 'Erro na requisição';
        $code  = $data['code'] ?? $data['codigo'] ?? null;
        $erros = isset($data['erros']) && is_array($data['erros']) ? $data['erros'] : [];

        $this->logger?->debug('mapApiError called', [
            'status' => $status,
            'code' => $code,
            'has_erros' => !empty($erros),
            'data' => $data
        ]);

        // Heurística: se tem erros de campo ou é um código de validação, trate como validação
        $isValidationError = ($status === 400 && !empty($erros)) ||
                            ($code && (stripos((string)$code, 'VAL') === 0 || stripos((string)$msg, 'valida') !== false));

        if ($isValidationError) {
            $this->logger?->debug('Criando ValidationException', [
                'message' => $msg,
                'erros' => $erros,
                'code' => $code,
                'status' => $status
            ]);

            // Cria a exceção de validação com a mensagem e erros
            $exception = new Exceptions\ValidationException(
                $msg,
                $erros,
                $status, // Usa o status HTTP fornecido
                is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            );

            // Se a exceção tiver um método setCode, define o código original
            if (method_exists($exception, 'setCode') && $code) {
                $this->logger?->debug('Chamando setCode no ValidationException', ['code' => $code]);
                $exception->setCode($code);
            }

            $this->logger?->debug('Retornando ValidationException', [
                'class' => get_class($exception),
                'code' => $exception->getCode(),
                'message' => $exception->getMessage(),
                'has_errors' => !empty($erros)
            ]);

            return $exception;
        }

        // Fallback: ApiException com contexto
        $ex = new ApiException($msg, $status);
        if (method_exists($ex, 'setData')) {
            $ex->setData($data);
        }
        if (method_exists($ex, 'setPath') && $path) {
            $ex->setPath($path);
        }
        return $ex;
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

    /**
     * Decide retry apenas para 429/5xx (e nunca para 4xx de validação).
     */
    private function shouldRetry(ResponseInterface $r): bool
    {
        $code = $r->getStatusCode();
        if ($code === 429 || $code === 408) return true;
        if ($code >= 500) return true;
        return false;
    }
}
