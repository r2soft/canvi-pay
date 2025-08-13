<?php
declare(strict_types=1);

namespace R2Soft\Canvi\Exceptions;

// A classe ApiException foi movida para um arquivo separado

class UnauthorizedException extends ApiException {}
class RateLimitException extends ApiException {}
class ServerException extends ApiException {}

class ValidationException extends ApiException
{
    public function __construct(string $message, int $code = 0, public readonly array $fields = [])
    {
        parent::__construct($message, $code);
    }
}
