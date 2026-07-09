<?php
declare(strict_types=1);


namespace App\Services\Ai\Tools\Repositories;

use App\Models\Ai\AiTool;
use App\Models\Ai\McpServer;
use App\Services\Ai\Tools\Contracts\ToolInterface;
use App\Services\Ai\Tools\Values\McpToolDefinition;
use App\Services\Ai\Tools\Values\ToolType;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepositoryWithContextualScopes;
use App\Services\System\Database\Eloquent\Repositories\Value\ScopeOverrides;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Repository for the {@see AiTool} Eloquent model.
 *
 * Provides all database access for AI tools, covering both PHP function-calling tools
 * ({@see ToolType::FUNCTION}) and MCP-backed tools ({@see ToolType::MCP}).
 *
 * Key responsibilities:
 *  - Querying tools by type, with optional contextual-scope overrides.
 *  - Upserting function tools from registered PHP classes ({@see upsertFunction()}).
 *  - Upserting MCP tools discovered from a live server ({@see upsertMcp()}).
 *  - Cleaning up stale MCP tools after a sync ({@see removeAllMcpToolsOf()}).
 *
 * All write operations bypass contextual scopes (e.g. the active-filter scope) so that
 * inactive or admin-only tools are also reachable during sync operations.
 */
class AiToolRepository extends AbstractRepositoryWithContextualScopes
{
    /**
     * Finds all tools, optionally filtered to a single {@see ToolType}.
     * Pass null for `$type` to return all tools regardless of type.
     *
     * @return Collection<int, AiTool>
     */
    public function findAllOfType(
        ToolType|null   $type,
        ?ScopeOverrides $scopeOverrides = null
    ): Collection
    {
        $query = $this->getQuery($scopeOverrides);

        if ($type !== null) {
            $query->where('type', $type->value);
        }

        return $query->get();
    }

    /**
     * Removes all MCP tools associated with the given server, except for those with IDs in the provided list
     */
    public function removeAllMcpToolsOf(
        McpServer  $server,
        array|null $exceptIds = null
    ): void
    {
        $query = $this->getQueryWithoutContextualScopes()
            ->where('type', ToolType::MCP)
            ->where('mcp_server_id', $server->id);

        if (!empty($exceptIds)) {
            $query->whereNotIn('id', $exceptIds);
        }

        $query->delete();
    }

    /**
     * Creates or updates the `ai_tools` row for a PHP function-calling tool.
     *
     * The row is matched by `class_name`, so renaming the class will create a new row rather
     * than updating the existing one — delete the orphaned row manually after a rename.
     *
     * @param bool $addedByFile True when called from a config-file sync or DI-tag sweep
     *                          (as opposed to a manual UI/CLI registration).
     */
    public function upsertFunction(ToolInterface $tool, bool $addedByFile = false): AiTool
    {
        return $this->getQueryWithoutContextualScopes()->updateOrCreate(
            ['class_name' => get_class($tool)],
            [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'capability' => $tool->capability(),
                'type' => ToolType::FUNCTION,
                'active' => true,
                'added_by_file' => $addedByFile
            ]
        );
    }

    /**
     * Creates or updates the `ai_tools` row for a tool discovered on an MCP server.
     *
     * The row is matched by a slug built from the server label, tool name, and server ID.
     * Including the server ID guards against label collisions when two servers share the same
     * label — the slug would still be unique. The `mcp_config` column stores the full raw tool
     * definition so that {@see LaravelMcpTool} can reconstruct the schema without re-querying
     * the MCP server.
     */
    public function upsertMcp(McpToolDefinition $definition, McpServer $server): AiTool
    {
        // "server_label" has no uniqueness requirement, so we include the server ID to be safe.
        $name = Str::slug(sprintf('%s-%s-%s',
            empty($server->server_label) ? 'mcp-server' : $server->server_label,
            $definition->name,
            $server->id
        ));

        return $this->getQueryWithoutContextualScopes()->updateOrCreate(
            ['name' => $name],
            [
                'description' => $definition->description,
                'capability' => $definition->capability,
                'type' => ToolType::MCP,
                'active' => true,
                'mcp_server_id' => $server->id,
                'mcp_name' => $definition->name,
                'mcp_config' => $definition->config,
                'added_by_file' => false
            ]
        );
    }
}
