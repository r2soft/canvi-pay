<?php

declare(strict_types=1);

namespace R2Soft\Canvi\Contracts;

use Psr\Http\Message\ResponseInterface;

interface CanviClientInterface
{
    /**
     * Faz uma requisição para a API Canvi
     *
     * @param string $method Método HTTP (GET, POST, PUT, DELETE, etc.)
     * @param string $endpoint Endpoint da API (ex: /api/v1/boletos)
     * @param array $options Opções da requisição (headers, body, etc.)
     * @return ResponseInterface Resposta da API
     */
    public function request(string $method, string $endpoint, array $options = []): ResponseInterface;

    /**
     * Retorna a URI base da API
     *
     * @return string
     */
    public function getBaseUri(): string;
}
