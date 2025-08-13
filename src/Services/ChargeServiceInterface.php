<?php
namespace R2Soft\Canvi\Services;

interface ChargeServiceInterface
{
    public function create(array $payload, ?string $idempotencyKey = null): array;
    public function get(string $chargeId): array;
    public function cancel(string $chargeId): array;

    /**
     * @param array{cursor?:string, limit?:int, status?:string} $filters
     */
    public function list(array $filters = []): array;
}