<?php

declare(strict_types=1);

namespace Tests\Services;

use PDO;
use PHPUnit\Framework\TestCase;
use R2Soft\Canvi\Services\TokenStorage;

class TokenStorageTest extends TestCase
{
    private PDO $pdo;
    private TokenStorage $tokenStorage;

    protected function setUp(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('PDO SQLite não está disponível no ambiente de testes');
        }

        $this->pdo = new PDO('sqlite::memory:');
        $this->tokenStorage = new TokenStorage($this->pdo, 'test_identifier', 'wallet-1');
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

    public function testExpiredTokenIsCleared(): void
    {
        $this->tokenStorage->saveToken('token_expired', 1);
        // Força expiração direta alterando a coluna
        $this->pdo->exec('UPDATE canvi_tokens SET expires_at = ' . (time() - 10));

        $this->assertNull($this->tokenStorage->loadToken());
    }

    public function testInvalidIdentifierThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TokenStorage($this->pdo, '', 'wallet-1');
    }

    public function testInvalidWalletThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TokenStorage($this->pdo, 'id', '');
    }

    public function testInvalidExpirationThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->tokenStorage->saveToken('abc', 0);
    }

    public function testDifferentWalletsAreIsolated(): void
    {
        $walletA = new TokenStorage($this->pdo, 'test_identifier', 'wallet-A');
        $walletB = new TokenStorage($this->pdo, 'test_identifier', 'wallet-B');

        $walletA->saveToken('token-A', 3600);
        $this->assertSame('token-A', $walletA->loadToken()['token']);
        $this->assertNull($walletB->loadToken());
    }
}
