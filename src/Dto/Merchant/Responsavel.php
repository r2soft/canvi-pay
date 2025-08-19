<?php
declare(strict_types=1);

Namespace R2Soft\Canvi\Dto\Merchant;

use App\Exception\DomainValidationException;

final class Responsavel
{
    public function __construct(
        public string     $nome,
        public string     $cpf,
        public string     $rg,
        public string     $nacionalidade,
        public string     $celular, // formato internacional brasileiro
        public string     $email,
        public string     $data_nascimento,
        public EnderecoDto $endereco,
    ) {
        $this->validate();
    }

    public static function fromArray(array $a): self
    {
        $end = EnderecoDto::fromArray((array)($a['endereco'] ?? []));
        return new self(
            nome:            (string)($a['nome'] ?? ''),
            cpf:             (string)($a['cpf'] ?? ''),
            rg:              (string)($a['rg'] ?? ''),
            nacionalidade:   (string)($a['nacionalidade'] ?? ''),
            celular:         (string)($a['celular'] ?? ''),
            email:           (string)($a['email'] ?? ''),
            data_nascimento: (string)($a['data_nascimento'] ?? ''),
            endereco:        $end,
        );
    }

    public function toArray(): array
    {
        return [
            'nome'            => $this->nome,
            'cpf'             => $this->cpf,
            'rg'              => $this->rg,
            'nacionalidade'   => $this->nacionalidade,
            'celular'         => $this->celular,
            'email'           => $this->email,
            'data_nascimento' => $this->data_nascimento,
            'endereco'        => $this->endereco->toArray(),
        ];
    }

    private function validate(): void
    {
        $errors = [];

        foreach (['nome','rg','nacionalidade'] as $campo) {
            if (trim((string)$this->{$campo}) === '') {
                $errors["responsavel.$campo"] = 'Campo obrigatório.';
            }
        }

        if (!preg_match('/^\d{11}$/', $this->cpf)) {
            $errors['responsavel.cpf'] = 'CPF deve ter 11 dígitos numéricos.';
        }

        // +55 11 91234-5678 (vamos validar de forma razoável)
        if (!preg_match('/^\+55\s?\d{2}\s?\d{4,5}-?\d{4}$/', $this->celular)) {
            $errors['responsavel.celular'] = 'Número deve seguir o formato brasileiro internacional (+55 DD 9xxxx-xxxx).';
        }

        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $errors['responsavel.email'] = 'E-mail inválido.';
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->data_nascimento)) {
            $errors['responsavel.data_nascimento'] = 'Data fora do formato YYYY-MM-DD.';
        }

        if ($errors) {
            throw DomainValidationException::fromArray($errors);
        }
    }
}
