<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Ai\AiModel;
use App\Services\Ai\Models\Capabilities\Values\WellKnownCapabilities;
use App\Services\Ai\Tools\Implementations\TestTool;
use App\Services\Ai\Tools\Values\McpServerType;
use App\Services\Ai\Tools\Values\ToolType;
use App\Services\Ai\Values\OnlineStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Mock MCP servers and AI tools for exercising the assistant builder's tool
 * picker (`ToolSelector.svelte` / `ToolsList.svelte` / `McpServerSelector.svelte`)
 * without needing real MCP servers reachable from the dev environment.
 *
 * Deliberately spreads servers across every {@see OnlineStatus} and tools
 * across every {@see WellKnownCapabilities} key (plus some with no capability
 * mapping, and one using `mapped_capability` to override it) so the picker's
 * grouped-by-server, capability-icon, and active/inactive-toggle states all
 * have something to render.
 *
 * Only `TestTool` is seeded as a `type=function` tool — it's the one real
 * class in `config/tools.php`. Function tools need a class that actually
 * exists (`LaravelToolConverter::convertFunctionTool` resolves it when a tool
 * is invoked), unlike `type=mcp` tools, which fail soft (an unreachable
 * server) rather than fatal.
 *
 * Uses `DB::table()->updateOrInsert()` rather than the Eloquent models —
 * `AiTool` filters to `active=1` through a contextual global scope
 * (`ActiveFilterScope`, see `AiTool::registerScopes()`), which makes
 * `AiTool::updateOrCreate()` unable to find its *own* previously-seeded
 * inactive row on a second run (the lookup is scoped too) and try to
 * re-insert it into a unique column. The query builder isn't scoped, so this
 * stays idempotent for every row regardless of `active`. Mirrors
 * `AssistantSettingSeeder`'s style for the same reason.
 */
class AiToolSeeder extends Seeder
{
    public function run(): void
    {
        $serverIds = $this->seedMcpServers();
        $toolIds = $this->seedTools($serverIds);
        $this->assignToModels($toolIds);
    }

    /** @return array<string, int> server id keyed by a short label for {@see seedTools} to reference. */
    private function seedMcpServers(): array
    {
        $now = now();

        $servers = [
            'rag' => [
                'url' => 'https://rag.mock.hawki.test/mcp',
                'server_label' => 'hawki-rag',
                'description' => 'HAWKI web search and knowledge base tools.',
                'require_approval' => 'never',
                'api_key' => 'mock-rag-api-key',
                'type' => McpServerType::SSE->value,
                'status' => OnlineStatus::ONLINE->value,
                'timeouts' => ['read' => 30, 'connect' => 5],
            ],
            'github' => [
                'url' => 'https://github.mock.hawki.test/mcp',
                'server_label' => 'github-tools',
                'description' => 'Repository search, file, and issue tools.',
                'require_approval' => 'always',
                'api_key' => 'mock-github-api-key',
                'type' => McpServerType::HTTP->value,
                'status' => OnlineStatus::ONLINE->value,
                'timeouts' => ['read' => 15],
            ],
            'files' => [
                'url' => 'stdio://local-files-mock',
                'server_label' => 'local-files',
                'description' => 'Reads and lists files from a local mount.',
                'require_approval' => 'never',
                'api_key' => null,
                'type' => McpServerType::STDIO->value,
                // Offline on purpose: exercises the picker's offline/unreachable state.
                'status' => OnlineStatus::OFFLINE->value,
                'timeouts' => [],
            ],
            'weather' => [
                'url' => 'https://weather.mock.hawki.test/mcp',
                'server_label' => 'weather-service',
                'description' => 'Forecast lookup by location.',
                'require_approval' => 'never',
                'api_key' => 'mock-weather-api-key',
                'type' => McpServerType::HTTP->value,
                // Not pinged yet: exercises the picker's "unknown" state.
                'status' => OnlineStatus::UNKNOWN->value,
                'timeouts' => ['read' => 10],
            ],
        ];

        $ids = [];
        foreach ($servers as $label => $server) {
            DB::table('mcp_servers')->updateOrInsert(
                ['url' => $server['url']],
                [
                    'server_label' => $server['server_label'],
                    'description' => $server['description'],
                    'require_approval' => $server['require_approval'],
                    'api_key' => $server['api_key'] !== null ? Crypt::encryptString($server['api_key']) : null,
                    'type' => $server['type'],
                    'status' => $server['status'],
                    'timeouts' => json_encode($server['timeouts']),
                    'added_by_file' => false,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
            $ids[$label] = DB::table('mcp_servers')->where('url', $server['url'])->value('id');
        }

        return $ids;
    }

    /**
     * @param array<string, int> $serverIds
     * @return list<int> every seeded tool's id
     */
    private function seedTools(array $serverIds): array
    {
        $now = now();

        $definitions = [
            // ── hawki-rag: the two built-in capabilities ────────────────────
            [
                'server' => 'rag',
                'name' => 'hawki-rag-web_search',
                'mcp_name' => 'web_search',
                'description' => 'Searches the web and returns summarized results.',
                'capability' => WellKnownCapabilities::WEB_SEARCH,
            ],
            [
                'server' => 'rag',
                'name' => 'hawki-rag-knowledge_base_query',
                'mcp_name' => 'knowledge_base_query',
                'description' => 'Queries the connected knowledge base for relevant passages.',
                'capability' => WellKnownCapabilities::KNOWLEDGE_BASE,
            ],

            // ── github-tools: mostly uncategorized, one web_fetch, one mapped ──
            [
                'server' => 'github',
                'name' => 'github-tools-search_repositories',
                'mcp_name' => 'search_repositories',
                'description' => 'Searches GitHub repositories by name or topic.',
                'capability' => null,
            ],
            [
                'server' => 'github',
                'name' => 'github-tools-get_file_contents',
                'mcp_name' => 'get_file_contents',
                'description' => 'Fetches the contents of a file at a given path and ref.',
                'capability' => WellKnownCapabilities::WEB_FETCH,
            ],
            [
                'server' => 'github',
                'name' => 'github-tools-create_issue',
                'mcp_name' => 'create_issue',
                'description' => 'Opens an issue on a repository.',
                'capability' => null,
                // Admin has manually mapped this custom tool onto a known capability.
                'mapped_capability' => WellKnownCapabilities::CODE_EXECUTION,
            ],

            // ── local-files: offline server, so these show as unreachable ──
            [
                'server' => 'files',
                'name' => 'local-files-read_file',
                'mcp_name' => 'read_file',
                'description' => 'Reads a file from the local mount.',
                'capability' => null,
            ],
            [
                'server' => 'files',
                'name' => 'local-files-list_directory',
                'mcp_name' => 'list_directory',
                'description' => 'Lists the contents of a directory on the local mount.',
                'capability' => null,
                // One inactive tool to exercise the picker's disabled state.
                'active' => false,
            ],

            // ── weather-service: status-unknown server ──────────────────────
            [
                'server' => 'weather',
                'name' => 'weather-service-get_forecast',
                'mcp_name' => 'get_forecast',
                'description' => 'Returns a short-range forecast for a location.',
                'capability' => null,
            ],
        ];

        $ids = [];
        foreach ($definitions as $def) {
            DB::table('ai_tools')->updateOrInsert(
                ['name' => $def['name']],
                [
                    'type' => ToolType::MCP->value,
                    'class_name' => null,
                    'mcp_server_id' => $serverIds[$def['server']],
                    'mcp_name' => $def['mcp_name'],
                    'mcp_config' => json_encode(['name' => $def['mcp_name']]),
                    'description' => $def['description'],
                    'capability' => $def['capability'],
                    'mapped_capability' => $def['mapped_capability'] ?? null,
                    'active' => $def['active'] ?? true,
                    'added_by_file' => false,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
            $ids[] = DB::table('ai_tools')->where('name', $def['name'])->value('id');
        }

        // The one real function tool (see config/tools.php's `available_tools`).
        DB::table('ai_tools')->updateOrInsert(
            ['name' => 'test_tool'],
            [
                'type' => ToolType::FUNCTION->value,
                'class_name' => TestTool::class,
                'mcp_server_id' => null,
                'mcp_name' => null,
                'mcp_config' => null,
                'description' => 'A test tool for verifying tool calling.',
                'capability' => null,
                'mapped_capability' => null,
                'active' => true,
                'added_by_file' => false,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
        $ids[] = DB::table('ai_tools')->where('name', 'test_tool')->value('id');

        return $ids;
    }

    /**
     * Attaches every seeded tool to a handful of tool-calling-capable models,
     * so `filter[assigned]=1` (what the builder's tool picker actually
     * queries) returns something. Silently does nothing if `models:sync`
     * hasn't populated `ai_models` yet — this seeder doesn't own that data.
     *
     * Eloquent from here on is fine: `AiModel` carries no such scope, and
     * `syncWithoutDetaching` writes the pivot table directly from the ids
     * collected above rather than re-querying `AiTool`.
     *
     * @param list<int> $toolIds
     */
    private function assignToModels(array $toolIds): void
    {
        $models = AiModel::all()->filter(fn (AiModel $model) => $model->settings->canUseTools());

        if ($models->isEmpty()) {
            $this->command->warn(
                'AiToolSeeder: no tool-calling-capable AiModel found (has `models:sync` run yet?) '
                . '— tools were seeded but not assigned to any model.'
            );
            return;
        }

        $typesById = DB::table('ai_tools')->whereIn('id', $toolIds)->pluck('type', 'id');

        foreach ($models->take(3) as $model) {
            $model->tools()->syncWithoutDetaching(
                collect($toolIds)->mapWithKeys(fn (int $id) => [
                    $id => ['type' => $typesById[$id]],
                ])->all()
            );
        }
    }
}
