<?php

declare(strict_types=1);

namespace R2Soft\Canvi\Services;

use R2Soft\Canvi\Contracts\CanviClientInterface;
use R2Soft\Canvi\Exceptions\ApiException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Serviço de autenticação para a API Canvi.
 *
 * Esta classe é responsável por gerenciar a autenticação na API Canvi,
 * incluindo a geração e renovação de tokens de acesso.
 *
 * @package R2Soft\Canvi\Services
 * @author R2Soft <dev@r2soft.com.br>
 * @version 1.0.0
 */
class CanviAuthService
{
    private CanviClientInterface $client;
    private ?string $clientId;
    private ?string $clientSecret;
    private string $authPath;
    private ?LoggerInterface $logger;
    private $extractor;

    /**
     * Constrói uma nova instância do serviço de autenticação.
     *
     * Este construtor suporta duas assinaturas diferentes:
     * 1. Com credenciais explícitas:
     *    new CanviAuthService($client, 'client-id', 'client-secret', '/auth/token')
     *
     * 2. Com caminho personalizado e extrator:
     *    new CanviAuthService($client, '/custom/auth', $extractorFunction)
     *
     * @param CanviClientInterface $client Cliente HTTP para fazer requisições
     * @param string|array $clientIdOrPath ID do cliente ou caminho do endpoint de autenticação
     * @param string|callable|null $clientSecretOrExtractor Segredo do cliente ou função extratora de token
     * @param string $authPath Caminho do endpoint de autenticação (padrão: '/bt/token')
     * @param LoggerInterface|null $logger Instância do logger (opcional)
     *
     * @example
     * // Uso com credenciais diretas
     * $auth = new CanviAuthService(
     *     $client,
     *     'seu-client-id',
     *     'seu-client-secret',
     *     '/oauth/token',
     *     $logger
     * );
     *
     * // Uso com caminho personalizado e extrator
     * $auth = new CanviAuthService(
     *     $client,
     *     '/custom/auth',
     *     function($data) {
     *         return $data['token'] ?? null;
     *     },
     *     null,
     *     $logger
     * );
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
     * Gera um token de acesso usando as credenciais fornecidas.
     *
     * Este método autentica o cliente na API Canvi usando as credenciais fornecidas
     * e retorna um token de acesso que pode ser usado em requisições subsequentes.
     *
     * @param string|null $clientId ID do cliente (opcional, usa o configurado no construtor se não informado)
     * @param string|null $clientSecret Segredo do cliente (opcional, usa o configurado no construtor se não informado)
     * @param string|null $authPath Caminho do endpoint de autenticação (opcional, usa o configurado no construtor se não informado)
     * @return string Token de acesso obtido da API
     * @throws \InvalidArgumentException Se as credenciais forem inválidas ou ausentes
     * @throws ApiException Se ocorrer um erro na autenticação ou na comunicação com a API
     *
     * @example
     * // Usando credenciais configuradas no construtor
     * $token = $authService->generateToken();
     *
     * // Sobrescrevendo credenciais
     * $token = $authService->generateToken('outro-id', 'outro-segredo');
     *
     * // Especificando um caminho de autenticação personalizado
     * $token = $authService->generateToken(null, null, '/custom/auth');
     */
    public function generateToken(?string $clientId = null, ?string $clientSecret = null, ?string $authPath = null): string
    {
        // Se credenciais forem fornecidas, usa-as
        if ($clientId !== null || $clientSecret !== null) {
            if ($clientId === null || $clientSecret === null) {
                $error = 'Credenciais inválidas: é necessário fornecer client_id e client_secret';
                $this->logger?->error($error);
                throw new \InvalidArgumentException($error);
            }
            
            $path = $authPath ?? $this->authPath;
            // Garante que o caminho comece com '/' para consistência
            if (strpos($path, '/') !== 0) {
                $path = '/' . $path;
            }
            
            $authData = [
                'client_id' => $clientId,
                'private_key' => $clientSecret
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
     * Extrai o token da resposta de autenticação.
     *
     * Este método extrai o token de acesso da resposta da API de autenticação.
     * Por padrão, procura pelos campos 'access_token' ou 'token' na resposta.
     * Pode ser sobrescrito por um extrator personalizado configurado no construtor.
     *
     * @param array $data Dados da resposta da API de autenticação
     * @return string Token de acesso extraído
     * @throws ApiException Se o token não for encontrado na resposta
     *
     * @example
     * // Exemplo de resposta da API:
     * // {
     * //   "access_token": "eyJhbGciOiJ...",
     * //   "expires_in": 3600,
     * //   "token_type": "Bearer"
     * // }
     */
    private function extractTokenFromResponse(array $data): string
    {
        if (isset($data['access_token'])) {
            return $data['access_token'];
        }
        
        if (isset($data['token'])) {
            return $data['token'];
        }
        
        throw new ApiException('Token não encontrado na resposta');
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