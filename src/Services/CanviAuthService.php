<?php
declare(strict_types=1);

namespace R2Soft\Canvi\Services;

use R2Soft\Canvi\CanviClient;
use R2Soft\Canvi\Exceptions\ApiException;

final class CanviAuthService
{
    public function __construct(
        private readonly CanviClient $client,
        private readonly string $authPath = '/api/v1/auth/token',
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
            'api_key' => $this->client->getApiKey(),
            'api_secret' => $this->client->getApiSecret(),
        ];

        if (!is_array($payload) || empty($payload['api_key']) || empty($payload['api_secret'])) {
            throw new \InvalidArgumentException('Credenciais ausentes: informe api_key e api_secret, ou configure no CanviClient.');
        }

        return $this->client->authenticate($this->authPath, $payload, $this->extractor);
    }
}
