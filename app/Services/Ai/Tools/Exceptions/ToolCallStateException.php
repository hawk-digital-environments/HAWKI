<?php
declare(strict_types=1);

namespace App\Services\Ai\Tools\Exceptions;

class ToolCallStateException extends \RuntimeException implements ToolExceptionInterface
{
    public static function forRequestNotSet(): self
    {
        return new self('Request is not set. This method can only be called during the execution of the tool.');
    }

    public static function forArgumentsNotSet(): self
    {
        return new self('Arguments are not set. This method can only be called during the execution of the tool.');
    }
}
