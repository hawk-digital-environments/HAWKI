<?php
declare(strict_types=1);

namespace App\Services\Ai\Tools\Exceptions;

class ToolNotFoundException extends \RuntimeException implements ToolExceptionInterface
{
    /**
     * Client-facing response for the tool resolution failures below: the
     * frontend translates the code and never shows the raw message.
     */
    public function render(): \Illuminate\Http\JsonResponse
    {
        return response()->json(['message' => 'The selected tool is currently unavailable.', 'code' => 'TOOL_UNAVAILABLE'], 422);
    }

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

    public static function forOfflineServer(string $toolName): self
    {
        return new self(sprintf(
            'The tool "%s" belongs to an MCP server that is currently offline.',
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
