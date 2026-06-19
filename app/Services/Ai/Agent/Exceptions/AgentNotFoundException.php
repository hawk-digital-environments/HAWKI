<?php
declare(strict_types=1);

namespace App\Services\Ai\Agent\Exceptions;

class AgentNotFoundException extends \InvalidArgumentException implements AgentExceptionInterface
{
    public static function forAgentKey(string $agentKey): self
    {
        return new self(sprintf('Agent "%s" not found in registry.', $agentKey));
    }
}
