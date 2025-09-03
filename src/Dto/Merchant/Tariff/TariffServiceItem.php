<?php
declare(strict_types=1);

namespace R2Soft\Canvi\Dto\Merchant\Tariff;

use JsonSerializable;

final class TariffServiceItem implements JsonSerializable
{
    public function __construct(
        public string $key,       // e.g. 'pixCashin', 'boletoCashin'
        public string $type,      // 'percentual' | 'valor'
        public string $value      // keep as string "0.70", "4.00"
    ) {}

    public static function fromArray(array $row): self
    {
        $key   = trim((string)($row['chave'] ?? $row['key'] ?? ''));
        $type  = strtolower(trim((string)($row['tipo'] ?? $row['type'] ?? '')));
        $value = trim((string)($row['valor'] ?? $row['value'] ?? ''));

        return new self($key, $type, $value);
    }

    public function toArray(): array
    {
        return [
            'chave' => $this->key,
            'tipo'  => $this->type,
            'valor' => $this->value,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
