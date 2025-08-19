<?php

declare(strict_types=1);

namespace R2Soft\Canvi\Exceptions;

use R2Soft\Canvi\Exceptions\ApiException;

class ValidationException extends ApiException
{
    /**
     * @var array Array de erros de validação
     */
    private array $errors;

    /**
     * Construtor da exceção de validação
     *
     * @param string $message Mensagem de erro
     * @param array $errors Array de erros de validação
     * @param int $code Código de status HTTP
     * @param string|null $responseBody Corpo da resposta HTTP
     * @param \Throwable|null $previous Exceção anterior
     */
    public function __construct(
        string $message = "Erro de validação",
        array $errors = [],
        int $code = 422,
        ?string $responseBody = null,
        ?\Throwable $previous = null
    ) {
        $this->errors = $errors;
        // A ordem dos parâmetros no construtor da ApiException é:
        // 1. string $message
        // 2. int $code
        // 3. ?\Throwable $previous
        // 4. ?int $httpStatus
        // 5. ?string $rawBody
        parent::__construct(
            $message,
            $code,
            $previous,      // previous
            $code,          // httpStatus (usando o mesmo código)
            $responseBody   // rawBody
        );
    }

    /**
     * Obtém os erros de validação
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    /**
     * Define o código de erro da exceção
     *
     * @param mixed $code Código de erro
     * @return self
     */
    public function setCode($code): self
    {
        $this->code = $code;
        return $this;
    }
}
