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

    /**
     * Opens a file with a lock
     *
     * @param string $file Path to the file
     * @param string $mode Mode to open the file (r, w, etc.)
     * @return resource File handle
     * @throws TokenStorageException If the file cannot be opened
     */
    private function openWithLock(string $file, string $mode)
    {
        $fh = @fopen($file, $mode);
        if ($fh === false) {
            throw new TokenStorageException('Failed to open token file: ' . $file);
        }
        return $fh;
    }

    private function loadAllTokens(): array
    {
        @mkdir($this->storagePath, 0700, true);
        $file = $this->getTokenFilePath();
        if (!file_exists($file)) {
            return [];
        }

        try {
            $fh = $this->openWithLock($file, 'r');
            
            if (!flock($fh, LOCK_SH)) {
                throw new TokenStorageException('Failed to acquire shared lock on token file');
            }
            
            $json = stream_get_contents($fh) ?: '{}';
            
            // Tenta decodificar o JSON, se falhar, retorna array vazio
            $data = json_decode($json, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Se o JSON for inválido, remove o arquivo corrompido
                fclose($fh);
                @unlink($file);
                return [];
            }
            
            return is_array($data) ? $data : [];
            
        } catch (\Exception $e) {
            // Em caso de erro, remove o arquivo corrompido
            @unlink($file);
            return [];
        } finally {
            if (isset($fh) && is_resource($fh)) {
                flock($fh, LOCK_UN);
                fclose($fh);
            }
        }
    }

    private function saveAllTokens(array $tokens): void
    {
        @mkdir($this->storagePath, 0700, true);
        $file = $this->storagePath . DIRECTORY_SEPARATOR . self::TOKEN_FILENAME;

        $fh = $this->openWithLock($file, 'c+');
        try {
            flock($fh, LOCK_EX);
            ftruncate($fh, 0);
            fwrite($fh, json_encode($tokens, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
            fflush($fh);
            // Permissões restritivas (somente dono)
            @chmod($file, 0600);
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
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
