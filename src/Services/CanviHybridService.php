<?php
declare(strict_types=1);

namespace R2Soft\Canvi\Services;

use R2Soft\Canvi\CanviClient;

final class CanviHybridService
{
    public function __construct(private CanviClient $client) {}

    public function create(array $payload, ?string $idempotencyKey = null): array
    {
        // Ex.: '/charges/hybrid' — “boleto + QR Pix” (ajuste path)
        return $this->client->post('/charges/hybrid', $payload, $idempotencyKey);
    }
}
