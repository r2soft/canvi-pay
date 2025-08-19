<?php
declare(strict_types=1);

namespace R2Soft\Canvi\Exceptions;

final class DomainValidationException extends \RuntimeException
{
    /** @var array<string,string> */
    private array $errors;

    /**
     * @param array<string,string> $errors
     */
    private function __construct(array $errors, string $message = 'Domain validation error.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    /**
     * @param array<string,string> $errors
     */
    public static function fromArray(array $errors, string $message = 'Domain validation error.'): self
    {
        return new self($errors, $message);
    }

    /**
     * @return array<string,string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
