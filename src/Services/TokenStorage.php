<?php

declare(strict_types=1);

namespace R2Soft\Canvi\Services;

use R2Soft\Canvi\Exceptions\TokenStorageException;

final class TokenStorage
{
    private string $storagePath;
    private string $identifier;
    private const TOKEN_FILENAME = 'canvi_token.json';
    private const TOKEN_EXPIRATION_MARGIN = 300; // 5 minutos de margem para renovação

    public function __construct(?string $storagePath = null, ?string $identifier = 'default')
    {
        if (empty($identifier)) {
            throw new \InvalidArgumentException('O identificador não pode ser vazio');
        }
        
        $this->storagePath = $storagePath ?? sys_get_temp_dir();
        $this->identifier = $identifier;

        // Garante que o diretório existe e tem permissões corretas
        if (!is_dir($this->storagePath) && !mkdir($this->storagePath, 0755, true)) {
            throw new TokenStorageException(
                "Não foi possível criar o diretório de armazenamento: {$this->storagePath}"
            );
        }

        if (!is_writable($this->storagePath)) {
            throw new TokenStorageException(
                "O diretório de armazenamento não tem permissão de escrita: {$this->storagePath}"
            );
        }
    }

    public function getTokenFilePath(): string
    {
        return rtrim($this->storagePath, '/') . '/' . self::TOKEN_FILENAME;
    }

    private function loadAllTokens(): array
    {
        $tokenFile = $this->getTokenFilePath();
        
        if (!file_exists($tokenFile) || !is_readable($tokenFile)) {
            return [];
        }

        $content = @file_get_contents($tokenFile);
        if ($content === false) {
            return [];
        }
        
        if (empty($content)) {
            return [];
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            
            // Se for o formato antigo, converte para o novo formato
            if ($data !== null && isset($data['token'])) {
                return ['default' => $data];
            }
            
            return is_array($data) ? $data : [];
        } catch (\JsonException $e) {
            // Se o arquivo estiver corrompido, remove-o
            @unlink($tokenFile);
            return [];
        }
    }

    private function saveAllTokens(array $tokens): void
    {
        $tokenFile = $this->getTokenFilePath();
        $result = file_put_contents(
            $tokenFile,
            json_encode($tokens, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT),
            LOCK_EX
        );

        if ($result === false) {
            throw new TokenStorageException("Falha ao salvar os tokens no arquivo: {$tokenFile}");
        }

        // Define permissões restritivas no arquivo
        chmod($tokenFile, 0600);
    }

    public function loadToken(): ?array
    {
        try {
            $tokens = $this->loadAllTokens();
            
            if (!isset($tokens[$this->identifier])) {
                return null;
            }
            
            $tokenData = $tokens[$this->identifier];
            
            if (!is_array($tokenData) || !isset($tokenData['token']) || !isset($tokenData['expires_at'])) {
                $this->clearToken();
                return null;
            }
            
            // Verifica se o token expirou
            if ($tokenData['expires_at'] < (time() - self::TOKEN_EXPIRATION_MARGIN)) {
                $this->clearToken();
                return null;
            }
            
            return $tokenData;
        } catch (\Throwable $e) {
            // Em caso de qualquer erro, limpa o token e retorna null
            $this->clearToken();
            return null;
        }
    }

    public function saveToken(string $token, int $expiresIn): void
    {
        if (empty($token)) {
            throw new \InvalidArgumentException('O token não pode ser vazio');
        }
        
        if ($expiresIn <= 0) {
            throw new \InvalidArgumentException('O tempo de expiração deve ser maior que zero');
        }
        
        $tokens = $this->loadAllTokens();
        
        $tokens[$this->identifier] = [
            'token' => $token,
            'expires_at' => time() + $expiresIn,
            'created_at' => time(),
        ];
        
        $this->saveAllTokens($tokens);
    }

    public function clearToken(): void
    {
        $tokens = $this->loadAllTokens();
        
        if (isset($tokens[$this->identifier])) {
            unset($tokens[$this->identifier]);
            $this->saveAllTokens($tokens);
        }
    }
}
