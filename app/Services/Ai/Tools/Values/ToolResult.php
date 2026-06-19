<?php
declare(strict_types=1);

namespace App\Services\Ai\Tools\Values;

use App\Utils\DecoratorTrait;
use Mcp\Types\CallToolResult;

/**
 * A tiny adapter layer to the underlying MCP client library's CallToolResult.
 * This is used only to keep our own code decoupled from the MCP client library, and to allow for future extensions if needed.
 */
class ToolResult extends CallToolResult
{
    use DecoratorTrait;
}
