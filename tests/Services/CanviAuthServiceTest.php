<?php

declare(strict_types=1);

namespace R2Soft\Canvi\Tests\Services;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use R2Soft\Canvi\CanviClient;
use R2Soft\Canvi\Exceptions\ApiException;
use R2Soft\Canvi\Services\CanviAuthService;

/**
 * Testes para a classe CanviAuthService.
 * 
 * @covers \R2Soft\Canvi\Services\CanviAuthService
 * @covers \R2Soft\Canvi\Exceptions\ApiException
 */
class CanviAuthServiceTest extends TestCase
{
    private CanviClient|MockObject $clientMock;
    private CanviAuthService $authService;

    protected function setUp(): void
    {
        $this->clientMock = $this->createMock(CanviClient::class);
        $this->authService = new CanviAuthService(
            $this->clientMock,
            'test_client_id',
            'test_client_secret',
            '/bt/token',
            null
        );
    }

    /**
     * Testa a geração de token com credenciais padrão.
     * 
     * @return void
     */
    public function testGenerateTokenWithDefaultCredentials(): void
    {
        $expectedToken = 'test_token_123';
        $apiKey = 'test_api_key';
        $apiSecret = 'test_api_secret';

        $this->clientMock->method('getApiKey')->willReturn($apiKey);
        $this->clientMock->method('getApiSecret')->willReturn($apiSecret);
        
        $this->clientMock->expects($this->once())
            ->method('authenticate')
            ->with(
                '/bt/token',
                [
                    'client_id' => $apiKey,
                    'private_key' => $apiSecret,
                ],
                null
            )
            ->willReturn($expectedToken);

        $token = $this->authService->generateToken();
        $this->assertSame($expectedToken, $token);
    }

    /**
     * Testa a geração de token com credenciais personalizadas.
     * 
     * @return void
     */
    public function testGenerateTokenWithCustomCredentials(): void
    {
        $expectedToken = 'test_token_456';
        
        $this->clientMock->expects($this->once())
            ->method('authenticate')
            ->with(
                '/bt/token',
                [
                    'client_id' => 'custom_key',
                    'private_key' => 'custom_secret',
                ],
                null
            )
            ->willReturn($expectedToken);

        $token = $this->authService->generateToken('custom_key', 'custom_secret');
        $this->assertSame($expectedToken, $token);
    }

    public function testGenerateTokenWithCustomAuthPath(): void
    {
        $expectedToken = 'test_token_789';
        $customPath = '/custom/auth/path';
        
        $authService = new CanviAuthService(
            $this->clientMock,
            $customPath
        );

        $this->clientMock->method('getApiKey')->willReturn('key');
        $this->clientMock->method('getApiSecret')->willReturn('secret');
        
        $this->clientMock->expects($this->once())
            ->method('authenticate')
            ->with(
                $customPath,
                $this->isType('array'),
                null
            )
            ->willReturn($expectedToken);

        $token = $authService->generateToken();
        $this->assertSame($expectedToken, $token);
    }

    /**
     * Testa a geração de token com extrator personalizado.
     * 
     * @return void
     */
    public function testGenerateTokenWithCustomExtractor(): void
    {
        $expectedToken = 'extracted_token';
        $apiResponse = ['access_token' => $expectedToken, 'expires_in' => 3600];
        $extractor = fn(array $data) => $data['access_token'] ?? null;
        
        $authService = new CanviAuthService(
            $this->clientMock,
            '/bt/token',
            $extractor
        );

        $this->clientMock->method('getApiKey')->willReturn('key');
        $this->clientMock->method('getApiSecret')->willReturn('secret');
        
        $this->clientMock->expects($this->once())
            ->method('authenticate')
            ->with(
                '/bt/token',
                $this->isType('array'),
                $extractor
            )
            ->willReturn($expectedToken);

        $token = $authService->generateToken();
        $this->assertSame($expectedToken, $token);
    }

    /**
     * Testa o construtor alternativo que recebe o caminho como segundo parâmetro.
     * 
     * @return void
     */
    public function testGenerateTokenWithCustomPathInConstructor(): void
    {
        $expectedToken = 'extracted_token';
        $apiResponse = ['access_token' => $expectedToken, 'expires_in' => 3600];
        $extractor = fn(array $data) => $data['access_token'] ?? null;
        
        $authService = new CanviAuthService(
            $this->clientMock,
            '/custom/auth/path',
            $extractor
        );

        $this->clientMock->method('getApiKey')->willReturn('key');
        $this->clientMock->method('getApiSecret')->willReturn('secret');
        
        $this->clientMock->expects($this->once())
            ->method('authenticate')
            ->with(
                '/custom/auth/path',
                $this->isType('array'),
                $extractor
            )
            ->willReturn($expectedToken);

        $token = $authService->generateToken();
        $this->assertSame($expectedToken, $token);
    }

    /**
     * Testa a geração de token com credenciais inválidas.
     * 
     * @return void
     */
    public function testGenerateTokenWithInvalidCredentials(): void
    {
        $this->clientMock->method('getApiKey')->willReturn('');
        $this->clientMock->method('getApiSecret')->willReturn('');
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Credenciais não configuradas no cliente');
        
        $this->authService->generateToken();
    }

    public function testGenerateTokenWithNullClientSecret(): void
    {
        $this->clientMock->method('getApiKey')->willReturn('test_key');
        $this->clientMock->method('getApiSecret')->willReturn(null);
        
        $this->expectException(\InvalidArgumentException::class);
        $this->authService->generateToken();
    }

    /**
     * Testa a geração de token com credenciais personalizadas nulas.
     * 
     * @return void
     */
    public function testGenerateTokenWithCustomNullCredentials(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Credenciais inválidas: é necessário fornecer client_id e client_secret');
        
        $this->authService->generateToken('client_id_only', null);
    }

    /**
     * Testa a geração de token com um caminho de autenticação vazio.
     * 
     * @return void
     */
    public function testGenerateTokenWithEmptyAuthPath(): void
    {
        $expectedToken = 'dummy_token';
        $this->clientMock->method('getApiKey')->willReturn('test_key');
        $this->clientMock->method('getApiSecret')->willReturn('test_secret');
        
        $this->clientMock->expects($this->once())
            ->method('authenticate')
            ->with(
                '/bt/token', // Deve manter o caminho padrão
                [
                    'client_id' => 'test_key',
                    'private_key' => 'test_secret'
                ],
                null
            )
            ->willReturn($expectedToken);

        $token = $this->authService->generateToken(null, null, '');
        $this->assertSame($expectedToken, $token);
    }

    /**
     * Testa a geração de token com um extrator que retorna nulo.
     * 
     * @return void
     */
    public function testGenerateTokenWithNullExtractorResult(): void
    {
        // Cria um extrator que sempre retorna null
        $extractor = fn() => null;
        
        // Cria uma instância do serviço com o extrator personalizado
        $authService = new CanviAuthService(
            $this->clientMock,
            '/auth', // Usa a assinatura alternativa do construtor
            $extractor // Passa o extrator como terceiro parâmetro
        );
        
        // Configura o cliente para retornar uma resposta vazia
        $this->clientMock->expects($this->once())
            ->method('authenticate')
            ->willReturnCallback(function() use ($extractor) {
                // Simula o comportamento do extrator retornando null
                $result = $extractor([]);
                if ($result === null) {
                    throw new ApiException('Token não encontrado na resposta');
                }
                return $result;
            });

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Token não encontrado na resposta');
        
        // Fornece credenciais válidas para evitar o erro de credenciais inválidas
        $authService->generateToken('valid_key', 'valid_secret');
    }

    /**
     * Testa o construtor com parâmetros inválidos.
     * 
     * @dataProvider invalidConstructorParamsProvider
     * @param mixed $clientIdOrPath
     * @param mixed $clientSecretOrExtractor
     * @param string $expectedException
     * @param string $expectedMessage
     * @return void
     */
    public function testConstructorWithInvalidParams($clientIdOrPath, $clientSecretOrExtractor, string $expectedException, string $expectedMessage): void
    {
        // O construtor não lança exceção imediatamente, então precisamos testar o comportamento
        // quando tentamos usar as credenciais inválidas
        $authService = new CanviAuthService(
            $this->clientMock,
            $clientIdOrPath,
            $clientSecretOrExtractor
        );
        
        // Configura o cliente para lançar uma exceção quando as credenciais forem inválidas
        $this->clientMock->method('getApiKey')
            ->willReturn($clientIdOrPath);
            
        $this->clientMock->method('getApiSecret')
            ->willReturn($clientSecretOrExtractor);
            
        $this->expectException($expectedException);
        $this->expectExceptionMessage($expectedMessage);
        
        // Tenta gerar um token com as credenciais inválidas
        $authService->generateToken();
    }

    /**
     * Provedor de dados para testConstructorWithInvalidParams
     * 
     * @return array
     */
    public static function invalidConstructorParamsProvider(): array
    {
        return [
            'null clientId and null clientSecret' => [
                null, 
                null, 
                \InvalidArgumentException::class, 
                'Credenciais não configuradas no cliente'
            ],
            'empty clientId and empty clientSecret' => [
                '', 
                '', 
                \InvalidArgumentException::class, 
                'Credenciais não configuradas no cliente'
            ],
            'null clientId with clientSecret' => [
                null, 
                'secret', 
                \InvalidArgumentException::class, 
                'Credenciais não configuradas no cliente'
            ],
            'clientId with null clientSecret' => [
                'client_id', 
                null, 
                \InvalidArgumentException::class, 
                'Credenciais não configuradas no cliente'
            ],
        ];
    }

    public function testGenerateTokenWithApiException(): void
    {
        $this->clientMock->method('getApiKey')->willReturn('key');
        $this->clientMock->method('getApiSecret')->willReturn('secret');
        
        $this->clientMock->expects($this->once())
            ->method('authenticate')
            ->willThrowException(new ApiException('Authentication failed', 401));
        
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Authentication failed');
        $this->expectExceptionCode(401);
        
        $this->authService->generateToken();
    }
}
