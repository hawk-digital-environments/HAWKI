<?php

namespace App\Console\Commands;

use App\Services\AI\AIProviderFactory;
use App\Models\ApiFormat;
use App\Models\ProviderSetting;
use App\Models\ApiFormatEndpoint;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class AiCacheManagement extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:cache 
                            {action : Action to perform (clear, warm, stats, clear-all)}
                            {--provider= : Specific provider ID to target}
                            {--endpoint= : Specific endpoint ID to target}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage AI service caches for URLs and provider instances';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->argument('action');
        $providerId = $this->option('provider');
        $endpointId = $this->option('endpoint');

        switch ($action) {
            case 'clear':
                return $this->clearCaches($providerId, $endpointId);
            case 'warm':
                return $this->warmCaches($providerId);
            case 'stats':
                return $this->showCacheStats();
            case 'clear-all':
                return $this->clearAllCaches();
            default:
                $this->error("Unknown action: {$action}");
                $this->showUsage();
                return 1;
        }
    }

    /**
     * Clear specific or all caches
     */
    private function clearCaches(?string $providerId = null, ?string $endpointId = null): int
    {
        if ($endpointId) {
            return $this->clearEndpointCache($endpointId);
        }

        if ($providerId) {
            return $this->clearProviderCache($providerId);
        }

        // Clear all AI-related caches
        $this->clearAllAiCaches();
        $this->info("✅ All AI caches cleared successfully");
        return 0;
    }

    /**
     * Clear cache for specific endpoint
     */
    private function clearEndpointCache(string $endpointId): int
    {
        $endpoint = ApiFormatEndpoint::find($endpointId);
        if (!$endpoint) {
            $this->error("Endpoint not found: {$endpointId}");
            return 1;
        }

        $endpoint->clearUrlCache();
        $this->info("✅ Cache cleared for endpoint: {$endpoint->name} (ID: {$endpointId})");
        return 0;
    }

    /**
     * Clear cache for specific provider
     */
    private function clearProviderCache(string $providerId): int
    {
        $provider = ProviderSetting::find($providerId);
        if (!$provider) {
            $this->error("Provider not found: {$providerId}");
            return 1;
        }

        $provider->clearUrlCaches();
        
        // Also clear factory cache for this provider
        Cache::forget("provider_data_{$providerId}");
        
        $this->info("✅ Cache cleared for provider: {$provider->provider_name} (ID: {$providerId})");
        return 0;
    }

    /**
     * Clear all AI-related caches
     */
    private function clearAllAiCaches(): void
    {
        // Clear factory caches
        $factory = app(AIProviderFactory::class);
        $factory->clearAllCaches();

        // Clear all provider caches
        ProviderSetting::all()->each(function ($provider) {
            $provider->clearUrlCaches();
        });

        // Clear all endpoint caches
        ApiFormatEndpoint::all()->each(function ($endpoint) {
            $endpoint->clearUrlCache();
        });

        // Clear API format related caches
        ApiFormat::all()->each(function ($apiFormat) {
            $apiFormat->clearRelatedCaches();
        });
    }

    /**
     * Warm up caches by pre-loading frequently used data
     */
    private function warmCaches(?string $providerId = null): int
    {
        $this->info("🔥 Warming up AI caches...");

        if ($providerId) {
            return $this->warmProviderCache($providerId);
        }

        // Warm up all active providers
        $providers = ProviderSetting::where('is_active', true)->get();
        
        $this->line("Found {$providers->count()} active providers to warm up");

        foreach ($providers as $provider) {
            $this->warmProviderCache($provider->id);
        }

        $this->info("✅ Cache warm-up completed successfully");
        return 0;
    }

    /**
     * Warm cache for specific provider
     */
    private function warmProviderCache(string $providerId): int
    {
        $provider = ProviderSetting::find($providerId);
        if (!$provider) {
            $this->error("Provider not found: {$providerId}");
            return 1;
        }

        $this->line("🔥 Warming cache for provider: {$provider->provider_name}");

        try {
            // Pre-load URLs (this will cache them)
            $pingUrl = $provider->ping_url;
            $chatUrl = $provider->chat_url;

            // Pre-load provider instance
            $factory = app(AIProviderFactory::class);
            $factory->getProviderInterfaceById($providerId);

            $this->line("   ✅ URLs cached: ping=" . ($pingUrl ? '✓' : '✗') . ", chat=" . ($chatUrl ? '✓' : '✗'));
            
            return 0;
        } catch (\Exception $e) {
            $this->error("   ❌ Failed to warm cache: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Show cache statistics
     */
    private function showCacheStats(): int
    {
        $this->info("📊 AI Cache Statistics");
        $this->newLine();

        // Factory stats
        $factory = app(AIProviderFactory::class);
        $factoryStats = $factory->getCacheStats();

        $this->line("🏭 <fg=cyan>AIProviderFactory:</>");
        $this->line("   Model mappings cached: {$factoryStats['mappings_cached']}");
        $this->line("   Provider instances cached: {$factoryStats['instances_cached']}");
        $this->line("   Cache TTL (mappings): {$factoryStats['cache_ttl_mappings']}s");
        $this->line("   Cache TTL (instances): {$factoryStats['cache_ttl_instances']}s");

        $this->newLine();

        // Provider stats
        $providers = ProviderSetting::where('is_active', true)->get();
        $this->line("🔗 <fg=cyan>Active Providers:</> {$providers->count()}");

        $cachedUrls = 0;
        foreach ($providers as $provider) {
            $pingCached = Cache::has("provider_ping_url_{$provider->id}_{$provider->updated_at?->timestamp}");
            $chatCached = Cache::has("provider_chat_url_{$provider->id}_{$provider->updated_at?->timestamp}");
            
            if ($pingCached || $chatCached) {
                $cachedUrls++;
                $this->line("   {$provider->provider_name}: ping=" . ($pingCached ? '✓' : '✗') . ", chat=" . ($chatCached ? '✓' : '✗'));
            }
        }

        $this->line("   Providers with cached URLs: {$cachedUrls}/{$providers->count()}");

        $this->newLine();

        // Endpoint stats
        $endpoints = ApiFormatEndpoint::where('is_active', true)->get();
        $this->line("🔚 <fg=cyan>Active Endpoints:</> {$endpoints->count()}");

        return 0;
    }

    /**
     * Clear all caches (nuclear option)
     */
    private function clearAllCaches(): int
    {
        $this->warn("⚠️  This will clear ALL Laravel caches, not just AI caches!");
        
        if (!$this->confirm('Are you sure you want to continue?')) {
            $this->info('Operation cancelled');
            return 0;
        }

        // Clear all Laravel caches
        $this->call('cache:clear');
        $this->call('config:clear');
        $this->call('route:clear');
        $this->call('view:clear');

        $this->info("✅ All caches cleared (including AI caches)");
        return 0;
    }

    /**
     * Show usage examples
     */
    private function showUsage(): void
    {
        $this->newLine();
        $this->line('<fg=yellow>Usage examples:</>');
        $this->line('  php artisan ai:cache clear                  # Clear all AI caches');
        $this->line('  php artisan ai:cache clear --provider=1     # Clear cache for provider ID 1');
        $this->line('  php artisan ai:cache clear --endpoint=5     # Clear cache for endpoint ID 5');
        $this->line('  php artisan ai:cache warm                   # Warm up all provider caches');
        $this->line('  php artisan ai:cache warm --provider=1      # Warm up cache for provider ID 1');
        $this->line('  php artisan ai:cache stats                  # Show cache statistics');
        $this->line('  php artisan ai:cache clear-all              # Clear ALL Laravel caches');
    }
}
