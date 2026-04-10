<?php
declare(strict_types=1);

namespace R2Soft\Canvi\Services;

use Psr\Log\LoggerInterface;
use R2Soft\Canvi\Contracts\CanviClientInterface;
use R2Soft\Canvi\Exceptions\ApiException;

final class CanviCredentialsService
{
    public function __construct(
        private CanviClientInterface $client,
        private ?LoggerInterface $logger = null
    ) {}

    /**
     * GET /ac/credenciais
     * Retorna SEMPRE uma lista (array indexado) de credenciais.
     * Ex.: [
     *   ['conta'=>..., 'nome_fantasia'=>..., 'numero_cnpj'=>..., 'client_id'=>..., 'private_key'=>..., 'data_expiracao'=>...],
     *   ...
     * ]
     */
    public function get(array $filters): array
    {
        $query = $this->buildQuery($filters);

        $this->logger?->debug('GET /ac/credenciais', ['query' => $this->maskForLog($query)]);

        try {
            $res  = $this->client->get('/ac/credenciais', $query);
            $rows = $res['data'] ?? $res;

            if (!is_array($rows)) {
                throw new ApiException('Resposta inesperada ao consultar credenciais.');
            }

            // Se vier um único item associativo, normaliza para lista
            if ($rows !== [] && !$this->isList($rows)) {
                $rows = [$rows];
            }

            return $rows; // <<<<<< retorna array puro
        } catch (ApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logger?->error('Falha ao obter credenciais: ' . $e->getMessage(), ['exception' => $e]);
            throw new ApiException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Conveniência: retorna só o primeiro item (ou null) como array.
     */
    public function getFirst(array $filters): ?array
    {
        $all = $this->get($filters);
        return $all[0] ?? null;
    }

    // ------------------------------------------------------
    // Helpers
    // ------------------------------------------------------

    private function buildQuery(array $in): array
    {
        $numeroCnpj = isset($in['numero_cnpj']) ? preg_replace('/\D+/', '', (string)$in['numero_cnpj']) : null;
        $numeroCpf  = isset($in['numero_cpf'])  ? preg_replace('/\D+/', '', (string)$in['numero_cpf'])  : null;
        $clientId   = isset($in['client_id'])   ? trim((string)$in['client_id']) : null;
        $conta      = isset($in['conta'])       ? trim((string)$in['conta'])     : null;

        // remove nulos/vazios
        return array_filter([
            'numero_cnpj' => $numeroCnpj,
            'numero_cpf'  => $numeroCpf,
            'client_id'   => $clientId,
            'conta'       => $conta,
        ], static fn($v) => $v !== null && $v !== '');
    }

    private function isList(array $arr): bool
    {
        $i = 0;
        foreach ($arr as $k => $_) {
            if ($k !== $i++) return false;
        }
        return true;
    }

    private function maskForLog(array $q): array
    {
        $out = $q;
        if (isset($out['numero_cnpj'])) {
            $d = preg_replace('/\D+/', '', $out['numero_cnpj']) ?? $out['numero_cnpj'];
            $out['numero_cnpj'] = (strlen($d) <= 4) ? str_repeat('*', strlen($d)) : str_repeat('*', strlen($d) - 4) . substr($d, -4);
        }
        if (isset($out['client_id'])) {
            $s = $out['client_id'];
            $out['client_id'] = (strlen($s) <= 3) ? str_repeat('*', strlen($s)) : str_repeat('*', strlen($s) - 3) . substr($s, -3);
        }
        if (isset($out['conta'])) {
            $s = $out['conta'];
            $out['conta'] = (strlen($s) <= 3) ? str_repeat('*', strlen($s)) : str_repeat('*', strlen($s) - 3) . substr($s, -3);
        }
        return $out;
    }
}
