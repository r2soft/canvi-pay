<?php
declare(strict_types=1);

namespace R2Soft\Canvi\Services;

use R2Soft\Canvi\Contracts\BoletoService as BoletoServiceInterface;
use R2Soft\Canvi\Contracts\CanviClientInterface;
use R2Soft\Canvi\Exceptions\ApiException;
use Psr\Log\LoggerInterface;

class CanviBoletoService implements BoletoServiceInterface
{
    private CanviClientInterface $client;
    private ?LoggerInterface $logger;

    /**
     * @param CanviClientInterface $client Cliente HTTP para fazer requisições
     * @param LoggerInterface|null $logger Instância do logger (opcional)
     */
    public function __construct(CanviClientInterface $client, ?LoggerInterface $logger = null)
    {
        $this->client = $client;
        $this->logger = $logger;
    }

    /**
     * Cria um novo boleto
     *
     * @param array $payload Dados do boleto
     * @param string|null $idempotencyKey Chave de idempotência (opcional)
     * @return array Dados do boleto criado
     * @throws ApiException
     */
    public function create(array $payload, ?string $idempotencyKey = null): array
    {
        $this->logger?->debug('Criando novo boleto', [
            'payload' => $this->sanitizePayload($payload),
            'idempotency_key' => $idempotencyKey,
        ]);

        return $this->client->post('/bt/boleto', $payload, $idempotencyKey);
    }

    /**
     * Obtém os dados de um boleto pelo ID
     *
     * @param string $chargeId ID do boleto
     * @return array Dados do boleto
     * @throws ApiException
     */
    public function get(string $chargeId): array
    {
        $this->logger?->debug('Consultando boleto', ['identificador_externo' => $chargeId]);

        return $this->client->post("/bt/consulta_boleto", ['identificador_externo' => $chargeId]);
    }

    /**
     * Cancela um boleto
     *
     * @param string $chargeId ID do boleto a ser cancelado
     * @return array Resultado do cancelamento
     * @throws ApiException
     */
    public function cancel(string $chargeId): array
    {
        $this->logger?->debug('Cancelando boleto', ['charge_id' => $chargeId]);

        return $this->client->post("/bt/cancelar_boleto", ['id_boleto' => $chargeId]);
    }

    /**
     * Lista boletos de acordo com os filtros fornecidos
     *
     * @param array $filters Filtros para a consulta
     * @return array Lista de boletos
     * @throws ApiException
     */
    public function list(array $filters = []): array
    {
        $this->logger?->debug('Listando boletos', ['filters' => $filters]);

        return $this->client->get('/bt/consulta_boletos', $filters);
    }

    /**
     * Remove dados sensíveis do payload para logging
     *
     * @param array $payload Dados originais
     * @return array Dados com informações sensíveis removidas
     */
    private function sanitizePayload(array $payload): array
    {
        $sensitiveFields = ['cpf', 'cnpj', 'nome', 'email', 'telefone', 'endereco'];
        $sanitized = $payload;

        foreach ($sensitiveFields as $field) {
            if (isset($sanitized[$field])) {
                $sanitized[$field] = '***';
            }
        }

        return $sanitized;
    }
}
