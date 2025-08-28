<?php
declare(strict_types=1);

namespace R2Soft\Canvi\Services;

use R2Soft\Canvi\Contracts\CanviClientInterface;
use R2Soft\Canvi\Exceptions\ApiException;
use Psr\Log\LoggerInterface;

class CanviWebhookService
{
    private CanviClientInterface $client;
    private ?LoggerInterface $logger;

    public function __construct(CanviClientInterface $client, ?LoggerInterface $logger = null)
    {
        $this->client = $client;
        $this->logger = $logger;
    }

    /**
     * Configura um novo webhook
     *
     * @param array $payload Dados do webhook
     * @param string|null $idempotencyKey Chave de idempotência (opcional)
     * @return array Resposta da API
     * @throws ApiException
     */
    public function create(array $payload): array
    {
        $this->logger?->debug('Configurando webhook', [
            'payload' => $payload
        ]);

        return $this->client->post('/bt/configurar_webhook', $payload);
    }
}
