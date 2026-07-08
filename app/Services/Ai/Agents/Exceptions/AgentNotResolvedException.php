<?php
declare(strict_types=1);

namespace App\Services\Ai\Agents\Exceptions;

class AgentNotResolvedException extends \RuntimeException implements AgentExceptionInterface
{
    public static function forRequestType(string $requestType): self
    {
        return new self(sprintf(
            'No agent factory could create an agent for request of type "%s".',
            $requestType,
        ));
    }
}
