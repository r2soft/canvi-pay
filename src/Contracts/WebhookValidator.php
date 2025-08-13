<?php

namespace R2Soft\Canvi\Contracts;
interface WebhookValidator
{
    public function isValid(string $raw, ?string $sig): bool;
}