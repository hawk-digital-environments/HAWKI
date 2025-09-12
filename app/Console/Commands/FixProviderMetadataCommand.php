<?php

namespace App\Console\Commands;

use App\Models\ApiFormat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixProviderMetadataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:provider-metadata {--dry-run : Show what would be changed without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix missing provider_class entries in API format metadata';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔧 Fixing Provider Metadata...');
        
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }

        // Define the correct provider_class mappings for each API format
        $providerClassMappings = [
            'openai-api' => 'App\\Services\\AI\\Providers\\OpenAIProvider',
            'ollama-api' => 'App\\Services\\AI\\Providers\\OllamaProvider',
            'google-generative-language-api' => 'App\\Services\\AI\\Providers\\GoogleProvider',
            'google-vertex-ai-api' => 'App\\Services\\AI\\Providers\\GoogleProvider',
            'anthropic-api' => 'App\\Services\\AI\\Providers\\AnthropicProvider',
            'gwdg-api' => 'App\\Services\\AI\\Providers\\GWDGProvider',
            'open-webui-api' => 'App\\Services\\AI\\Providers\\OpenWebUIProvider',
        ];

        $updatedCount = 0;
        $errorCount = 0;

        foreach ($providerClassMappings as $uniqueName => $providerClass) {
            try {
                $apiFormat = ApiFormat::where('unique_name', $uniqueName)->first();
                
                if (!$apiFormat) {
                    $this->warn("⚠️  API format not found: {$uniqueName}");
                    continue;
                }

                // Parse existing metadata
                $metadata = is_string($apiFormat->metadata)
                    ? json_decode($apiFormat->metadata, true)
                    : ($apiFormat->metadata ?? []);

                // Check if provider_class is missing or incorrect
                $needsUpdate = false;
                if (!isset($metadata['provider_class'])) {
                    $this->info("📝 Adding missing provider_class for: {$uniqueName}");
                    $needsUpdate = true;
                } elseif ($metadata['provider_class'] !== $providerClass) {
                    $this->info("🔄 Updating incorrect provider_class for: {$uniqueName}");
                    $this->info("   Old: {$metadata['provider_class']}");
                    $this->info("   New: {$providerClass}");
                    $needsUpdate = true;
                } else {
                    $this->info("✅ Correct provider_class already set for: {$uniqueName}");
                }

                if ($needsUpdate) {
                    // Add/update the provider_class
                    $metadata['provider_class'] = $providerClass;
                    
                    if (!$dryRun) {
                        // Update the database
                        $apiFormat->metadata = $metadata;
                        $apiFormat->save();
                        
                        $this->info("✅ Updated metadata for: {$uniqueName}");
                    } else {
                        $this->info("🔍 Would update metadata for: {$uniqueName}");
                    }
                    
                    $updatedCount++;
                }

            } catch (\Exception $e) {
                $this->error("❌ Error processing {$uniqueName}: " . $e->getMessage());
                $errorCount++;
            }
        }

        // Summary
        $this->newLine();
        $this->info('📊 Summary:');
        $this->info("   - Processed: " . count($providerClassMappings) . " API formats");
        $this->info("   - Updated: {$updatedCount}");
        $this->info("   - Errors: {$errorCount}");

        if ($dryRun && $updatedCount > 0) {
            $this->newLine();
            $this->warn('🔧 To apply changes, run without --dry-run flag:');
            $this->warn('   php artisan fix:provider-metadata');
        }

        if (!$dryRun && $updatedCount > 0) {
            $this->newLine();
            $this->info('🧹 Clearing AI caches to ensure changes take effect...');
            $this->call('ai:cache', ['action' => 'clear-all']);
        }

        return $errorCount > 0 ? 1 : 0;
    }
}
