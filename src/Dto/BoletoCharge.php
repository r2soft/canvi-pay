<?php

namespace R2Soft\Canvi\Dto;
final class BoletoCharge extends Charge
{
    public function __construct(int $amount, public ?string $dueDate = null)
    {
        parent::__construct($amount);
    }

    public function toArray()
    {
        return parent::toArray() + ['dueDate' => $this->dueDate];
    }
}