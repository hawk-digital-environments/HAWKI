<?php
declare(strict_types=1);

namespace App\Services\Ai\Tools\Exceptions;

use App\Services\Ai\Tools\Contracts\ToolInterface;

class InvalidToolConfigurationException extends \RuntimeException implements ToolExceptionInterface
{
    public static function forUnsupportedToolType(string $toolType, string $toolName): self
    {
        return new self(sprintf('Unsupported tool type "%s" for tool "%s".', $toolType, $toolName));
    }

    public static function forClassNotFound(string $toolClass, string $toolName): self
    {
        return new self(sprintf(
            'Tool class "%s" does not exist for tool "%s".',
            $toolClass,
            $toolName
        ));
    }

    public static function forClassNotImplementingInterface(string $toolClass, string $toolName): self
    {
        return new self(sprintf(
            'Tool class "%s" must implement %s for tool "%s".',
            $toolClass,
            ToolInterface::class,
            $toolName
        ));
    }

    public static function forMcpToolNotLinkedToServer(string $toolName): self
    {
        return new self(sprintf(
            'MCP tool "%s" is not linked to an MCP server.',
            $toolName
        ));
    }

    public static function forMcpToolMissingConfig(string $toolName): self
    {
        return new self(sprintf(
            'MCP tool "%s" does not have a config. Did you run the tool sync?',
            $toolName
        ));
    }
}
