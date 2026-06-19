<?php
declare(strict_types=1);

namespace App\Services\Ai\Tools\Values;

use App\Services\Ai\Values\ModelCapabilities;

readonly class McpToolDefinition
{
    public function __construct(
        /**
         * The name of the tool on the MCP server.
         */
        public string      $name,
        /**
         * A human-readable description of the tool's purpose and functionality.
         */
        public string|null $description,
        /**
         * This stores the raw tool configuration as defined on the MCP server, which may include details such as parameter names, types, and any additional metadata necessary for invoking the tool correctly.
         * @var array<string, mixed> An associative array defining the tool's input parameters, including their types and any relevant metadata.
         */
        public array       $config,
        /**
         * An optional "capability" string we can use for mapping this tool to a native tool in our system.
         * A good example would be "web_search" {@see ModelCapabilities}
         * @var string|null
         */
        public string|null $capability
    )
    {
    }
}
