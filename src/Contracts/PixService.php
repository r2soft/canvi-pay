<?php

namespace R2Soft\Canvi\Contracts;
interface PixService
{
    public function createDynamic(array $p, ?string $k = null): array;

    public function refund(string $id, int $amt): array;
}