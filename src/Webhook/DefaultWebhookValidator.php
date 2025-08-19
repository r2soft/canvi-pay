<?php
declare(strict_types=1);

namespace R2Soft\Canvi\Webhook;

final class DefaultWebhookValidator
{
    /** @var string */
    private $signatureHeader;
    
    /** @var string|null */
    private $secret;
    
    /** @var string|null */
    private $publicKey;
    
    /** @var string */
    private $algorithm;
    
    /** @var int */
    private $tolerance;

    public function __construct(
        string $signatureHeader = 'X-Canvi-Signature',
        ?string $secret = null,          // para HMAC
        ?string $publicKey = null,       // para assimétrico (PEM)
        string $algorithm = 'sha256',    // HMAC: sha256 | sha512...
        int $tolerance = 300             // segundos
    ) {
        $this->signatureHeader = $signatureHeader;
        $this->secret = $secret;
        $this->publicKey = $publicKey;
        $this->algorithm = $algorithm;
        $this->tolerance = $tolerance;
    }

    public function isValid(array $server, string $rawBody): bool
    {
        $signatureHeader = $server['HTTP_' . str_replace('-', '_', strtoupper($this->signatureHeader))] ?? null;
        if (!$signatureHeader) return false;

        // Espera-se formato: t=<timestamp>,v1=<assinatura>
        // Ex.: "t=1723651200,v1=abcdef..."
        $parts = [];
        foreach (explode(',', $signatureHeader) as $p) {
            [$k, $v] = array_map('trim', explode('=', $p, 2) + [null, null]);
            if ($k && $v) $parts[$k] = $v;
        }

        $ts = isset($parts['t']) ? (int)$parts['t'] : null;
        // Aceita tanto 'v1' quanto 'sig' como prefixo da assinatura
        $sig = $parts['v1'] ?? $parts['sig'] ?? null;

        if (!$ts || !$sig) return false;
        if (abs(time() - $ts) > $this->tolerance) return false;

        if ($this->secret) {
            $signed = $ts . '.' . $rawBody;
            $calc = hash_hmac($this->algorithm, $signed, $this->secret);
            return hash_equals($calc, $sig);
        }

        if ($this->publicKey) {
            $data = $ts . '.' . $rawBody;
            // A assinatura já está em base64, então não precisamos de hex2bin
            $ok = openssl_verify($data, base64_decode($sig), $this->publicKey, OPENSSL_ALGO_SHA256);
            return $ok === 1;
        }

        return false;
    }


    private function parseSignatureHeader(string $header): array
    {
        $out = [];
        foreach (explode(',', $header) as $pair) {
            [$k, $v] = array_map('trim', explode('=', $pair, 2) + [null, null]);
            if ($k && $v) $out[$k] = $v;
        }
        return $out;
    }

    private function isWithinTolerance(int $timestamp): bool
    {
        $now = time();
        return abs($now - $timestamp) <= $this->tolerance;
    }
}