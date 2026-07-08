<?php

namespace App\Services\Ai\Tools\Values;

use App\Services\Ai\Tools\Contracts\ToolInterface;

enum ToolType: string
{
    /**
     * A tool based on a local Laravel class.
     * @see ToolInterface for the implementation and tags.
     */
    case FUNCTION = 'function';

    /**
     * A tool that was found on an MCP server.
     * The tool will be executed by sending a request to the MCP server, which will then execute the tool and return the result.
     */
    case MCP = 'mcp';
}
