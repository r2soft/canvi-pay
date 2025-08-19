<?php

declare(strict_types=1);

namespace R2Soft\Canvi\Contracts;

/**
 * Interface para serviços de gerenciamento de boletos
 */
interface BoletoService
{
    /**
     * Cria um novo boleto
     * 
     * @param array $payload Dados do boleto
     * @param string|null $idempotencyKey Chave de idempotência (opcional)
     * @return array Dados do boleto criado
     */
    public function create(array $payload, ?string $idempotencyKey = null): array;

    /**
     * Obtém os dados de um boleto pelo ID
     * 
     * @param string $chargeId ID do boleto
     * @return array Dados do boleto
     */
    public function get(string $chargeId): array;

    /**
     * Cancela um boleto
     * 
     * @param string $chargeId ID do boleto a ser cancelado
     * @return array Resultado do cancelamento
     */
    public function cancel(string $chargeId): array;

    /**
     * Lista boletos de acordo com os filtros fornecidos
     * 
     * @param array $filters Filtros para a consulta
     * @return array Lista de boletos
     */
    public function list(array $filters = []): array;
}