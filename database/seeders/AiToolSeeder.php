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
 * The `rag` entry adapts to the instance: without a configured
 * HAWKI-RAG system (see {@see realRagConfigured()}) the mock server is
 * seeded to demo the tool picker; with one configured, the seeder writes
 * the live `hawki-rag` server from `tools.mcp_servers` (url, api key,
 * timeouts; status is left at its default because the seeder never
 * pings) plus its two built-in tools, so a freshly seeded instance can
 * use the RAG tools without running `ai:config:sync` / `ai:tools:sync`
 * first. Later syncs stay idempotent: the server row and tool names
 * match what discovery writes.
 *
 * Uses `DB::table()->updateOrInsert()` rather than the Eloquent models —
 * `AiTool` filters to `active=1` through a contextual global scope
 * (`ActiveFilterScope`, see `AiTool::registerScopes()`), which makes
 * `AiTool::updateOrCreate()` unable to find its *own previously-seeded*
 * inactive row on a second run (the lookup is scoped too) and try to
 * re-insert it into a unique column. The query builder isn't scoped, so this
 * stays idempotent for every row regardless of `active`. Mirrors
 * `AssistantSettingSeeder`'s style for the same reason.
 */
class AiToolSeeder extends Seeder
{
    /**
     * The built-in default `tools.mcp_servers.hawki-rag.url` (mirrored
     * from config/tools.php) — an instance still on this stock URL is
     * treated as "no real HAWKI-RAG configured".
     */
    private const string DEFAULT_RAG_MCP_URL = 'http://localhost:8080/mcp/rawki';

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
                'added_by_file' => false,
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
                'added_by_file' => false,
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
                'added_by_file' => false,
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
                'added_by_file' => false,
            ],
        ];

        if ($this->realRagConfigured()) {
            $realServer = $this->realRagServerConfig();

            if (null !== $realServer) {
                // Seed the live configuration instead of the mock.
                $servers['rag'] = $realServer;
            } else {
                // Real system configured but no config entry to derive
                // values from — skip the rag entries; the live server and
                // tools then come from `ai:config:sync` / `ai:tools:sync`.
                unset($servers['rag']);
            }
        }

        $ids = [];
        foreach ($servers as $label => $server) {
            $row = [
                'server_label' => $server['server_label'],
                'description' => $server['description'],
                'require_approval' => $server['require_approval'],
                'api_key' => $server['api_key'] !== null ? Crypt::encryptString($server['api_key']) : null,
                'type' => $server['type'],
                'timeouts' => json_encode($server['timeouts']),
                'added_by_file' => $server['added_by_file'],
                'updated_at' => $now,
                'created_at' => $now,
            ];

            // Only the mocks assert a status; the real rag entry never
            // pings, so the column default (unknown) applies and a live
            // row's already-known status survives a re-seed.
            if (isset($server['status'])) {
                $row['status'] = $server['status'];
            }

            DB::table('mcp_servers')->updateOrInsert(['url' => $server['url']], $row);
            $ids[$label] = DB::table('mcp_servers')->where('url', $server['url'])->value('id');
        }

        return $ids;
    }

    /**
     * Whether a real HAWKI-RAG system is configured: either the MCP
     * server URL in `tools.mcp_servers` is set to something other than
     * the stock default (what `ai:config:sync` would register), or a
     * live `hawki-rag` server (anything but this seeder's own mock URL)
     * is already registered.
     */
    private function realRagConfigured(): bool
    {
        $config = config('tools.mcp_servers.hawki-rag');

        $configuredUrl = \is_array($config) ? ($config['url'] ?? null) : null;

        if (\is_string($configuredUrl) && '' !== $configuredUrl && $configuredUrl !== self::DEFAULT_RAG_MCP_URL) {
            return true;
        }

        return DB::table('mcp_servers')
            ->where('server_label', 'hawki-rag')
            ->where('url', '!=', 'https://rag.mock.hawki.test/mcp')
            ->exists();
    }

    /**
     * The real `hawki-rag` MCP server definition from `tools.mcp_servers`,
     * translated into this seeder's row shape (field mapping mirrors
     * {@see \App\Services\Ai\ConfigFileSync\Syncers\McpServerSyncer}: the
     * type defaults to SSE). Returns null when the config entry carries no
     * URL. The row is marked `added_by_file` — it is owned by the config
     * file the same way an `ai:config:sync` row is.
     *
     * @return array<string, mixed>|null
     */
    private function realRagServerConfig(): ?array
    {
        $config = config('tools.mcp_servers.hawki-rag');

        if (!\is_array($config) || empty($config['url']) || !\is_string($config['url'])) {
            return null;
        }

        return [
            'url' => $config['url'],
            'server_label' => $config['server_label'] ?? 'hawki-rag',
            'description' => $config['description'] ?? 'HAWKI web search and knowledge base tools.',
            'require_approval' => $config['require_approval'] ?? 'never',
            'api_key' => $config['api_key'] ?? null,
            'type' => (empty($config['type']) ? McpServerType::SSE : McpServerType::from($config['type']))->value,
            'timeouts' => array_filter([
                'read' => $config['read_timeout'] ?? null,
                'connect' => $config['connection_timeout'] ?? null,
                'sse_idle' => $config['sse_idle_timeout'] ?? null,
            ], static fn (int|float|null $value): bool => null !== $value),
            'added_by_file' => true,
        ];
    }

    /**
     * @param array<string, int> $serverIds
     * @return array{rag: list<int>, other: list<int>} ids of the seeded tools, split so {@see assignToModels()} can treat the hawki-rag tools specially
     */
    private function seedTools(array $serverIds): array
    {
        $now = now();

        $definitions = [
            // ── hawki-rag: the two built-in capabilities ────────────────────
            // Seeded against whichever server `seedMcpServers()` registered
            // under 'rag' — the mock for the picker demo, or the real
            // configured one (names match `ai:tools:sync` discovery, so
            // both stay idempotent).
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

        $ids = ['rag' => [], 'other' => []];
        foreach ($definitions as $def) {
            // Definitions whose server was skipped (rag without a usable
            // config entry) have nothing to attach to — skip them too.
            if (!isset($serverIds[$def['server']])) {
                continue;
            }

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
            $ids['rag' === $def['server'] ? 'rag' : 'other'][] = DB::table('ai_tools')->where('name', $def['name'])->value('id');
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
        $ids['other'][] = DB::table('ai_tools')->where('name', 'test_tool')->value('id');

        return $ids;
    }

    /**
     * Attaches the seeded tools to the tool-calling-capable models, so
     * `filter[assigned]=1` (what the builder's tool picker actually
     * queries) returns something. The hawki-rag tools go to every
     * tool-calling model — knowledge base and web search are core
     * capabilities, not demo entries — while the remaining mock tools
     * keep their "handful of models" spread. Silently does nothing if
     * `models:sync` hasn't populated `ai_models` yet — this seeder
     * doesn't own that data.
     *
     * Eloquent from here on is fine: `AiModel` carries no such scope, and
     * `syncWithoutDetaching` writes the pivot table directly from the ids
     * collected above rather than re-querying `AiTool`.
     *
     * @param array{rag: list<int>, other: list<int>} $toolIds
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

        $typesById = DB::table('ai_tools')
            ->whereIn('id', [...$toolIds['rag'], ...$toolIds['other']])
            ->pluck('type', 'id');

        $attach = static function (AiModel $model, array $ids) use ($typesById): void {
            if ([] === $ids) {
                return;
            }

            $model->tools()->syncWithoutDetaching(
                collect($ids)->mapWithKeys(fn (int $id) => [
                    $id => ['type' => $typesById[$id]],
                ])->all()
            );
        };

        foreach ($models as $model) {
            $attach($model, $toolIds['rag']);
        }

        foreach ($models->take(3) as $model) {
            $attach($model, $toolIds['other']);
        }
    }
}
