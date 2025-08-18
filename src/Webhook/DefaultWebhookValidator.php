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
        $headerName = 'HTTP_' . strtoupper(str_replace('-', '_', $this->signatureHeader));
        $sigHeader = $server[$headerName] ?? null;
        if (!$sigHeader) return false;

        // Formato esperado: t=timestamp, v1=<assinatura> (HMAC) ou sig=<base64> (assimétrico)
        $parts = $this->parseSignatureHeader($sigHeader);
        $timestamp = $parts['t'] ?? null;

        if (!$timestamp || !$this->isWithinTolerance((int)$timestamp)) {
            return false;
        }

        if ($this->secret) {
            // Base string inclui timestamp para proteção contra replay
            $base = $timestamp . '.' . $rawBody;
            $calc = hash_hmac($this->algorithm, $base, $this->secret);
            $provided = $parts['v1'] ?? null;
            return $provided && hash_equals($calc, $provided);
        }

        if ($this->publicKey) {
            $signature = $parts['sig'] ?? null;
            if (!$signature) return false;
            $data = $timestamp . '.' . $rawBody;
            $sigRaw = base64_decode($signature, true);
            if ($sigRaw === false) return false;

            $ok = openssl_verify($data, $sigRaw, $this->publicKey, OPENSSL_ALGO_SHA256);
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