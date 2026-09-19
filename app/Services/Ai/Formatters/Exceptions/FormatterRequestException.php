<?php

declare(strict_types=1);

namespace App\Services\Ai\Formatters\Exceptions;

/**
 * A request could not be parsed into the IR, or violates a formatter-level constraint
 * (e.g. stateless-proxy restrictions). Rendered by the controller through
 * {@see \App\Services\Ai\Formatters\Contracts\FormatterInterface::formatError()} in the
 * formatter's own error wire format.
 */
abstract class FormatterRequestException extends \RuntimeException implements FormatterExceptionInterface
{
    public function __construct(
        string $message,
        private readonly string $errorCode,
        private readonly int $httpStatus = 400,
        private readonly string $errorType = 'invalid_request_error',
        private readonly ?string $param = null,
    ) {
        parent::__construct($message);
    }

    final public function errorCode(): string
    {
        return $this->errorCode;
    }

    final public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    final public function errorType(): string
    {
        return $this->errorType;
    }

    final public function param(): ?string
    {
        return $this->param;
    }
}
