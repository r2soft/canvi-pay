<?php
declare(strict_types=1);

Namespace R2Soft\Canvi\Dto\Merchant;

use App\Exception\DomainValidationException;

final class Geral
{
    public function __construct(
        public string       $tipo_pessoa, // 'pf' | 'pj'
        public string       $numero_cnpj, // CPF(11) ou CNPJ(14) numérico
        public string       $nome_comerciante,
        public string       $nome_social,
        public string       $data_nascimento, // YYYY-MM-DD (nascimento/fundação)
        public string|null  $inscricao_estadual,
        public string|null  $inscricao_municipal,
        public string       $email,
        public FinanceiroDto $financeiro,
        public EnderecoDto   $endereco,
    ) {
        $this->validate();
    }

    public static function fromArray(array $a): self
    {
        $fin = FinanceiroDto::fromArray((array)($a['financeiro'] ?? []));
        $end = EnderecoDto::fromArray((array)($a['endereco'] ?? []));

        return new self(
            tipo_pessoa:         (string)($a['tipo_pessoa'] ?? ''),
            numero_cnpj:         (string)($a['numero_cnpj'] ?? ''),
            nome_comerciante:    (string)($a['nome_comerciante'] ?? ''),
            nome_social:         (string)($a['nome_social'] ?? ''),
            data_nascimento:     (string)($a['data_nascimento'] ?? ''),
            inscricao_estadual:  $a['inscricao_estadual'] ?? null,
            inscricao_municipal: $a['inscricao_municipal'] ?? null,
            email:               (string)($a['email'] ?? ''),
            financeiro:          $fin,
            endereco:            $end,
        );
    }

    public function toArray(): array
    {
        return [
            'tipo_pessoa'         => $this->tipo_pessoa,
            'numero_cnpj'         => $this->numero_cnpj,
            'nome_comerciante'    => $this->nome_comerciante,
            'nome_social'         => $this->nome_social,
            'data_nascimento'     => $this->data_nascimento,
            'inscricao_estadual'  => $this->inscricao_estadual,
            'inscricao_municipal' => $this->inscricao_municipal,
            'email'               => $this->email,
            'financeiro'          => $this->financeiro->toArray(),
            'endereco'            => $this->endereco->toArray(),
        ];
    }

    private function validate(): void
    {
        $errors = [];

        if (!in_array($this->tipo_pessoa, ['pf','pj'], true)) {
            $errors['geral.tipo_pessoa'] = "Deve ser 'pf' ou 'pj'.";
        }

        if (!preg_match('/^\d{11}$|^\d{14}$/', $this->numero_cnpj)) {
            $errors['geral.numero_cnpj'] = 'CPF com 11 dígitos ou CNPJ com 14 dígitos (apenas números).';
        }

        foreach (['nome_comerciante','nome_social'] as $campo) {
            if (trim((string)$this->{$campo}) === '') {
                $errors["geral.$campo"] = 'Campo obrigatório.';
            }
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->data_nascimento)) {
            $errors['geral.data_nascimento'] = 'Data fora do formato YYYY-MM-DD.';
        }

        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $errors['geral.email'] = 'E-mail inválido.';
        }

        if ($errors) {
            throw DomainValidationException::fromArray($errors);
        }
    }
}
