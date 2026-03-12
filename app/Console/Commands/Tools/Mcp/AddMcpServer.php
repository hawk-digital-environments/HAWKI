<?php

namespace App\Console\Commands\Tools\Mcp;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Models\Ai\Tools\AiTool;
use App\Models\Ai\Tools\McpServer;
use App\Services\AI\Tools\MCP\MCPSSEClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use const http\Client\Curl\Features\HTTP2;

class AddMcpServer extends Command
{
    protected $signature = 'tools:add-mcp-server
                            {url}
                            {--label=}
                            {--description=}
                            {--require_approval=never}
                            {--timeout=30}
                            {--discovery_timeout=5}
                            {--api_key=}';

    protected $description = 'Add an MCP server, discover its tools, and assign them to AI models';

    public function handle(): int
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
            $server = $this->createNewServer($url);

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
        $result = $server->fetchServerTools();

        if (!$result['success']) {
            $this->error($result['message'] ?? 'Tool discovery failed.');
            return Command::FAILURE;
        }

        $tools   = $result['tools'];
        $toolMap = [];

        $this->newLine();
        $this->info('The following tools were discovered:');
        foreach ($tools as $index => $tool) {
            $this->line(sprintf('  %d. <fg=cyan>%s</> — %s', $index + 1, $tool['name'], $tool['description']));
            $toolMap[$tool['name']] = $tool;
        }
        $this->newLine();

        // ── 3. Select tools to register ───────────────────────────────────────
        $toolOptions   = array_keys($toolMap);
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

            $capability     = $this->askCapability($name, $existingCapabilities);
            $aiTool         = $server->setupTools($toolMap[$name], $capability);
            $createdTools[] = $aiTool;

            // Make newly chosen capability available for subsequent tools in this run
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
            $this->info('You can assign tools later with: <comment>php artisan tools:assign</comment>');
            return Command::SUCCESS;
        }

        $this->assignToolsToModels($createdTools);

        return Command::SUCCESS;
    }

    /**
     * Interactive model-assignment flow.
     * Supports assignment by provider (bulk) or by individual model.
     *
     * @param AiTool[] $tools
     */
    public function assignToolsToModels(array $tools): void
    {
        $modeOptions = [
            'By Provider (assigns to all eligible models in the provider)',
            'By Model (select individual models)',
        ];

        $mode = $this->choice('How do you want to assign these tools?', $modeOptions, 0);

        if ($mode === $modeOptions[0]) {
            $this->assignByProvider($tools);
        } else {
            $this->assignByModel($tools);
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
        $choices   = array_merge([$newOption], $existingCapabilities);

        $choice = $this->choice('  Select or create a capability', $choices, 0);

        if ($choice === $newOption) {
            $default    = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $toolName));
            $capability = $this->ask(
                '  Enter capability name (snake_case, e.g. knowledge_base)',
                $default
            );
            return trim($capability) ?: $default;
        }

        return $choice;
    }

    private function createNewServer(string $url): McpServer|int
    {
        $label            = $this->option('label');
        $description      = $this->option('description');
        $requireApproval  = $this->option('require_approval');
        $timeout          = $this->option('timeout');
        $discoveryTimeout = $this->option('discovery_timeout');
        $apiKey           = $this->option('api_key');

        if (empty($apiKey)) {
            $apiKey = $this->secret('Please enter the API key for the MCP server (leave blank if none)') ?: null;
        }


        $client = new MCPSSEClient(
            $url,
            (int) 5,
            "1|Mx1y74lMsg02Npzih1r76jadBKG6iKEiiaG4ncEA4021f614"
        );

        $info = $client->getServerInfo();
        $name = $info['name'];
        $version = $info['version'];
        $protocolVersion = $info['protocolVersion'];

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

        if (!is_numeric($timeout) || $timeout <= 0) {
            $timeout = $this->ask('Please enter timeout in seconds', '30');
        }

        if (!is_numeric($discoveryTimeout) || $discoveryTimeout <= 0) {
            $discoveryTimeout = $this->ask('Please enter discovery timeout in seconds', '5');
        }

        return $this->createMcpServer(
            $url,
            $label,
            $version,
            $protocolVersion,
            $description,
            $requireApproval,
            $timeout,
            $discoveryTimeout,
            $apiKey
        );
    }

    private function assignByProvider(array $tools): void
    {
        $providers = AiProvider::withCount('models')->get();

        if ($providers->isEmpty()) {
            $this->warn('No providers found in database. Run <comment>php artisan models:sync</comment> first.');
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

        // Strip the "(N models)" suffix to get bare provider names
        $selectedNames = collect($selected)
            ->map(fn($s) => preg_replace('/\s*\(\d+ models\)$/', '', $s))
            ->toArray();

        $models = AiModel::whereHas('provider', fn($q) => $q->whereIn('name', $selectedNames))
            ->where('active', true)
            ->get()
            ->filter(fn($m) => ($m->tools ?: [])['tool_calling'] ?? true);

        if ($models->isEmpty()) {
            $this->warn('No eligible models found for the selected providers.');
            $this->line('Note: Models with <fg=yellow>tool_calling: false</> in their configuration are excluded.');
            return;
        }

        $this->line("  Found <fg=green>{$models->count()}</> eligible model(s) across selected provider(s).");
        $this->assignToModels($tools, $models);
    }

    private function assignByModel(array $tools): void
    {
        $models = AiModel::with('provider')
            ->where('active', true)
            ->orderBy('model_id')
            ->get()
            ->filter(fn($m) => ($m->tools ?: [])['tool_calling'] ?? true);

        if ($models->isEmpty()) {
            $this->warn('No active tool-capable models found. Run <comment>php artisan models:sync</comment> first.');
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

        $this->assignToModels($tools, $selectedModels);
    }

    private function assignToModels(array $tools, $models): void
    {
        $assignedCount = 0;
        foreach ($tools as $tool) {
            foreach ($models as $model) {
                $model->assignedTools()->syncWithoutDetaching([
                    $tool->id => ['type' => $tool->type],
                ]);
                $assignedCount++;
            }
        }

        AiModel::clearCapabilitiesCache();
        $this->newLine();
        $this->info("  ✓ Created {$assignedCount} model-tool assignment(s).");
        $this->info('  Use <comment>php artisan tools:list</comment> to review the configuration.');
    }

    protected function createMcpServer(
        string  $url,
        string  $label,
        string  $version,
        string  $protocolVersion,
        string  $description,
        string  $requireApproval,
        string  $timeout,
        string  $discoveryTimeout,
        ?string $apiKey
    ): McpServer|int {
        try {
            $server = McpServer::create([
                'url'               => $url,
                'server_label'      => $label,
                'version'           => $version,
                'protocolVersion'   => $protocolVersion,
                'description'       => $description,
                'require_approval'  => $requireApproval,
                'timeout'           => (int) $timeout,
                'discovery_timeout' => (int) $discoveryTimeout,
                'api_key'           => $apiKey ?? '',
            ]);

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
}
