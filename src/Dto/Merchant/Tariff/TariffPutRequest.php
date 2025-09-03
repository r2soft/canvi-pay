<?php
declare(strict_types=1);

namespace R2Soft\Canvi\Dto\Merchant\Tariff;

final class TariffPutRequest
{
    /** @var TariffServiceItem[] */
    public array $services;

    public function __construct(
        public ?string $cnpj     = null, // 11/14 digits only
        public ?string $account  = null,
        TariffServiceItem ...$services
    ) {
        $this->services = $services;
    }

    public static function fromArray(array $in): self
    {
        $cnpj     = isset($in['numero_cnpj']) ? self::digits((string)$in['numero_cnpj']) : null;
        $account  = isset($in['conta'])       ? trim((string)$in['conta'])     : null;

        $items = [];
        foreach (($in['servico'] ?? $in['services'] ?? []) as $row) {
            if (is_array($row)) {
                $items[] = TariffServiceItem::fromArray($row);
            }
        }

        return new self($cnpj, $account, ...$items);
    }

    public function toQuery(): array
    {
        $q = [
            'numero_cnpj' => $this->cnpj ? self::digits($this->cnpj) : null,
            'conta'       => $this->account ? trim($this->account) : null,
        ];

        return array_filter($q, static fn($v) => $v !== null && $v !== '');
    }

    public function toBody(): array
    {
        return [
            'numero_cnpj' => $this->cnpj,
            'servico'     => array_map(static fn(TariffServiceItem $i) => $i->toArray(), $this->services),
        ];
    }

    private static function digits(string $s): string
    {
        return preg_replace('/\D+/', '', $s) ?? '';
    }
}
