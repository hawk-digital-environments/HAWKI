<?php

namespace App\Console\Commands;

use App\Models\ProviderSetting;
use App\Services\AI\AIProviderFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DebugGoogleProviderCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debug:google-provider {--clear-cache : Clear AI caches before testing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Debug Google provider loading issues';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Debugging Google Provider Issue...');

        if ($this->option('clear-cache')) {
            $this->info('🧹 Clearing AI caches...');
            $factory = app(AIProviderFactory::class);
            $factory->clearAllCaches();
            $this->info('✅ Caches cleared');
        }

        // Step 1: Check database configuration
        $this->info('📊 Step 1: Checking database configuration');
        $provider = ProviderSetting::with('apiFormat')->where('provider_name', 'Google')->first();
        
        if (!$provider) {
            $this->error('❌ No Google provider found in database');
            return 1;
        }

        $this->info("✅ Google provider found (ID: {$provider->id})");
        $this->info("   - Active: " . ($provider->is_active ? 'YES' : 'NO'));
        $this->info("   - API Format: " . ($provider->apiFormat ? $provider->apiFormat->unique_name : 'NONE'));

        if (!$provider->apiFormat) {
            $this->error('❌ No API format configured for Google provider');
            return 1;
        }

        // Step 2: Check metadata
        $this->info('📋 Step 2: Checking API format metadata');
        $metadata = is_string($provider->apiFormat->metadata)
            ? json_decode($provider->apiFormat->metadata, true)
            : ($provider->apiFormat->metadata ?? []);

        if (isset($metadata['provider_class'])) {
            $this->info("✅ Provider class in metadata: {$metadata['provider_class']}");
            
            // Step 3: Check class existence
            $this->info('🔍 Step 3: Checking class existence');
            if (class_exists($metadata['provider_class'])) {
                $this->info("✅ Provider class exists and is loadable");
            } else {
                $this->error("❌ Provider class does not exist: {$metadata['provider_class']}");
                $this->info("   Please check autoloader or class file");
                return 1;
            }
        } else {
            $this->error('❌ No provider_class in metadata');
            $this->info('   Metadata content: ' . json_encode($metadata, JSON_PRETTY_PRINT));
            return 1;
        }

        // Step 4: Test derivation fallback (what happens when metadata fails)
        $this->info('🧪 Step 4: Testing derivation fallback');
        $apiFormatName = $provider->apiFormat->unique_name;
        $baseName = str_replace('-api', '', $apiFormatName);
        $derivedClassName = str_replace(' ', '', ucwords(str_replace('-', ' ', $baseName))) . 'Provider';
        $fullDerivedClassName = 'App\Services\AI\Providers\\' . $derivedClassName;
        
        $this->info("   - API Format: {$apiFormatName}");
        $this->info("   - Derived class: {$fullDerivedClassName}");
        $this->info("   - Derived class exists: " . (class_exists($fullDerivedClassName) ? 'YES' : 'NO'));

        // Step 5: Test factory loading
        $this->info('🏭 Step 5: Testing factory loading');
        try {
            $factory = app(AIProviderFactory::class);
            $providerInstance = $factory->getProviderInterfaceById($provider->id);
            $this->info("✅ Factory loading successful");
            $this->info("   - Instance class: " . get_class($providerInstance));
            $this->info("   - Provider ID: " . $providerInstance->getProviderId());
        } catch (\Exception $e) {
            $this->error("❌ Factory loading failed: " . $e->getMessage());
            return 1;
        }

        // Step 6: Test model loading
        $this->info('🤖 Step 6: Testing model loading');
        $testModel = \App\Models\LanguageModel::where('provider_id', $provider->id)
            ->where('is_active', true)
            ->first();

        if ($testModel) {
            $this->info("   - Test model: {$testModel->model_id}");
            try {
                $modelProvider = $factory->getProviderForModel($testModel->model_id);
                $this->info("✅ Model loading successful");
                $this->info("   - Instance class: " . get_class($modelProvider));
            } catch (\Exception $e) {
                $this->error("❌ Model loading failed: " . $e->getMessage());
                return 1;
            }
        } else {
            $this->warn('⚠️  No active Google models found for testing');
        }

        $this->info('🎉 All tests passed! Google provider should work correctly.');
        
        return 0;
    }
}
