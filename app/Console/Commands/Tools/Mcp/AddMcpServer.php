<?php

namespace App\Console\Commands\Tools\Mcp;

use App\Models\Tools\McpServer;
use Illuminate\Console\Command;

class AddMcpServer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tools:add-mcp-server
                            {url}
                            {--label}
                            {--description}
                            {--require_approval="never"}
                            {--timeout="30"}
                            {--discovery_timeout="5"}
                            {--api_key}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add a MCP Server to HAWKI';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $url = $this->argument('url');
        $label = $this->option('label');
        $description = $this->option('description');
        $require_approval = $this->option('require_approval');
        $timeout = $this->option('timeout');
        $discoveryTimeout = $this->option('discovery_timeout');
        $apiKey = $this->option('api_key');

        // Validate URL
        if (empty($url)) {
            $this->error('URL is required.');
            return 1;
        }

        // Check if label is not set and prompt user
        if (empty($label)) {
            $label = $this->ask('Please enter a label for the MCP server', 'MCP Server');
        }

        // Check if description is not set and prompt user
        if (empty($description)) {
            $description = $this->ask('Please enter a description for the MCP server', '');
        }

        // Check if API key is not set and prompt user
        if (empty($apiKey)) {
            $apiKey = $this->secret('Please enter the API key for the MCP server (optional)');
        }

        // Validate require_approval option
        $validApprovalOptions = ['never', 'always', 'auto'];
        if (!in_array($require_approval, $validApprovalOptions)) {
            $require_approval = $this->choice(
                'When should approval be required?',
                $validApprovalOptions,
                'never'
            );
        }

        // Validate timeout
        if (!is_numeric($timeout) || $timeout <= 0) {
            $timeout = $this->ask('Please enter timeout in seconds', '30');
        }

        // Validate discovery_timeout
        if (!is_numeric($discoveryTimeout) || $discoveryTimeout <= 0) {
            $discoveryTimeout = $this->ask('Please enter discovery timeout in seconds', '5');
        }

        // Check if API key is not set and ask user if they need one
        if (empty($apiKey)) {
            $needsApiKey = $this->confirm('Does this MCP server require an API key?', false);
            if ($needsApiKey) {
                $apiKey = $this->secret('Please enter the API key for the MCP server');
            }
        }

        // Add your logic to actually add the MCP server here
        $this->info("Adding MCP server with:");
        $this->info("- URL: {$url}");
        $this->info("- Label: {$label}");
        $this->info("- Description: {$description}");
        $this->info("- Require Approval: {$require_approval}");
        $this->info("- Timeout: {$timeout}s");
        $this->info("- Discovery Timeout: {$discoveryTimeout}s");
        $this->info("- API Key: " . ($apiKey ? '***' : 'None'));

        // Create the MCP server record
        $server = $this->createMcpServer(
            $url,
            $label,
            $description,
            $require_approval,
            $timeout,
            $discoveryTimeout,
            $apiKey,
        );

        $discoverTools = $this->ask('Do you want to discover tools on this server?', true);

        if(!$discoverTools){
            return null;
        }

        $result = $server->fetchServerTools($discoverTools);
        if(!$result['success']){
            $this->error($result['message']);
        }
        $tools = $result['tools'];

        $this->info('The following tools were discovered on this server:');
        $toolOptions = [];
        $toolMap = [];

        foreach($tools as $index => $tool){
            $toolOptions[] = $tool['name'];
            $toolMap[$tool['name']] = $tool;
            $this->info(($index + 1) . '. ' . $tool['name'] . ':  ' . $tool['description']);
        }

        // Allow multiple selections
        $selectedTools = $this->choice(
            'Select which tools you want to add (use comma to separate multiple selections)',
            $toolOptions,
            null, // no default
            null, // no maximum selections limit
            true // allow multiple selections
        );
        // If user selects none or cancels
        if (empty($selectedTools)) {
            $this->info('No tools selected. Exiting.');
            return;
        }
        // Convert selected names back to full tool data
        $selectedToolData = [];
        if (!is_array($selectedTools)) {
            $selectedTools = [$selectedTools];
        }

        foreach ($selectedTools as $toolName) {
            if (isset($toolMap[$toolName])) {
                $selectedToolData[] = $toolMap[$toolName];
            }
        }






    }


    protected function createMcpServer(
        string $url,
        string $label,
        string $description,
        string $require_approval,
        string $timeout,
        string $discoveryTimeout,
        string $apiKey,
    ): McpServer
    {
        try {
            $mcpServer = McpServer::create([
                'url' => $url,
                'server_label' => $label,
                'description' => $description,
                'require_approval' => $require_approval,
                'timeout' => (int)$timeout,
                'discovery_timeout' => (int)$discoveryTimeout,
                'api_key' => $apiKey,
            ]);
            $this->info("MCP server added successfully!");
            $this->info("ID: {$mcpServer->id}");
            $this->info("URL: {$mcpServer->url}");
            $this->info("Label: {$mcpServer->server_label}");
            return $mcpServer;
            // ... rest of success message
        } catch (\Exception $e) {
            $this->error("Failed to add MCP server: " . $e->getMessage());
            return 1;
        }
    }

}
