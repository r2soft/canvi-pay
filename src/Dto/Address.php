<?php
declare(strict_types=1);

namespace R2Soft\Canvi\Dto;
final class Address
{
    public function __construct(
        public string $street,
        public string $number,
        public string $city,
        public string $state,
        public string $zip,
        public ?string $complement = null
    ) {
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}