<?php

namespace R2Soft\Canvi\Dto;
final class Payer
{
    public function __construct(public string $name)
    {
    }

    public function toArray()
    {
        return ['name' => $this->name];
    }
}