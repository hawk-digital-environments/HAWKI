<?php
declare(strict_types=1);

namespace App\Services\Ai\Agents\Exceptions;

class AgentStateException extends \RuntimeException implements AgentExceptionInterface
{
    public static function forUsageNotAvailable(): self
    {
        return new self('Token usage is not available. Call send() or sendStreaming() before reading usage.');
    }
}
