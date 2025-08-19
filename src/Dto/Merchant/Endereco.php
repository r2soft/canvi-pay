<?php
declare(strict_types=1);

Namespace R2Soft\Canvi\Dto\Merchant;

use App\Exception\DomainValidationException;

final class Endereco
{
    public function __construct(
        public string  $cep,
        public string  $estado,
        public string  $cidade,
        public string  $endereco,
        public string  $numero,
        public string  $bairro,
        public ?string $complemento = null,
    )
    {
        $this->validate();
    }

    public static function fromArray(array $a): self
    {
        return new self(
            cep: (string) ($a['cep'] ?? ''),
            estado: (string) ($a['estado'] ?? ''),
            cidade: (string) ($a['cidade'] ?? ''),
            endereco: (string) ($a['endereco'] ?? ''),
            numero: (string) ($a['numero'] ?? ''),
            bairro: (string) ($a['bairro'] ?? ''),
            complemento: $a['complemento'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'cep' => $this->cep,
            'estado' => $this->estado,
            'cidade' => $this->cidade,
            'endereco' => $this->endereco,
            'numero' => $this->numero,
            'bairro' => $this->bairro,
            'complemento' => $this->complemento,
        ];
    }

    private function validate(): void
    {
        $errors = [];

        if (!preg_match('/^\d{8}$/', $this->cep)) {
            $errors['endereco.cep'] = 'CEP deve ter 8 dígitos (00000000).';
        }
        if (!preg_match('/^[A-Z]{2}$/', $this->estado)) {
            $errors['endereco.estado'] = 'Estado deve ser a UF com 2 letras maiúsculas (ex.: SP).';
        }
        foreach (['cidade', 'endereco', 'numero', 'bairro'] as $campo) {
            if (trim((string) $this->{$campo}) === '') {
                $errors["endereco.$campo"] = 'Campo obrigatório.';
            }
        }

        if ($errors) {
            throw DomainValidationException::fromArray($errors);
        }
    }
}
