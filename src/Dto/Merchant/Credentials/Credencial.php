<?php
declare(strict_types=1);

namespace R2Soft\Canvi\Dto\Merchant\Credentials;

use DateTimeImmutable;
use JsonSerializable;

final class Credencial implements JsonSerializable
{
    public function __construct(
        public string $conta,
        public string $nome_fantasia,
        public string $numero_cnpj,   // somente dígitos
        public string $client_id,
        public string $private_key,
        public ?DateTimeImmutable $data_expiracao = null,
        public array $extras = []
    ) {}

    public static function fromArray(array $row): self
    {
        $numeroCnpj = isset($row['numero_cnpj'])
            ? preg_replace('/\D+/', '', (string)$row['numero_cnpj'])
            : '';

        $exp = null;
        if (!empty($row['data_expiracao'])) {
            $s = (string) $row['data_expiracao'];
            $exp = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $s) ?: new DateTimeImmutable($s);
        }

        $known  = ['conta','nome_fantasia','numero_cnpj','client_id','private_key','data_expiracao'];
        $extras = array_diff_key($row, array_flip($known));

        return new self(
            conta:         (string)($row['conta'] ?? ''),
            nome_fantasia: (string)($row['nome_fantasia'] ?? ''),
            numero_cnpj:   (string)$numeroCnpj,
            client_id:     (string)($row['client_id'] ?? ''),
            private_key:   (string)($row['private_key'] ?? ''),
            data_expiracao:$exp,
            extras:        $extras
        );
    }

    public function toArray(): array
    {
        return array_merge([
            'conta'          => $this->conta,
            'nome_fantasia'  => $this->nome_fantasia,
            'numero_cnpj'    => $this->numero_cnpj,
            'client_id'      => $this->client_id,
            'private_key'    => $this->private_key,
            'data_expiracao' => $this->data_expiracao?->format('Y-m-d H:i:s'),
        ], $this->extras);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
