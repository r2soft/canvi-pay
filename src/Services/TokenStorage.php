<?php

declare(strict_types=1);

namespace R2Soft\Canvi\Services;

use R2Soft\Canvi\Exceptions\TokenStorageException;

final class TokenStorage
{
    private string $storagePath;
    private const TOKEN_FILENAME = 'canvi_token.json';
    private const TOKEN_EXPIRATION_MARGIN = 300; // 5 minutos de margem para renovação

    public function __construct(?string $storagePath = null)
    {
        $this->storagePath = $storagePath ?? sys_get_temp_dir();

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

    public function loadToken(): ?array
    {
        $tokenFile = $this->getTokenFilePath();

        if (!file_exists($tokenFile)) {
            return null;
        }

        try {
            $data = json_decode(file_get_contents($tokenFile), true, 512, JSON_THROW_ON_ERROR);

            // Verifica se o token expirou
            if (isset($data['expires_at']) && $data['expires_at'] < (time() - self::TOKEN_EXPIRATION_MARGIN)) {
                $this->clearToken();
                return null;
            }

            return $data;
        } catch (\JsonException $e) {
            // Se o arquivo estiver corrompido, remove e retorna null
            $this->clearToken();
            return null;
        }
    }

    public function saveToken(string $token, int $expiresIn): void
    {
        $data = [
            'token' => $token,
            'expires_at' => time() + $expiresIn,
            'created_at' => time(),
        ];

        $tokenFile = $this->getTokenFilePath();
        $result = file_put_contents(
            $tokenFile,
            json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT),
            LOCK_EX
        );

        if ($result === false) {
            throw new TokenStorageException("Falha ao salvar o token no arquivo: {$tokenFile}");
        }

        // Define permissões restritivas no arquivo
        chmod($tokenFile, 0600);
    }

    public function clearToken(): void
    {
        $tokenFile = $this->getTokenFilePath();
        if (file_exists($tokenFile)) {
            unlink($tokenFile);
        }
    }
}
