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

final class CanviClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $apiKey = null,
        private readonly ?string $apiSecret = null,
        private readonly int $timeout = 10,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?Psr18Client $httpClient = null,
        private readonly ?RequestFactoryInterface $requestFactory = null,
        private readonly ?StreamFactoryInterface $streamFactory = null,
        private readonly int $maxRetries = 0,
        private readonly int $retryDelayMs = 250,
        private readonly ?string $userAgent = 'r2soft-canvi-php/1.x'
    ) {}

    public function post(string $path, array $json, ?string $idempotencyKey = null): array
    {
        return $this->sendJson('POST', $path, $json, $idempotencyKey);
    }

    public function get(string $path, array $query = []): array
    {
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
        if ($this->apiKey) {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
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
        beginning:
        $attempts++;

        try {
            $response = $client->sendRequest($request);
        } catch (\Throwable $e) {
            $this->logger?->error('Canvi HTTP error', [
                'method' => $method,
                'path'   => $path,
                'error'  => $e->getMessage(),
            ]);
            throw new ApiException('HTTP transport error', 0, $e);
        }

        $status = $response->getStatusCode();
        $raw = (string)$response->getBody();
        $data = $this->decodeJsonSafely($raw);

        if ($status >= 200 && $status < 300) {
            return is_array($data) ? $data : [];
        }

        // Mapear erros
        $ex = $this->mapApiError($status, $data, $raw);
        $this->logger?->warning('Canvi API error', [
            'method' => $method,
            'path'   => $path,
            'status' => $status,
            'error'  => $ex->getMessage(),
        ]);

        // Retry para 429/5xx se habilitado
        if ($this->shouldRetry($status, $attempts)) {
            usleep($this->retryDelayMs * 1000);
            goto beginning;
        }

        throw $ex;
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
        $message = $data['message'] ?? $data['error'] ?? 'Unknown error';
        $code = (int)($data['code'] ?? 0);

        return match (true) {
            $status === 401 || $status === 403 => new UnauthorizedException($message, $code),
            $status === 400 || $status === 422 => new ValidationException($message, $code, $data['fields'] ?? []),
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