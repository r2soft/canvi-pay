<?php
declare(strict_types=1);

Namespace R2Soft\Canvi\Dto\Merchant;

use App\Exception\DomainValidationException;

final class ComercianteCadastro
{
    public function __construct(
        public GeralDto        $geral,
        public ResponsavelDto  $responsavel,
        public string          $id_externo,
        public string          $tipo_conta, // "liquidacao" | "pagamento"
        public ConfiguracaoDto $configuracao,
    ) {
        $this->validate();
    }

    public static function fromArray(array $a): self
    {
        return new self(
            geral:         GeralDto::fromArray((array)($a['geral'] ?? [])),
            responsavel:   ResponsavelDto::fromArray((array)($a['responsavel'] ?? [])),
            id_externo:    (string)($a['id_externo'] ?? ''),
            tipo_conta:    (string)($a['tipo_conta'] ?? ''),
            configuracao:  ConfiguracaoDto::fromArray((array)($a['configuracao'] ?? [])),
        );
    }

    public function toArray(): array
    {
        return [
            'geral'         => $this->geral->toArray(),
            'responsavel'   => $this->responsavel->toArray(),
            'id_externo'    => $this->id_externo,
            'tipo_conta'    => $this->tipo_conta,
            'configuracao'  => $this->configuracao->toArray(),
        ];
    }

    private function validate(): void
    {
        $errors = [];

        if (trim($this->id_externo) === '') {
            $errors['id_externo'] = 'Campo obrigatório.';
        }

        if (!in_array($this->tipo_conta, ['liquidacao','pagamento'], true)) {
            $errors['tipo_conta'] = 'Deve ser "liquidacao" ou "pagamento".';
        }

        if ($errors) {
            throw DomainValidationException::fromArray($errors);
        }
    }
}
