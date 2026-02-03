<?php

namespace App\Console\Commands;

use App\Services\AI\Tools\Interfaces\MCPToolInterface;
use App\Services\AI\Tools\MCP\MCPSSEClient;
use App\Services\AI\Tools\ToolRegistry;
use Illuminate\Console\Command;

class ListAllTools extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tools:list {--refresh : Force refresh MCP tool discovery} {--json : Output as JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all available tools (function calling and MCP)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $refresh = $this->option('refresh');
        $jsonOutput = $this->option('json');

        if ($refresh) {
            $this->info('🔄 Refreshing MCP tool cache...');
            $cachePath = storage_path('framework/cache/mcp-tools.php');
            if (file_exists($cachePath)) {
                unlink($cachePath);
            }
            // Re-bootstrap to trigger discovery
            $this->call('cache:clear');
        }

        $allTools = $this->gatherAllTools();

        if ($jsonOutput) {
            $this->line(json_encode($allTools, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $this->displayTools($allTools);

        return Command::SUCCESS;
    }

    /**
     * Gather all tools from all sources
     */
    private function gatherAllTools(): array
    {
        $tools = [
            'function_calling' => [],
            'mcp' => [],
            'summary' => [
                'total' => 0,
                'function_calling_count' => 0,
                'mcp_count' => 0,
                'servers_count' => 0,
            ],
        ];

        // Get function calling tools
        $availableTools = config('tools.available_tools', []);
        foreach ($availableTools as $toolClass) {
            if (class_exists($toolClass)) {
                try {
                    $tool = app($toolClass, ['serverConfig' => []]);
                    $tools['function_calling'][] = [
                        'name' => $tool->getName(),
                        'class' => $toolClass,
                        'description' => $tool->getDefinition()->description,
                    ];
                } catch (\Exception $e) {
                    $tools['function_calling'][] = [
                        'name' => 'ERROR',
                        'class' => $toolClass,
                        'description' => 'Failed to instantiate: ' . $e->getMessage(),
                    ];
                }
            }
        }

        // Get MCP tools from registry
        $registry = app(ToolRegistry::class);
        $mcpTools = $registry->getMCPTools();

        $serverGroups = [];
        foreach ($mcpTools as $tool) {
            $toolName = $tool->getName();
            $definition = $tool->getDefinition();

            // Extract server from tool name (format: server_label.tool_name)
            $parts = explode('.', $toolName, 2);
            $serverLabel = $parts[0] ?? 'unknown';
            $shortName = $parts[1] ?? $toolName;

            if (!isset($serverGroups[$serverLabel])) {
                $serverGroups[$serverLabel] = [];
            }

            $serverGroups[$serverLabel][] = [
                'name' => $toolName,
                'short_name' => $shortName,
                'description' => $definition->description,
                'parameters' => count($definition->parameters['properties'] ?? []),
            ];
        }

        $tools['mcp'] = $serverGroups;

        // Calculate summary
        $tools['summary']['function_calling_count'] = count($tools['function_calling']);
        $tools['summary']['mcp_count'] = count($mcpTools);
        $tools['summary']['servers_count'] = count($serverGroups);
        $tools['summary']['total'] = $tools['summary']['function_calling_count'] + $tools['summary']['mcp_count'];

        return $tools;
    }

    /**
     * Display tools in a formatted way
     */
    private function displayTools(array $tools): void
    {
        $this->newLine();
        $this->info('╔════════════════════════════════════════════════════════════════╗');
        $this->info('║              🛠️  HAWKI TOOLS OVERVIEW                          ║');
        $this->info('╚════════════════════════════════════════════════════════════════╝');
        $this->newLine();

        // Summary
        $summary = $tools['summary'];
        $this->line("📊 <fg=cyan>Summary:</>");
        $this->line("   Total Tools: <fg=green>{$summary['total']}</>");
        $this->line("   Function Calling: <fg=yellow>{$summary['function_calling_count']}</>");
        $this->line("   MCP Tools: <fg=blue>{$summary['mcp_count']}</> (from {$summary['servers_count']} servers)");
        $this->newLine();

        // Function Calling Tools
        if (!empty($tools['function_calling'])) {
            $this->line('═══════════════════════════════════════════════════════════════');
            $this->line('📦 <fg=yellow;options=bold>FUNCTION CALLING TOOLS</> (Local Execution)');
            $this->line('═══════════════════════════════════════════════════════════════');
            $this->newLine();

            foreach ($tools['function_calling'] as $tool) {
                $this->line("  <fg=green>●</> <fg=cyan>{$tool['name']}</>");
                $this->line("    <fg=gray>Class:</> {$tool['class']}");
                $this->line("    <fg=gray>Description:</> {$tool['description']}");
                $this->newLine();
            }
        } else {
            $this->line('═══════════════════════════════════════════════════════════════');
            $this->line('📦 <fg=yellow;options=bold>FUNCTION CALLING TOOLS</> (Local Execution)');
            $this->line('═══════════════════════════════════════════════════════════════');
            $this->warn('   No function calling tools configured');
            $this->newLine();
        }

        // MCP Tools by Server
        if (!empty($tools['mcp'])) {
            $this->line('═══════════════════════════════════════════════════════════════');
            $this->line('🌐 <fg=blue;options=bold>MCP TOOLS</> (Remote Execution via MCP Servers)');
            $this->line('═══════════════════════════════════════════════════════════════');
            $this->newLine();

            foreach ($tools['mcp'] as $serverLabel => $serverTools) {
                $this->line("  <fg=magenta>▶</> Server: <fg=magenta;options=bold>{$serverLabel}</>");
                $this->line("    <fg=gray>Tools:</> " . count($serverTools));
                $this->newLine();

                foreach ($serverTools as $tool) {
                    $this->line("    <fg=green>●</> <fg=cyan>{$tool['name']}</>");
                    $this->line("      <fg=gray>Description:</> {$tool['description']}");
                    $this->line("      <fg=gray>Parameters:</> {$tool['parameters']}");
                    $this->newLine();
                }
            }
        } else {
            $this->line('═══════════════════════════════════════════════════════════════');
            $this->line('🌐 <fg=blue;options=bold>MCP TOOLS</> (Remote Execution via MCP Servers)');
            $this->line('═══════════════════════════════════════════════════════════════');
            $this->warn('   No MCP tools discovered. Check your MCP server configuration.');
            $this->newLine();
        }

        // Footer
        $this->line('═══════════════════════════════════════════════════════════════');
        $this->line('💡 <fg=gray>Tip:</> Use <fg=cyan>--refresh</> to force re-discover MCP tools');
        $this->line('💡 <fg=gray>Tip:</> Use <fg=cyan>--json</> to output as JSON');
        $this->newLine();
    }
}
