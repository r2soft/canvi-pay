<?php

namespace R2Soft\Canvi\Support;
final class Signature
{
    public static function hmac(string $algo, string $payload, string $secret): string
    {
        return hash_hmac($algo, $payload, $secret);
    }
}