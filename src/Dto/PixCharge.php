<?php

namespace R2Soft\Canvi\Dto;
final class PixCharge extends Charge
{
    public function __construct(int $amount, public ?int $expiresIn = null)
    {
        parent::__construct($amount);
    }

    public function toArray()
    {
        return parent::toArray() + ['expiresIn' => $this->expiresIn];
    }
}