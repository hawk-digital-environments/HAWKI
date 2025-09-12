<?php

namespace App\Console\Commands;

use App\Models\ApiFormat;
use Illuminate\Console\Command;

class SyncApiFormatProviderClasses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api-formats:sync-provider-classes 
                            {--dry-run : Show what would be updated without making changes}
                            {--force : Force update even if provider_class already exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync provider_class information to api_formats metadata';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        // Define the correct provider class mappings
        $providerMappings = [
            'openai-api' => 'App\\Services\\AI\\Providers\\OpenAIProvider',
            'ollama-api' => 'App\\Services\\AI\\Providers\\OllamaProvider',
            'google-generative-language-api' => 'App\\Services\\AI\\Providers\\GoogleProvider',
            'google-vertex-ai-api' => 'App\\Services\\AI\\Providers\\GoogleProvider',
            'gwdg-api' => 'App\\Services\\AI\\Providers\\GWDGProvider',
            'openwebui-api' => 'App\\Services\\AI\\Providers\\OpenWebUIProvider',
            'anthropic-api' => 'App\\Services\\AI\\Providers\\AnthropicProvider',
            'huggingface-api' => 'App\\Services\\AI\\Providers\\OpenAIProvider', // OpenAI-compatible
            'cohere-api' => 'App\\Services\\AI\\Providers\\OpenAIProvider', // OpenAI-compatible
        ];

        $this->info('Syncing API Format Provider Classes...');
        $this->newLine();

        $updated = 0;
        $skipped = 0;

        foreach ($providerMappings as $uniqueName => $providerClass) {
            $apiFormat = ApiFormat::where('unique_name', $uniqueName)->first();
            
            if (!$apiFormat) {
                $this->warn("API Format '{$uniqueName}' not found in database");
                continue;
            }

            // Parse current metadata
            $metadata = is_string($apiFormat->metadata) 
                ? json_decode($apiFormat->metadata, true) 
                : ($apiFormat->metadata ?? []);

            // Check if provider_class already exists
            if (isset($metadata['provider_class']) && !$force) {
                $this->line("🔄 <fg=yellow>Skipping:</> {$uniqueName} (provider_class already exists: {$metadata['provider_class']})");
                $skipped++;
                continue;
            }

            // Verify the provider class exists
            if (!class_exists($providerClass)) {
                $this->error("❌ Provider class {$providerClass} does not exist!");
                continue;
            }

            // Update metadata
            $metadata['provider_class'] = $providerClass;

            if ($dryRun) {
                $this->line("🔍 <fg=cyan>Would update:</> {$uniqueName} -> {$providerClass}");
            } else {
                $apiFormat->metadata = $metadata;
                $apiFormat->save();
                $this->line("✅ <fg=green>Updated:</> {$uniqueName} -> {$providerClass}");
            }
            
            $updated++;
        }

        $this->newLine();
        
        if ($dryRun) {
            $this->info("Dry run completed. Would update {$updated} API formats, {$skipped} skipped.");
            $this->line("Run without --dry-run to apply changes.");
        } else {
            $this->info("Sync completed. Updated {$updated} API formats, {$skipped} skipped.");
        }

        return 0;
    }
}
