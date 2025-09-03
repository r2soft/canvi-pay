<?php
declare(strict_types=1);

namespace R2Soft\Canvi\Services;

use Psr\Log\LoggerInterface;
use R2Soft\Canvi\Contracts\CanviClientInterface;
use R2Soft\Canvi\Dto\Merchant\Tariff\TariffPutRequest;
use R2Soft\Canvi\Exceptions\ApiException;

final class CanviTariffService
{
    public function __construct(
        private CanviClientInterface $client,
        private ?LoggerInterface $logger = null
    ) {}

    /**
     * PUT /ac/tarifa
     */
    public function put(TariffPutRequest $request): array
    {
        $query = $request->toQuery();
        $body  = $request->toBody();

        $this->logger?->debug('PUT /ac/tarifa', [
            'query' => $this->maskForLog($query),
            'body'  => $body,
        ]);

        try {
            return $this->client->put('/ac/tarifa?conta=' . $query['conta'], $body);
        } catch (ApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logger?->error('Failed to update tariff: ' . $e->getMessage(), ['exception' => $e]);
            throw new ApiException($e->getMessage(), $e->getCode(), $e);
        }
    }

    private function maskForLog(array $q): array
    {
        $out = $q;
        if (isset($out['numero_cnpj'])) {
            $d = preg_replace('/\D+/', '', $out['numero_cnpj']) ?? $out['numero_cnpj'];
            $out['numero_cnpj'] = (strlen($d) <= 4)
                ? str_repeat('*', strlen($d))
                : str_repeat('*', strlen($d) - 4) . substr($d, -4);
        }
        if (isset($out['client_id'])) {
            $s = $out['client_id'];
            $out['client_id'] = (strlen($s) <= 3)
                ? str_repeat('*', strlen($s))
                : str_repeat('*', strlen($s) - 3) . substr($s, -3);
        }
        if (isset($out['conta'])) {
            $s = $out['conta'];
            $out['conta'] = (strlen($s) <= 3)
                ? str_repeat('*', strlen($s))
                : str_repeat('*', strlen($s) - 3) . substr($s, -3);
        }
        return $out;
    }
}
