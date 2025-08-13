<?php
declare(strict_types=1);

namespace R2Soft\Canvi\Services;

use R2Soft\Canvi\CanviClient;

final class CanviBoletoService implements ChargeServiceInterface
{
    public function __construct(private readonly CanviClient $client) {}

    public function create(array $payload, ?string $idempotencyKey = null): array
    {

        return $this->client->post('/api/v1/charges/boleto', $payload, $idempotencyKey);
    }

    public function get(string $chargeId): array
    {
        return $this->client->get("/api/v1/charges/{$chargeId}");
    }

    public function cancel(string $chargeId): array
    {
        return $this->client->post("/api/v1/charges/{$chargeId}/cancel", []);
    }

    public function list(array $filters = []): array
    {
        return $this->client->get('/api/v1/charges/boleto', $filters);
    }
}
