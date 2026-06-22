<?php

namespace App\Console\Commands\Ai\Tools\Mcp;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Models\Ai\AiTool;
use App\Models\Ai\McpServer;
use App\Services\Ai\Repositories\AiModelToolRepository;
use App\Services\Ai\Repositories\AiToolRepository;
use App\Services\Ai\Repositories\McpServerRepository;
use App\Services\Ai\Tools\Mcp\McpClientFactory;
use App\Services\Ai\Tools\Values\McpToolDefinition;
use App\Services\Ai\Values\McpServerTimeouts;
use App\Services\Ai\Values\McpServerType;
use Illuminate\Console\Command;

class AddMcpServer extends Command
{
    protected $signature = 'ai:tools:mcp:add
                            {url}
                            {--label=}
                            {--description=}
                            {--require_approval=never}
                            {--connection_timeout=}
                            {--read_timeout=}
                            {--sse_idle_timeout=}
                            {--api_key=}';

    protected $description = 'Add an MCP server, discover its tools, and assign them to AI models';

    public function handle(
        McpClientFactory     $clientFactory,
        McpServerRepository  $serverRepo,
        AiToolRepository     $toolRepo,
        AiModelToolRepository $assignments
    ): int
    {
        $url = $this->argument('url');

        if (empty($url)) {
            $this->error('URL is required.');
            return Command::FAILURE;
        }

        // ── Check if URL already exists ───────────────────────────────────────
        $existing = McpServer::where('url', $url)->first();

        if ($existing) {
            $this->warn("An MCP server with this URL is already registered:");
            $this->line("  ID:    {$existing->id}");
            $this->line("  Label: {$existing->server_label}");
            $this->newLine();

            if (!$this->confirm('Reuse this server and proceed to tool setup?', true)) {
                $this->info('Aborted.');
                return Command::SUCCESS;
            }

            $server = $existing;
        } else {
            $server = $this->createNewServer($url, $clientFactory, $serverRepo);

            if (!$server instanceof McpServer) {
                return Command::FAILURE;
            }
        }

        // ── 2. Discover tools ─────────────────────────────────────────────────
        $discoverTools = $this->confirm('Do you want to discover tools on this server?', true);
        if (!$discoverTools) {
            $this->info('Skipping tool discovery. You can run it later with tools:add-mcp-server.');
            return Command::SUCCESS;
        }

        $this->info('Connecting to MCP server…');

        $client = $clientFactory->createForServer($server);
        $toolMap = [];

        try {
            $definitions = $client->listToolDefinitions();
            $index = 0;
            $this->newLine();
            $this->info('The following tools were discovered:');
            foreach ($definitions as $def) {
                $this->line(sprintf('  %d. <fg=cyan>%s</> — %s', $index + 1, $def->name, $def->description));
                $toolMap[$def->name] = $def;
                $index++;
            }
            $this->newLine();
        } catch (\Throwable $e) {
            $this->error('Tool discovery failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        if (empty($toolMap)) {
            $this->warn('No tools were discovered on this server.');
            return Command::SUCCESS;
        }

        // ── 3. Select tools to register ───────────────────────────────────────
        $toolOptions = array_keys($toolMap);
        $selectedNames = $this->choice(
            'Select which tools to register (comma-separate multiple, or press Enter for all)',
            $toolOptions,
            implode(',', array_keys($toolOptions)),
            null,
            true
        );

        if (empty($selectedNames)) {
            $this->info('No tools selected. Exiting.');
            return Command::SUCCESS;
        }

        if (!is_array($selectedNames)) {
            $selectedNames = [$selectedNames];
        }

        // ── 4. Persist AiTool records (with capability per tool) ──────────────
        $existingCapabilities = AiTool::whereNotNull('capability')
            ->where('capability', '!=', '')
            ->distinct()
            ->pluck('capability')
            ->toArray();

        /** @var AiTool[] $createdTools */
        $createdTools = [];
        foreach ($selectedNames as $name) {
            if (!isset($toolMap[$name])) {
                continue;
            }

            $capability = $this->askCapability($name, $existingCapabilities);

            /** @var McpToolDefinition $def */
            $def = $toolMap[$name];
            $defWithCapability = new McpToolDefinition(
                name: $def->name,
                description: $def->description,
                config: $def->config,
                capability: $capability,
            );
            $aiTool = $toolRepo->upsertMcp($defWithCapability, $server);
            $createdTools[] = $aiTool;

            if (!in_array($capability, $existingCapabilities, true)) {
                $existingCapabilities[] = $capability;
            }

            $this->info("  ✓ Registered tool: <fg=green>{$aiTool->name}</> [capability: <fg=yellow>{$capability}</>]");
        }

        if (empty($createdTools)) {
            $this->warn('No tools were registered.');
            return Command::SUCCESS;
        }

        // ── 5. Assign tools to AI models ───────────────────────────────────────
        $this->newLine();
        $assignNow = $this->confirm('Do you want to assign these tools to AI models now?', true);

        if (!$assignNow) {
            $this->info('You can assign tools later with: <comment>php artisan ai:tools:assign</comment>');
            return Command::SUCCESS;
        }

        $this->assignToolsToModels($createdTools, $assignments);

        return Command::SUCCESS;
    }

    /**
     * Interactive model-assignment flow.
     * Supports assignment by provider (bulk) or by individual model.
     *
     * @param AiTool[] $tools
     */
    public function assignToolsToModels(array $tools, AiModelToolRepository $assignments): void
    {
        $modeOptions = [
            'By Provider (assigns to all eligible models in the provider)',
            'By Model (select individual models)',
        ];

        $mode = $this->choice('How do you want to assign these tools?', $modeOptions, 0);

        if ($mode === $modeOptions[0]) {
            $this->assignByProvider($tools, $assignments);
        } else {
            $this->assignByModel($tools, $assignments);
        }
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Ask the user to select an existing capability or define a new one for the given tool.
     * The capability becomes the key in the model's tools array (e.g. 'knowledge_base').
     */
    private function askCapability(string $toolName, array $existingCapabilities): string
    {
        $this->newLine();
        $this->line("  <fg=cyan>Capability</> for tool <fg=green>{$toolName}</>:");
        $this->line("  This determines how the tool appears in the UI and how models are filtered.");

        $newOption = '[+ Define a new capability]';
        $choices = array_merge([$newOption], $existingCapabilities);

        $choice = $this->choice('  Select or create a capability', $choices, 0);

        if ($choice === $newOption) {
            $default = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $toolName));
            $capability = $this->ask(
                '  Enter capability name (snake_case, e.g. knowledge_base)',
                $default
            );
            return trim($capability) ?: $default;
        }

        return $choice;
    }

    private function createNewServer(
        string              $url,
        McpClientFactory    $clientFactory,
        McpServerRepository $serverRepo
    ): McpServer|int
    {
        $label = $this->option('label');
        $description = $this->option('description');
        $requireApproval = $this->option('require_approval');
        $apiKey = $this->option('api_key');

        $connectionTimeout = $this->option('connection_timeout');
        $readTimeout = $this->option('read_timeout');
        $sseIdleTimeout = $this->option('sse_idle_timeout');

        $timeouts = new McpServerTimeouts(
            readTimeout: is_numeric($readTimeout) && $readTimeout > 0 ? (float)$readTimeout : null,
            connectionTimeout: is_numeric($connectionTimeout) && $connectionTimeout > 0 ? (float)$connectionTimeout : null,
            sseIdleTimeout: is_numeric($sseIdleTimeout) && $sseIdleTimeout > 0 ? (float)$sseIdleTimeout : null,
        );

        if (empty($apiKey)) {
            $apiKey = $this->secret('Please enter the API key for the MCP server (leave blank if none)') ?: null;
        }

        $client = $clientFactory->createForConfig(
            url: $url,
            type: McpServerType::SSE,
            config: null,
            apiKey: $apiKey,
            timeouts: $timeouts
        );

        if (!$client->ping()) {
            $this->error('Failed to connect to the MCP server at the given URL.');
            return Command::FAILURE;
        }

        $name = $client->getName();

        if (empty($label)) {
            $label = $this->ask('Please enter a label for the MCP server', $name);
        }

        if (empty($description)) {
            $description = $this->ask('Please enter a description for the MCP server', '');
        }

        $validApprovalOptions = ['never', 'always', 'auto'];
        if (!in_array($requireApproval, $validApprovalOptions)) {
            $requireApproval = $this->choice('When should approval be required?', $validApprovalOptions, 'never');
        }

        try {
            $server = $serverRepo->upsert(
                url: $url,
                type: McpServerType::SSE,
                label: $label,
                description: $description ?: null,
                requireApproval: $requireApproval,
                timeouts: $timeouts,
                apiKey: $apiKey,
                additionalConfig: null,
                addedByFile: false
            );

            $this->info("MCP server added successfully!");
            $this->info("  ID:    {$server->id}");
            $this->info("  URL:   {$server->url}");
            $this->info("  Label: {$server->server_label}");

            return $server;
        } catch (\Exception $e) {
            $this->error("Failed to add MCP server: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function assignByProvider(array $tools, AiModelToolRepository $assignments): void
    {
        $providers = AiProvider::withCount('models')->get();

        if ($providers->isEmpty()) {
            $this->warn('No providers found in database. Run <comment>php artisan ai:models:sync</comment> first.');
            return;
        }

        $providerLabels = $providers->map(fn($p) => "{$p->name} ({$p->models_count} models)")->toArray();

        $selected = $this->choice(
            'Select providers (comma-separate multiple)',
            $providerLabels,
            null, null, true
        );

        if (empty($selected)) {
            $this->info('No providers selected.');
            return;
        }

        if (!is_array($selected)) {
            $selected = [$selected];
        }

        $selectedNames = collect($selected)
            ->map(fn($s) => preg_replace('/\s*\(\d+ models\)$/', '', $s))
            ->toArray();

        $models = AiModel::whereHas('provider', fn($q) => $q->whereIn('name', $selectedNames))
            ->where('active', true)
            ->get()
            ->filter(fn($m) => $m->settings->canUseTools());

        if ($models->isEmpty()) {
            $this->warn('No eligible models found for the selected providers.');
            $this->line('Note: Models without tool-calling enabled in their configuration are excluded.');
            return;
        }

        $this->line("  Found <fg=green>{$models->count()}</> eligible model(s) across selected provider(s).");
        $this->assignToModels($tools, $models, $assignments);
    }

    private function assignByModel(array $tools, AiModelToolRepository $assignments): void
    {
        $models = AiModel::with('provider')
            ->where('active', true)
            ->orderBy('model_id')
            ->get()
            ->filter(fn($m) => $m->settings->canUseTools());

        if ($models->isEmpty()) {
            $this->warn('No active tool-capable models found. Run <comment>php artisan ai:models:sync</comment> first.');
            return;
        }

        $modelLabels = $models->map(fn($m) => "{$m->model_id} ({$m->label})")->values()->toArray();

        $selectedLabels = $this->choice(
            'Select which models may use these tools (comma-separate multiple)',
            $modelLabels,
            null, null, true
        );

        if (empty($selectedLabels)) {
            $this->info('No models selected.');
            return;
        }

        if (!is_array($selectedLabels)) {
            $selectedLabels = [$selectedLabels];
        }

        $selectedModels = $models->filter(
            fn($m) => in_array("{$m->model_id} ({$m->label})", $selectedLabels)
        );

        $this->assignToModels($tools, $selectedModels, $assignments);
    }

    private function assignToModels(array $tools, $models, AiModelToolRepository $assignments): void
    {
        $assignedCount = 0;
        foreach ($tools as $tool) {
            foreach ($models as $model) {
                $assignments->assignTool($model, $tool);
                $assignedCount++;
            }
        }

        $this->newLine();
        $this->info("  ✓ Created {$assignedCount} model-tool assignment(s).");
        $this->info('  Use <comment>php artisan tools:list</comment> to review the configuration.');
    }
}
