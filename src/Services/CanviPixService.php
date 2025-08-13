<?php
declare(strict_types=1);

namespace R2Soft\Canvi\Services;

use R2Soft\Canvi\CanviClient;

final class CanviPixService
{
    public function __construct(private readonly CanviClient $client) {}

    public function createDynamic(array $payload, ?string $idempotencyKey = null): array
    {
        return $this->client->post('/api/v1/pix/dynamic', $payload, $idempotencyKey);
    }

    public function get(string $chargeId): array
    {
        return $this->client->get("/charges/{$chargeId}");
    }

    public function refund(string $chargeId, int $amount): array
    {
        return $this->client->post("/charges/{$chargeId}/refunds", ['amount' => $amount]);
    }
}
