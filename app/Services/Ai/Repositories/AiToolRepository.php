<?php
declare(strict_types=1);


namespace App\Services\Ai\Repositories;

use App\Models\Ai\AiTool;
use App\Models\Ai\McpServer;
use App\Services\Ai\Contracts\ToolInterface;
use App\Services\Ai\Tools\Values\McpToolDefinition;
use App\Services\Ai\Values\ToolType;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepositoryWithContextualScopes;
use App\Services\System\Database\Eloquent\Repositories\Value\ScopeOverrides;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AiToolRepository extends AbstractRepositoryWithContextualScopes
{
    /**
     * Finds all tools in the database, with an optional filter
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
     * @param ToolInterface $tool
     * @param bool $addedByFile Whether this tool was added via a config file sync or service tag (as opposed to manually through the UI or CLI).
     * @return AiTool
     */
    public function upsertFunction(ToolInterface $tool, bool $addedByFile = false): AiTool
    {
        return $this->getQueryWithoutContextualScopes()->updateOrCreate(
            ['class_name' => get_class($tool)],
            [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'capability' => $tool->capability(),
                'type' => ToolType::FUNCTION,
                'active' => true,
                'added_by_file' => $addedByFile
            ]
        );
    }

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
