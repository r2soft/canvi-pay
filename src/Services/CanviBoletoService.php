<?php
declare(strict_types=1);

namespace R2Soft\Canvi\Services;

use R2Soft\Canvi\CanviClient;

final class CanviBoletoService implements ChargeServiceInterface
{
    public function __construct(private CanviClient $client) {}

    public function create(array $payload, ?string $idempotencyKey = null): array
    {
        return $this->client->post('/bt/boleto', $payload, $idempotencyKey);
    }

    public function get(string $chargeId): array
    {
        return $this->client->get("/bt/consulta_boleto/{$chargeId}");
    }

    public function cancel(string $chargeId): array
    {
        return $this->client->post("/bt/cancelar_boleto/{$chargeId}/cancel", []);
    }

    public function list(array $filters = []): array
    {
        return $this->client->get('/bt/consulta_boletos', $filters);
    }
}
