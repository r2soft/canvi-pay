<?php
declare(strict_types=1);

namespace R2Soft\Canvi\Services;

use R2Soft\Canvi\CanviClient;
use R2Soft\Canvi\Exceptions\ApiException;

final class CanviAuthService
{
    public function __construct(
        private readonly CanviClient $client,
        private readonly string $authPath = '/bt/token',
        /** @var callable|null fn(array $data): ?string */
        private $extractor = null,
    ) {}

    /**
     * Gera um token de acesso na API Canvi e o define no cliente para uso futuro.
     *
     * @param array|null $credentials Se omitido, usa api_key/api_secret do próprio cliente
     * @return string token gerado
     * @throws ApiException
     */
    public function generateToken(?array $credentials = null): string
    {
        $payload = $credentials ?? [
            'client_id' => $this->client->getApiKey(),
            'private_key' => $this->client->getApiSecret(),
        ];

        if (!is_array($payload) || empty($payload['client_id']) || empty($payload['private_key'])) {
            throw new \InvalidArgumentException('Credenciais ausentes: informe client_id e private_key, ou configure no CanviClient.');
        }


        return $this->client->authenticate($this->authPath, $payload, $this->extractor);
    }
}
