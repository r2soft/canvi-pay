<?php

declare(strict_types=1);

namespace R2Soft\Canvi\Contracts;

interface TokenStorageInterface
{
    public function loadToken(): ?array;

    public function saveToken(string $token, int $expiresIn): void;

    public function clearToken(): void;
}
