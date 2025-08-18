<?php
declare(strict_types=1);

namespace R2Soft\Canvi\Dto;

final class BoletoCreateRequest
{
    /**
     * Criação aderente ao payload da Canvi (/bt/boleto)
     */
    public function __construct(
        public string $valor, // ex.: "79.99"
        public string $tipoTransacao = 'boletoCashin',
        public string $identificadorExterno,
        public string $descricao,
        public array $instrucao = [], // lista de strings
        public bool $aplicarMulta = false,
        public string $tipoMulta = 'percentual',
        public ?string $multa = null, // "1.00"
        public bool $aplicarJuros = false,
        public string $tipoJuros = 'percentual',
        public ?string $juros = null, // "2.00"
        public bool $aplicarDesconto = false,
        public string $tipoDesconto = 'percentual',
        public ?string $desconto = null, // "10.00"
        public ?\DateTimeInterface $dataLimiteDesconto = null, // Y-m-d
        public \DateTimeInterface $vencimento, // Y-m-d
        public string $diasExpirar = '0',
        public array $pagador = [],  // nome, documento, endereco{...}
        public array $cliente = []  // nome, tipo_documento, numero_documento, e-mail
    ) {
    }

    public function toArray(): array
    {
        return array_filter([
            'valor' => $this->valor,
            'tipo_transacao' => $this->tipoTransacao,
            'identificador_externo' => $this->identificadorExterno,
            'descricao' => $this->descricao,
            'instrucao' => array_values($this->instrucao),
            'aplicar_multa' => $this->aplicarMulta,
            'tipo_multa' => $this->tipoMulta,
            'multa' => $this->multa,
            'aplicar_juros' => $this->aplicarJuros,
            'tipo_juros' => $this->tipoJuros,
            'juros' => $this->juros,
            'aplicar_desconto' => $this->aplicarDesconto,
            'tipo_desconto' => $this->tipoDesconto,
            'desconto' => $this->desconto,
            'data_limite_desconto' => $this->dataLimiteDesconto?->format('Y-m-d'),
            'vencimento' => $this->vencimento->format('Y-m-d'),
            'dias_expirar' => (string) $this->diasExpirar,
            'pagador' => $this->pagador,
            'cliente' => $this->cliente,
        ], fn($v) => $v !== null);
    }
}




