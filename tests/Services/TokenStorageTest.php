<?php

declare(strict_types=1);

namespace R2Soft\Canvi\Tests\Services;

use PHPUnit\Framework\TestCase;
use R2Soft\Canvi\Exceptions\TokenStorageException;
use R2Soft\Canvi\Services\TokenStorage;

class TokenStorageTest extends TestCase
{
    private string $testDir;
    private string $testFile;
    private TokenStorage $tokenStorage;

    protected function setUp(): void
    {
        // Cria um diretório temporário para os testes
        $this->testDir = sys_get_temp_dir() . '/canvi_test_' . uniqid();
        mkdir($this->testDir, 0755, true);

        $this->testFile = $this->testDir . '/canvi_token.json';
        $this->tokenStorage = new TokenStorage($this->testDir, 'test_identifier');
    }

    protected function tearDown(): void
    {
        // Limpa os arquivos de teste após cada teste
        if (file_exists($this->testFile)) {
            unlink($this->testFile);
        }
        if (is_dir($this->testDir)) {
            rmdir($this->testDir);
        }
    }

    public function testSaveAndLoadToken(): void
    {
        $token = 'test_token_123';
        $expiresIn = 3600; // 1 hora

        // Salva o token
        $this->tokenStorage->saveToken($token, $expiresIn);

        // Carrega o token salvo
        $tokenData = $this->tokenStorage->loadToken();

        // Verifica se o token foi salvo e carregado corretamente
        $this->assertIsArray($tokenData);
        $this->assertArrayHasKey('token', $tokenData);
        $this->assertArrayHasKey('expires_at', $tokenData);
        $this->assertArrayHasKey('created_at', $tokenData);
        $this->assertSame($token, $tokenData['token']);
        $this->assertGreaterThan(time(), $tokenData['expires_at']);
        $this->assertLessThanOrEqual(time() + $expiresIn, $tokenData['expires_at']);
    }

    public function testLoadTokenWithInvalidFile(): void
    {
        // Cria um arquivo de token inválido
        file_put_contents($this->testFile, 'invalid json');

        // Tenta carregar o token (deve retornar null e remover o arquivo inválido)
        $token = $this->tokenStorage->loadToken();

        $this->assertNull($token);
        $this->assertFileDoesNotExist($this->testFile);
    }

    public function testClearToken(): void
    {
        // Salva um token
        $this->tokenStorage->saveToken('test_token', 3600);

        // Verifica se o token foi salvo
        $this->assertNotNull($this->tokenStorage->loadToken());

        // Limpa o token
        $this->tokenStorage->clearToken();

        // Verifica se o token foi removido
        $this->assertNull($this->tokenStorage->loadToken());
    }

    public function testInvalidStorageDirectory(): void
    {
        // Marca o teste como pulado no ambiente Docker
        if (file_exists('/.dockerenv') || getenv('DOCKER') === 'true') {
            $this->markTestSkipped('Teste de diretório inválido não é confiável no ambiente Docker');
            return;
        }
        
        // Tenta criar um TokenStorage com um caminho inválido
        $invalidDir = '/proc/invalid_dir_' . uniqid();
        
        $this->expectException(TokenStorageException::class);
        
        try {
            new TokenStorage($invalidDir);
        } catch (\Exception $e) {
            // Verifica se a mensagem de erro contém a string esperada
            $this->assertStringContainsString('não foi possível', strtolower($e->getMessage()));
            throw $e;
        }
    }

    public function testUnwritableStorageDirectory(): void
    {
        // Marca o teste como pulado no ambiente Docker
        if (file_exists('/.dockerenv') || getenv('DOCKER') === 'true') {
            $this->markTestSkipped('Teste de diretório sem permissão não é confiável no ambiente Docker');
            return;
        }
        
        // Tenta criar um diretório sem permissão de escrita
        $unwritableDir = sys_get_temp_dir() . '/canvi_test_unwritable_' . uniqid();
        
        if (!@mkdir($unwritableDir, 0555, true)) {
            $this->markTestSkipped('Não foi possível criar diretório sem permissão de escrita');
            return;
        }
        
        try {
            // Torna o diretório não gravável
            if (!@chmod($unwritableDir, 0555)) {
                $this->markTestSkipped('Não foi possível alterar as permissões do diretório');
                return;
            }
            
            $this->expectException(TokenStorageException::class);
            new TokenStorage($unwritableDir);
        } finally {
            // Restaura as permissões para poder remover o diretório
            @chmod($unwritableDir, 0755);
            @rmdir($unwritableDir);
        }
    }
}