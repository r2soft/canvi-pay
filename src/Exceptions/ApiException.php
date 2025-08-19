<?php
declare(strict_types=1);

namespace R2Soft\Canvi\Exceptions;

class ApiException extends \RuntimeException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        protected ?int $httpStatus = null,
        protected ?string $rawBody = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
