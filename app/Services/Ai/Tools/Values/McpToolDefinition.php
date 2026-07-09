<?php
declare(strict_types=1);

namespace App\Services\Ai\Tools\Values;

use App\Services\Ai\Models\Capabilities\Values\WellKnownCapabilities;

/**
 * Immutable value object representing a single tool advertised by an MCP server.
 *
 * Produced by {@see HawkiMcpClient::listToolDefinitions()} during a tool sync and consumed
 * by {@see AiToolRepository::upsertMcp()} to persist the tool into the database. The `config`
 * array is the full raw MCP tool definition (JSON-decoded), stored verbatim in `mcp_config`
 * so that {@see LaravelMcpTool} can reconstruct the input schema later without a live server
 * round-trip.
 *
 * The `capability` field is a HAWKI extension — MCP servers can optionally set a
 * `hawkiCapability` property on their tool definitions to declare which well-known HAWKI
 * capability they fulfil (e.g. `"web_search"` from {@see WellKnownCapabilities}).
 */
readonly class McpToolDefinition
{
    public function __construct(
        /**
         * The name of the tool as declared on the MCP server (used as `mcp_name` in the DB).
         */
        public string      $name,
        /**
         * A human-readable description of the tool's purpose and functionality.
         */
        public string|null $description,
        /**
         * The full raw tool definition from the MCP server, including `inputSchema` and any
         * other metadata. Stored verbatim in `mcp_config` and used to rebuild the tool schema.
         *
         * @var array<string, mixed>
         */
        public array       $config,
        /**
         * Optional HAWKI capability key declared by the MCP server (via `hawkiCapability`).
         * When set, allows the tool to be resolved via {@see LaravelToolResolver::resolveToolForCapability()}.
         *
         * @see WellKnownCapabilities for the set of recognised values.
         */
        public string|null $capability
    )
    {
    }
}
