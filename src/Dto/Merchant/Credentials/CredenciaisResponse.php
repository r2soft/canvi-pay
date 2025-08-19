<?php
declare(strict_types=1);

namespace R2Soft\Canvi\Dto\Merchant\Credentials;

use JsonSerializable;

/**
 * Representa a resposta do GET /ac/credenciais (lista de credenciais).
 */
final class CredenciaisResponse implements JsonSerializable
{
    /** @var Credencial[] */
    public array $items;

    public function __construct(Credencial ...$items)
    {
        $this->items = $items;
    }

    /**
     * Aceita os dois formatos comuns:
     *  - [ {conta,...}, {conta,...} ]
     *  - ['data' => [ {conta,...} ]]
     */
    public static function fromApi(array $response): self
    {
        $rows = $response['data'] ?? $response;

        // se vier um único item associativo, normaliza para lista
        if (is_array($rows) && $rows !== [] && array_is_list($rows) === false) {
            $rows = [$rows];
        }

        $items = [];
        foreach ((array)$rows as $row) {
            if (is_array($row)) {
                $items[] = Credencial::fromArray($row);
            }
        }

        return new self(...$items);
    }

    /** Primeiro item ou null (conveniência) */
    public function first(): ?Credencial
    {
        return $this->items[0] ?? null;
    }

    public function jsonSerialize(): array
    {
        return array_map(static fn(Credencial $c) => $c->toArray(), $this->items);
    }
}
