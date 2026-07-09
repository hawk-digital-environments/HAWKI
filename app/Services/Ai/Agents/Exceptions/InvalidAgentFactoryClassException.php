<?php
declare(strict_types=1);

namespace App\Services\Ai\Agents\Exceptions;

use App\Services\Ai\Agents\Contracts\AgentFactoryInterface;

class InvalidAgentFactoryClassException extends \InvalidArgumentException implements AgentExceptionInterface
{
    public static function forClass(string $class): self
    {
        return new self(sprintf(
            'Agent factory class "%s" must implement %s.',
            $class,
            AgentFactoryInterface::class,
        ));
    }
}
