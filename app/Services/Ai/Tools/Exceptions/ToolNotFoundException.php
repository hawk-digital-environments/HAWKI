<?php
declare(strict_types=1);

namespace App\Services\Ai\Tools\Exceptions;

class ToolNotFoundException extends \RuntimeException implements ToolExceptionInterface
{
    public static function forCapability(string $capabilityKey): self
    {
        return new self(sprintf(
            'No active tool found for capability "%s". Ensure a tool with this capability is active and its MCP server is online.',
            $capabilityKey
        ));
    }

    public static function forName(string $toolName): self
    {
        return new self(sprintf(
            'No tool found with name "%s" on the current model.',
            $toolName
        ));
    }

    public static function forProviderNotSupportingCapability(string $capabilityKey): self
    {
        return new self(sprintf(
            'The current provider does not support a native tool for capability "%s".',
            $capabilityKey
        ));
    }
}
