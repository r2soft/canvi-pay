<?php
declare(strict_types=1);

Namespace R2Soft\Canvi\Dto\Merchant;

use App\Exception\DomainValidationException;

final class Financeiro
{
    public function __construct(
        public string  $chave_pix,
        public ?int    $horario_retirada = null, // você citou no exemplo, mantive opcional
        public string  $celular_financeiro = '',
        public string  $email_financeiro = '',
    ) {
        $this->validate();
    }

    public static function fromArray(array $a): self
    {
        return new self(
            chave_pix:           (string)($a['chave_pix'] ?? ''),
            horario_retirada:    isset($a['horario_retirada']) ? (int)$a['horario_retirada'] : null,
            celular_financeiro:  (string)($a['celular_financeiro'] ?? ''),
            email_financeiro:    (string)($a['email_financeiro'] ?? ''),
        );
    }

    public function toArray(): array
    {
        return [
            'chave_pix'          => $this->chave_pix,
            'horario_retirada'   => $this->horario_retirada,
            'celular_financeiro' => $this->celular_financeiro,
            'email_financeiro'   => $this->email_financeiro,
        ];
    }

    private function validate(): void
    {
        $errors = [];

        if (trim($this->chave_pix) === '') {
            $errors['financeiro.chave_pix'] = 'Campo obrigatório.';
        }

        if (!preg_match('/^\d{11}$/', $this->celular_financeiro)) {
            $errors['financeiro.celular_financeiro'] = 'Celular deve ter 11 dígitos (DDD+número).';
        }

        if (!filter_var($this->email_financeiro, FILTER_VALIDATE_EMAIL)) {
            $errors['financeiro.email_financeiro'] = 'E-mail inválido.';
        }

        if ($errors) {
            throw DomainValidationException::fromArray($errors);
        }
    }
}
