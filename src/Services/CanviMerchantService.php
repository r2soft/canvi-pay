<?php
declare(strict_types=1);

namespace R2Soft\Canvi\Services;

use Psr\Log\LoggerInterface;
use R2Soft\Canvi\Contracts\CanviClientInterface;
use R2Soft\Canvi\Dto\Merchant\ComercianteCadastro;
use R2Soft\Canvi\Exceptions\ApiException;

final class CanviMerchantService
{
    public function __construct(
        private CanviClientInterface $client,
        private ?LoggerInterface $logger = null
    ) {}

    /**
     * Cria/atualiza (upsert) um Comerciante via PUT /ac/comerciante
     *
     * @param array|ComercianteCadastro $data
     * @param string|null $idempotencyKey
     * @return array
     * @throws ApiException
     */
    public function upsert(array|ComercianteCadastro $data, ?string $idempotencyKey = null): array
    {
        $payload = $data instanceof ComercianteCadastro ? $data->toArray() : $data;

        $this->logger?->debug('Upsert Comerciante (PUT /ac/comerciante)', [
            'payload'          => $this->sanitizePayload($payload),
            'idempotency_key'  => $idempotencyKey,
        ]);
//debug(['/ac/comerciante', $payload, $idempotencyKey]);
        // Se seu CanviClientInterface já tiver ->put(), use-o; senão use ->request('PUT', ...)
        if (method_exists($this->client, 'put')) {
            /** @phpstan-ignore-next-line */
            return $this->client->put('/ac/comerciante', $payload, $idempotencyKey);
        }

        // Fallback genérico
        /** @phpstan-ignore-next-line */
        return $this->client->request('PUT', '/ac/comerciante', $payload, $idempotencyKey);
    }

    /**
     * (Opcional) Busca transações do comerciante.
     * GET /ac/transacoes?data_inicio=...&data_fim=...&tipo_data_pesquisa=0|1&conta=...&numero_cnpj=...
     *
     * @param array{
     *   data_inicio: string,
     *   data_fim: string,
     *   tipo_data_pesquisa?: int,
     *   conta?: string,
     *   numero_cnpj?: string
     * } $filters
     * @return array
     * @throws ApiException
     */
    public function listTransactions(array $filters): array
    {
        $query = $this->buildTransactionQuery($filters);

        $this->logger?->debug('Listando transações (GET /ac/transacoes)', [
            'query' => $query,
        ]);

        return $this->client->get('/ac/transacoes', $query);
    }

    // ======================================================================
    // Helpers
    // ======================================================================

    /**
     * Monta query segura para /ac/transacoes.
     *
     * @param array $filters
     * @return array
     */
    private function buildTransactionQuery(array $filters): array
    {
        $out = [];

        // datas exigem padrão YYYY-MM-DD — aqui não forço regex, só repasso.
        foreach (['data_inicio', 'data_fim'] as $k) {
            if (!empty($filters[$k]) && is_string($filters[$k])) {
                $out[$k] = $filters[$k];
            }
        }

        // 0 = criação | 1 = baixa
        if (isset($filters['tipo_data_pesquisa'])) {
            $v = (int) $filters['tipo_data_pesquisa'];
            $out['tipo_data_pesquisa'] = ($v === 1) ? 1 : 0;
        }

        // conta é opcional, mas se vier, repassamos
        if (!empty($filters['conta']) && is_string($filters['conta'])) {
            $out['conta'] = $filters['conta'];
        }

        // numero_cnpj opcional
        if (!empty($filters['numero_cnpj']) && is_string($filters['numero_cnpj'])) {
            $out['numero_cnpj'] = preg_replace('/\D+/', '', $filters['numero_cnpj']) ?? $filters['numero_cnpj'];
        }

        return $out;
    }

    /**
     * Remove/mascara dados sensíveis do payload para logging.
     * Funciona recursivamente e mascara chaves por nome.
     */
    private function sanitizePayload(array $payload): array
    {
        $maskKeys = [
            'cpf','cnpj','numero_cnpj','chave_pix',
            'nome','nome_comerciante','nome_social',
            'email','email_financeiro',
            'celular','celular_financeiro','telefone',
            'rg',
            // endereço
            'cep','endereco','numero','bairro','complemento',
        ];

        $mask = function (&$value, $key) use (&$mask, $maskKeys) {
            if (is_array($value)) {
                array_walk($value, $mask);
                return;
            }
            if (in_array((string)$key, $maskKeys, true)) {
                $value = '***';
            }
        };

        $sanitized = $payload;
        array_walk($sanitized, $mask);

        return $sanitized;
    }
}
