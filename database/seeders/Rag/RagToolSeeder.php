<?php

declare(strict_types=1);

namespace Database\Seeders\Rag;

use App\Models\Ai\AiModel;
use App\Services\Ai\Models\Capabilities\Values\WellKnownCapabilities;
use App\Services\Ai\Tools\Values\McpServerType;
use App\Services\Ai\Tools\Values\ToolType;
use App\Services\Rag\Config\RagConfig;
use App\Services\Ai\Values\OnlineStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the RAG module's contributions: the `hawki-rag` MCP server row and
 * its two built-in tools. This is the RAG slice's own seeder (not part of
 * {@see \Database\Seeders\AiToolSeeder}) so the module ships its database
 * rows the same way it ships its registry declarations — the pattern a
 * future plugin package follows.
 *
 * The entry adapts to the instance: without a configured HAWKI-RAG system
 * (see {@see realRagConfigured()}) the mock server is seeded to demo the
 * tool picker; with one configured, the live `hawki-rag` server is written
 * from `tools.mcp_servers` (url, api key, timeouts; status is left at its
 * default because the seeder never pings) plus its two built-in tools, so
 * a freshly seeded instance can use the RAG tools without running
 * `ai:config:sync` / `ai:tools:sync` first. Later syncs stay idempotent:
 * the server row and tool names match what discovery writes.
 *
 * Tool identities (ai_tools.name) come from the RAG config
 * (`rag.retrieval.query_tool` / `web_search_tool`) — the same single
 * source the agent tool and the public config read. The `mcp_name` values
 * mirror the live HAWKI-RAG server's tools (`App\Mcp\Tools\*` in
 * HAWKI-RAG); keep them in sync when the server renames tools. Running
 * `ai:tools:sync --mcp-only` replaces these rows with its own
 * server-id-suffixed slugs (and drops their model assignments).
 *
 * Uses `DB::table()->updateOrInsert()` rather than the Eloquent models —
 * `AiTool` filters to `active=1` through a contextual global scope, which
 * makes `AiTool::updateOrCreate()` unable to find its own previously
 * seeded inactive row on a second run. Mirrors
 * {@see \Database\Seeders\AiToolSeeder}'s style.
 */
class RagToolSeeder extends Seeder
{
    /**
     * The built-in default `tools.mcp_servers.hawki-rag.url` (mirrored
     * from config/tools.php) — an instance still on this stock URL is
     * treated as "no real HAWKI-RAG configured".
     */
    private const string DEFAULT_RAG_MCP_URL = 'http://localhost:8080/mcp/rawki';

    public function run(): void
    {
        $serverId = $this->seedMcpServer();

        if (null === $serverId) {
            // Real system configured but no config entry to derive values
            // from — the live server and tools then come from
            // `ai:config:sync` / `ai:tools:sync`.
            return;
        }

        $toolIds = $this->seedTools($serverId);
        $this->assignToModels($toolIds);
    }

    /**
     * @return int|null the mcp_servers row id, or null when a real system
     *                  is configured but has no usable config entry
     */
    private function seedMcpServer(): ?int
    {
        $now = now();

        $server = [
            'url' => 'https://rag.mock.hawki.test/mcp',
            'server_label' => 'hawki-rag',
            'description' => 'HAWKI web search and knowledge base tools.',
            'require_approval' => 'never',
            'api_key' => 'mock-rag-api-key',
            'type' => McpServerType::SSE->value,
            'status' => OnlineStatus::ONLINE->value,
            'timeouts' => ['read' => 30, 'connect' => 5],
            'added_by_file' => false,
        ];

        if ($this->realRagConfigured()) {
            $realServer = $this->realRagServerConfig();

            if (null === $realServer) {
                return null;
            }

            // Seed the live configuration instead of the mock.
            $server = $realServer;
        }

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

        // Only the mock asserts a status; the real rag entry never pings,
        // so the column default (unknown) applies and a live row's
        // already-known status survives a re-seed.
        if (isset($server['status'])) {
            $row['status'] = $server['status'];
        }

        DB::table('mcp_servers')->updateOrInsert(['url' => $server['url']], $row);

        return (int) DB::table('mcp_servers')->where('url', $server['url'])->value('id');
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
     * translated into the seeder's row shape (field mapping mirrors
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

    /** @return list<int> ids of the seeded tools */
    private function seedTools(int $serverId): array
    {
        $now = now();
        $ragConfig = app(RagConfig::class);

        $definitions = [
            [
                'name' => $ragConfig->webSearchToolName,
                'mcp_name' => 'web-search-tool',
                'description' => 'Run a web search via the configured provider (brave or tavily).',
                'capability' => WellKnownCapabilities::WEB_SEARCH,
                'access_rule' => 'web_search',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'A query string to search the web with',
                        ],
                        'max_results' => [
                            'type' => 'integer',
                            'description' => 'The maximum number of results to return',
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name' => $ragConfig->queryToolName,
                'mcp_name' => 'query-search',
                'description' => 'Search and retrieve specific information related to HAWK and internal knowledge base with a query.',
                'capability' => WellKnownCapabilities::KNOWLEDGE_BASE,
                'access_rule' => 'internal_search',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Retrieve relevant information from the knowledge base. Formulate a precise and context-rich search query including specific names, entities, relationships, dates, or domain terminology. Avoid vague or generic wording.',
                        ],
                        'top_k' => [
                            'type' => 'integer',
                            'description' => 'Number of chunks to retrieve',
                        ],
                    ],
                    // `dataset_id` is intentionally absent: it is injected
                    // server-side (RagKnowledgeAgentTool -> LaravelMcpTool
                    // settings) and must stay invisible to the model.
                    'required' => ['query'],
                ],
            ],
        ];

        $ids = [];
        foreach ($definitions as $def) {
            DB::table('ai_tools')->updateOrInsert(
                ['name' => $def['name']],
                [
                    'type' => ToolType::MCP->value,
                    'class_name' => null,
                    'mcp_server_id' => $serverId,
                    'mcp_name' => $def['mcp_name'],
                    // `mcp_config.inputSchema` is what LaravelMcpTool exposes
                    // to the model; without it the tool appears parameterless.
                    'mcp_config' => json_encode(['name' => $def['mcp_name'], 'inputSchema' => $def['inputSchema']]),
                    'description' => $def['description'],
                    'capability' => $def['capability'],
                    'mapped_capability' => null,
                    'access_rule' => $def['access_rule'],
                    'active' => true,
                    'added_by_file' => false,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
            $ids[] = (int) DB::table('ai_tools')->where('name', $def['name'])->value('id');
        }

        return $ids;
    }

    /**
     * Attaches the RAG tools to every tool-calling-capable model —
     * knowledge base and web search are core capabilities, not demo
     * entries. Silently does nothing if `models:sync` hasn't populated
     * `ai_models` yet — this seeder doesn't own that data.
     *
     * @param list<int> $toolIds
     */
    private function assignToModels(array $toolIds): void
    {
        $models = AiModel::all()->filter(fn (AiModel $model) => $model->settings->canUseTools());

        if ($models->isEmpty()) {
            $this->command?->warn(
                'RagToolSeeder: no tool-calling-capable AiModel found (has `models:sync` run yet?) '
                . '— RAG tools were seeded but not assigned to any model.'
            );
            return;
        }

        $typesById = DB::table('ai_tools')
            ->whereIn('id', $toolIds)
            ->pluck('type', 'id');

        foreach ($models as $model) {
            $model->tools()->syncWithoutDetaching(
                collect($toolIds)->mapWithKeys(fn (int $id) => [
                    $id => ['type' => $typesById[$id]],
                ])->all()
            );
        }
    }
}
