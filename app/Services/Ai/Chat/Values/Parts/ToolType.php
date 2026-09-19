<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Parts;

/**
 * Category of a tool referenced by a {@see ToolCallPart} or tool definition.
 *
 * The two-layer type system (category + concrete name) avoids a type explosion: provider
 * tool families (web search, code interpreter, MCP servers, …) all ride the same
 * discriminator with their concrete identity carried by the tool name.
 */
enum ToolType: string
{
    case FUNCTION = 'function';
    case MCP = 'mcp';
    case CUSTOM = 'custom';
}
