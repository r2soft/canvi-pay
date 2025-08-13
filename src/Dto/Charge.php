<?php
namespace R2Soft\Canvi\Dto;

final class Charge implements \JsonSerializable
{
    public function __construct(
        public readonly int $amount,               // em centavos
        public readonly ?string $description = null
    ) {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('amount deve ser > 0');
        }
    }

    public function jsonSerialize(): array
    {
        return [
            'amount' => $this->amount,
            'description' => $this->description,
        ];
    }

    public function toArray(): array
    {
        return $this->jsonSerialize();
    }

    public static function fromArray(array $data): self
    {
        return new self(
            amount: (int)$data['amount'],
            description: $data['description'] ?? null
        );
    }
}