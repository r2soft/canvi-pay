<?php
declare(strict_types=1);

namespace R2Soft\Canvi\Dto\Merchant\Credentials;

final class CredenciaisRequest
{
    public function __construct(
        public ?string $numero_cnpj = null, // 11/14 dígitos (somente números)
        public ?string $client_id   = null,
        public ?string $conta       = null
    ) {}

    public static function fromArray(array $in): self
    {
        debug('');
        return new self(
            numero_cnpj: isset($in['numero_cnpj']) ? self::digits((string)$in['numero_cnpj']) : null,
            client_id:   isset($in['client_id'])   ? trim((string)$in['client_id']) : null,
            conta:       isset($in['conta'])       ? trim((string)$in['conta']) : null
        );
    }

    /** Monta o array de query, já normalizado e sem nulos/vazios. */
    public function toQuery(): array
    {
        $q = [
            'numero_cnpj' => $this->numero_cnpj ? self::digits($this->numero_cnpj) : null,
            'client_id'   => $this->client_id   ? trim($this->client_id)          : null,
            'conta'       => $this->conta       ? trim($this->conta)              : null,
        ];

        return array_filter($q, static fn($v) => $v !== null && $v !== '');
    }

    private static function digits(string $s): string
    {
        return preg_replace('/\D+/', '', $s) ?? '';
    }
}
