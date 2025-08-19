<?php

namespace R2Soft\Canvi\Support;

use GuzzleHttp\Exception\ConnectException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class RetryMiddleware
{
    public static function factory(int $maxRetries = 5, int $baseDelayMs = 200): callable
    {
        return static function (callable $handler) use ($maxRetries, $baseDelayMs) {
            return static function (RequestInterface $request, array $options) use ($handler, $maxRetries, $baseDelayMs) {
                $retries = 0;

                return $handler($request, $options)->then(
                    function (ResponseInterface $response) use ($request, $options, $handler, &$retries, $maxRetries, $baseDelayMs) {
                        if (!self::shouldRetryResponse($response) || $retries >= $maxRetries) {
                            return $response;
                        }
                        $delay = self::computeDelayMs($retries, $baseDelayMs, $response);
                        $retries++;
                        usleep($delay * 1000);
                        return $handler($request, $options);
                    },
                    function ($reason) use ($request, $options, $handler, &$retries, $maxRetries, $baseDelayMs) {
                        $isConnect = $reason instanceof ConnectException;
                        if (!$isConnect || $retries >= $maxRetries) {
                            throw $reason;
                        }
                        $delay = self::computeDelayMs($retries, $baseDelayMs);
                        $retries++;
                        usleep($delay * 1000);
                        return $handler($request, $options);
                    }
                );
            };
        };
    }

    private static function shouldRetryResponse(ResponseInterface $r): bool
    {
        $code = $r->getStatusCode();
        if ($code === 429 || $code === 408) return true;
        if ($code >= 500) return true;
        return false;
    }

    private static function computeDelayMs(int $retries, int $baseMs, ?ResponseInterface $r = null): int
    {
        // Exponential backoff com jitter
        $delay = (int) (pow(2, $retries) * $baseMs);
        if ($r && $r->hasHeader('Retry-After')) {
            $retryAfter = (int) $r->getHeaderLine('Retry-After');
            if ($retryAfter > 0) {
                $delay = max($delay, $retryAfter * 1000);
            }
        }
        return (int) (random_int((int)($delay*0.8), (int)($delay*1.2)));
    }
}
