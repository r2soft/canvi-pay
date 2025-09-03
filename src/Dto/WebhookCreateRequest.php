<?php
declare(strict_types=1);

namespace R2Soft\Canvi\Dto;

final class WebhookCreateRequest
{
    /**
     * Criação aderente ao payload da Canvi (/bt/configurar_webhook)
     */
    public function __construct(
        public string $tipoTransacao, // pixCashin, pixStaticCashin, boletoCashin, pixCashout, cartao
        public string $url,
        public string $type = 'json' // urlencoded | json | form_data
    ) {
    }

    public function toArray(): array
    {
        return array_filter([
            'tipo_transacao' => $this->tipoTransacao,
            'url' => $this->url,
            'type' => $this->type,
        ], fn($v) => $v !== null);
    }
}
