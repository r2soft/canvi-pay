<?php
declare(strict_types=1);

namespace R2Soft\Canvi\Dto\Merchant\Tariff;

use JsonSerializable;

final class TariffResponse implements JsonSerializable
{
    /** @var TariffServiceItem[] */
    public array $services;

    public function __construct(TariffServiceItem ...$services)
    {
        $this->services = $services;
    }

    public static function fromApi(array $response): self
    {
        debug($response);
        $rows = $response['data'] ?? $response['servico'] ?? [];

        $items = [];
        foreach ((array)$rows as $row) {
            if (is_array($row)) {
                $items[] = TariffServiceItem::fromArray($row);
            }
        }

        return new self(...$items);
    }

    public function jsonSerialize(): array
    {
        return array_map(
            static fn(TariffServiceItem $s) => $s->toArray(),
            $this->services
        );
    }
}
