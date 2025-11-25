<?php

declare(strict_types=1);

namespace Tests\Support;

use R2Soft\Canvi\Contracts\TokenStorageInterface;

final class InMemoryTokenStorage implements TokenStorageInterface
{
    private const EXPIRATION_MARGIN = 300;

    private ?array $data = null;

    public function loadToken(): ?array
    {
        if ($this->data === null) {
            return null;
        }

        if (time() > ($this->data['expires_at'] - self::EXPIRATION_MARGIN)) {
            $this->clearToken();
            return null;
        }

        return $this->data;
    }

    public function saveToken(string $token, int $expiresIn): void
    {
        if ($token === '') {
            throw new \InvalidArgumentException('O token não pode ser vazio');
        }

        if ($expiresIn <= 0) {
            throw new \InvalidArgumentException('O tempo de expiração deve ser maior que zero');
        }

        $now = time();

        $this->data = [
            'token' => $token,
            'expires_at' => $now + $expiresIn,
            'created_at' => $now,
        ];
    }

    public function clearToken(): void
    {
        $this->data = null;
    }
}
