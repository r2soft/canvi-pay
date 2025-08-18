<?php

declare(strict_types=1);

namespace R2Soft\Canvi\Services;

use R2Soft\Canvi\Contracts\CanviClientInterface;
use R2Soft\Canvi\Exceptions\ApiException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class CanviAuthService
{
    private CanviClientInterface $client;
    private ?string $clientId;
    private ?string $clientSecret;
    private string $authPath;
    private ?LoggerInterface $logger;
    private $extractor;

    /**
     * @param CanviClientInterface $client Cliente HTTP para fazer requisições
     * @param string|array $clientIdOrPath ID do cliente para autenticação ou caminho do endpoint
     * @param string|callable|null $clientSecretOrExtractor Segredo do cliente ou extrator personalizado
     * @param string $authPath Caminho do endpoint de autenticação
     * @param LoggerInterface|null $logger Instância do logger (opcional)
     */
    public function __construct(
        CanviClientInterface $client,
        $clientIdOrPath = null,
        $clientSecretOrExtractor = null,
        string $authPath = '/bt/token',
        ?LoggerInterface $logger = null
    ) {
        $this->client = $client;
        $this->logger = $logger;
        
        // Handle different constructor signatures
        if (is_string($clientIdOrPath) && strpos($clientIdOrPath, '/') === 0) {
            // Second parameter is the auth path
            $this->authPath = $clientIdOrPath; // Mantém a barra inicial
            $this->clientId = null;
            $this->clientSecret = null;
            $this->extractor = is_callable($clientSecretOrExtractor) ? $clientSecretOrExtractor : null;
        } else {
            // Standard constructor with client ID/secret
            $this->clientId = $clientIdOrPath;
            $this->clientSecret = is_string($clientSecretOrExtractor) ? $clientSecretOrExtractor : null;
            $this->authPath = $authPath; // Já vem com a barra inicial do parâmetro padrão
            $this->extractor = is_callable($clientSecretOrExtractor) ? $clientSecretOrExtractor : null;
        }
    }

    /**
     * Gera um novo token de acesso
     * 
     * @param array|null $credentials Credenciais opcionais para autenticação
     * @return string O token de acesso
     * @throws \InvalidArgumentException Se as credenciais forem inválidas
     * @throws ApiException Se a autenticação falhar
     */
    public function generateToken(?array $credentials = null): string
    {
        // Se credenciais forem fornecidas, usa-as
        if ($credentials !== null) {
            if (!isset($credentials['client_id'], $credentials['private_key'])) {
                $error = 'Credenciais inválidas: é necessário fornecer client_id e private_key';
                $this->logger?->error($error);
                throw new \InvalidArgumentException($error);
            }
            
            $path = $this->authPath;
            // Garante que o caminho comece com '/' para consistência
            if (strpos($path, '/') !== 0) {
                $path = '/' . $path;
            }
            
            $authData = [
                'client_id' => $credentials['client_id'],
                'private_key' => $credentials['private_key']
            ];
            
            $this->logger?->debug('Autenticando com credenciais fornecidas', [
                'path' => $path,
                'auth_data' => array_merge($authData, ['private_key' => '***'])
            ]);
            
            try {
                return $this->client->authenticate(
                    $path,
                    $authData,
                    $this->extractor
                );
            } catch (\Exception $e) {
                $this->logger?->error('Falha na autenticação', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                if ($e instanceof ApiException) {
                    throw $e;
                }
                
                throw new ApiException('Falha na autenticação: ' . $e->getMessage(), $e->getCode(), $e);
            }
        }
        
        // Se não houver credenciais fornecidas, tenta obter do cliente
        if (method_exists($this->client, 'getApiKey') && method_exists($this->client, 'getApiSecret')) {
            $apiKey = $this->client->getApiKey();
            $apiSecret = $this->client->getApiSecret();
            
            if (empty($apiKey) || empty($apiSecret)) {
                $error = 'Credenciais não configuradas no cliente';
                $this->logger?->error($error);
                throw new \InvalidArgumentException($error);
            }
            
            $path = $this->authPath;
            // Garante que o caminho comece com '/' para consistência
            if (strpos($path, '/') !== 0) {
                $path = '/' . $path;
            }
            
            $authData = [
                'client_id' => $apiKey,
                'private_key' => $apiSecret
            ];
            
            $this->logger?->debug('Autenticando com credenciais padrão', [
                'path' => $path,
                'auth_data' => array_merge($authData, ['private_key' => '***'])
            ]);
            
            try {
                return $this->client->authenticate(
                    $path,
                    $authData,
                    $this->extractor
                );
            } catch (\Exception $e) {
                $this->logger?->error('Falha na autenticação', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                if ($e instanceof ApiException) {
                    throw $e;
                }
                
                throw new ApiException('Falha na autenticação: ' . $e->getMessage(), $e->getCode(), $e);
            }
        }
        
        throw new \InvalidArgumentException('Não foi possível obter as credenciais de autenticação');
    }
    
    /**
     * Resolve os dados de autenticação a partir de diferentes fontes
     */
    private function resolveAuthData(?array $credentials): array
    {
        // Use provided credentials if available
        if ($credentials !== null) {
            return [
                'client_id' => $credentials['client_id'] ?? $this->clientId,
                'private_key' => $credentials['private_key'] ?? $this->clientSecret,
            ];
        }
        
        // Try to get from client methods if available
        $clientId = $this->clientId;
        $clientSecret = $this->clientSecret;
        
        if (method_exists($this->client, 'getApiKey')) {
            $clientId = $this->client->getApiKey();
        }
        
        if (method_exists($this->client, 'getApiSecret')) {
            $clientSecret = $this->client->getApiSecret();
        }
        
        return [
            'client_id' => $clientId,
            'private_key' => $clientSecret,
        ];
    }
    
    /**
     * Analisa a resposta da API e retorna os dados decodificados
     * 
     * @param ResponseInterface $response Resposta HTTP
     * @return array Dados decodificados
     * @throws ApiException Se a resposta for inválida
     */
    private function parseResponse(ResponseInterface $response): array
    {
        $content = (string) $response->getBody();
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ApiException('Resposta da API inválida: ' . json_last_error_msg());
        }
        
        if (!is_array($data)) {
            throw new ApiException('Resposta da API em formato inválido');
        }
        
        if (!isset($data['access_token'])) {
            $error = $data['error'] ?? 'Erro desconhecido';
            $description = $data['error_description'] ?? '';
            throw new ApiException(sprintf('Erro na autenticação: %s %s', $error, $description));
        }
        
        return $data;
    }
    
}