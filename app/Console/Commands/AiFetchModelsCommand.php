<?php

namespace App\Console\Commands;

use App\Models\ApiProvider;
use App\Models\AiModel;
use App\Services\AI\AiService;
use App\Orchid\Traits\AiConnectionTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Fetch available models from AI providers.
 *
 * Usage:
 *  php artisan ai:fetch-models            # fetch all providers (hybrid: AI service first, then direct HTTP)
 *  php artisan ai:fetch-models OpenAI     # fetch only provider with name 'OpenAI'
 *  php artisan ai:fetch-models --save     # fetch and persist models to database
 *  php artisan ai:fetch-models --config   # only use AI service system (no direct HTTP fallback)
 *  php artisan ai:fetch-models --db       # only use direct HTTP requests to provider URLs
 *  php artisan ai:fetch-models Ollama --db --save  # fetch Ollama via direct HTTP and save
 */
class AiFetchModelsCommand extends Command
{
    use AiConnectionTrait;
    
    protected $signature = 'ai:fetch-models {provider?} {--save : Save models to database} {--dry-run : Show what would be done without making changes} {--config : Only use AI service system (no direct HTTP)} {--db : Only use direct HTTP requests to provider URLs}';

    protected $description = 'Fetch available models from AI providers via AI service (--config) or direct HTTP (--db)';

    public function __construct(private readonly AiService $aiService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $providerArg = $this->argument('provider');
        $save = (bool) $this->option('save');
        $dryRun = (bool) $this->option('dry-run');
        $configOnly = (bool) $this->option('config');
        $dbOnly = (bool) $this->option('db');

        // Validate flag combinations
        if ($configOnly && $dbOnly) {
            $this->error('Cannot use both --config and --db flags simultaneously. Choose one approach.');
            return Command::FAILURE;
        }

        // Get models based on selected approach
        if ($dbOnly) {
            return $this->handleDbOnlyMode($providerArg, $save, $dryRun);
        }

        $available = $this->aiService->getAvailableModels();

        if ($providerArg) {
            $provider = ApiProvider::where('provider_name', $providerArg)->first();
            if (! $provider) {
                $this->error("Provider '{$providerArg}' not found.");
                return Command::FAILURE;
            }

            $this->info("Fetching models for provider: {$provider->provider_name}");

            // Try to find models via AI Service first
            $models = array_filter($available->models->toArray(), function ($m) use ($provider) {
                // AiModel has getProvider bound; compare provider config id if available
                if (is_object($m) && method_exists($m, 'getProvider')) {
                    try {
                        return $m->getProvider()->getConfig()->getId() === $provider->provider_name || $m->getProvider()->getConfig()->getId() === (string)$provider->id;
                    } catch (\Throwable $e) {
                        return false;
                    }
                }

                // Fallback: try array form
                $providerId = $m['provider'] ?? $m['provider_id'] ?? null;
                return $providerId === $provider->provider_name || $providerId === (string)$provider->id;
            });

            // If no models found via AI Service and not config-only mode, try direct HTTP request
            if (empty($models) && !$configOnly) {
                $this->warn('No models found via AI Service, trying direct HTTP request...');
                try {
                    $fetchResult = $this->testAndFetchModels($provider, false);
                    $models = $fetchResult['success'] ? $fetchResult['models'] : [];
                } catch (\Exception $e) {
                    $this->error('Direct HTTP fetch failed: ' . $e->getMessage());
                    $models = [];
                }
            }

            $this->displayModels($models);

            if ($save) {
                if ($dryRun) {
                    $this->warn('Dry-run enabled: no models will be persisted.');
                } else {
                    $this->saveModelsForProvider($provider, $models);
                }
            }

            return Command::SUCCESS;
        }

        $this->info('Fetching models for all active providers (from AiService)');

        $grouped = [];
        foreach ($available->models as $model) {
            try {
                $providerId = $model->getProvider()->getConfig()->getId();
            } catch (\Throwable $e) {
                $providerId = 'unknown';
            }

            $grouped[$providerId][] = $model;
        }

        foreach ($grouped as $prov => $models) {
            $this->line("\nProvider: {$prov}");
            $this->displayModels($models);
            
            if ($save && !$dryRun) {
                // Find provider by name or ID
                $dbProvider = ApiProvider::where('provider_name', $prov)
                    ->orWhere('id', $prov)
                    ->first();
                
                if ($dbProvider) {
                    $this->saveModelsForProvider($dbProvider, $models);
                } else {
                    $this->warn("Could not find database provider for '{$prov}' - skipping save.");
                }
            }
        }

        if ($save && $dryRun) {
            $this->warn('Dry-run enabled: no models will be persisted.');
        }

        $this->info('\nDone.');

        return Command::SUCCESS;
    }

    private function displayModels($models): void
    {
        if (empty($models) || count($models) === 0) {
            $this->line('  (no models returned)');
            return;
        }

        foreach ($models as $model) {
            // Handle different model formats
            if (is_object($model) && method_exists($model, 'getId')) {
                // AI Service model object
                $id = $model->getId();
                $desc = method_exists($model, 'getDescription') ? $model->getDescription() : '';
            } elseif (is_array($model)) {
                // Array format (including normalized direct HTTP models)
                $id = $model['id'] ?? $model['name'] ?? 'n/a';
                $desc = $model['description'] ?? '';
            } else {
                // Fallback for other formats
                $id = (string) $model;
                $desc = '';
            }
            
            $this->line('  - ' . $id . ($desc ? " — {$desc}" : ''));
        }
    }

    private function saveModelsForProvider(ApiProvider $provider, array $models): void
    {
        $this->info("Persisting models for provider: {$provider->provider_name}");
        
        $order = 1;
        foreach ($models as $model) {
            // Extract model data from AI service objects or arrays
            if (is_object($model) && method_exists($model, 'getId')) {
                // AI Service model object
                $modelId = $model->getId();
                $label = method_exists($model, 'getName') ? $model->getName() : $modelId;
                $description = method_exists($model, 'getDescription') ? $model->getDescription() : '';
                $information = [
                    'description' => $description,
                    'source' => 'ai_service'
                ];
            } elseif (is_array($model) && isset($model['source']) && $model['source'] === 'direct_http') {
                // Direct HTTP normalized model
                $modelId = $model['id'];
                $label = $model['name'] ?? $modelId;
                $description = $model['description'] ?? '';
                $information = [
                    'description' => $description,
                    'source' => 'direct_http',
                    'raw_data' => $model['raw_data'] ?? []
                ];
            } else {
                // Fallback for other array/object data
                $modelId = is_array($model) ? ($model['id'] ?? $model['name'] ?? null) : 
                          (is_object($model) ? ($model->id ?? $model->name ?? null) : 
                          (string) $model);
                $label = is_array($model) ? ($model['label'] ?? $model['name'] ?? $modelId) : 
                        (is_object($model) ? ($model->label ?? $model->name ?? $modelId) : 
                        $modelId);
                $information = is_array($model) ? $model : 
                              (is_object($model) ? json_decode(json_encode($model), true) : []);
            }

            if (!$modelId) {
                continue;
            }

            AiModel::updateOrCreate(
                ['provider_id' => $provider->id, 'model_id' => (string) $modelId],
                [
                    'label' => $label,
                    'is_active' => true,
                    'display_order' => $order++,
                    'information' => $information,
                ]
            );
        }
        
        $this->info("Saved " . count($models) . " models for {$provider->provider_name}");
    }



    private function handleDbOnlyMode(?string $providerArg, bool $save, bool $dryRun): int
    {
        if ($providerArg) {
            // Single provider via direct HTTP
            $provider = ApiProvider::where('provider_name', $providerArg)->first();
            if (!$provider) {
                $this->error("Provider '{$providerArg}' not found.");
                return Command::FAILURE;
            }

            $this->info("Fetching models for provider: {$provider->provider_name} (direct HTTP only)");
            try {
                $fetchResult = $this->testAndFetchModels($provider, false);
                $models = $fetchResult['success'] ? $fetchResult['models'] : [];
                if (!$fetchResult['success']) {
                    $this->error('Fetch failed: ' . $fetchResult['error']);
                }
            } catch (\Exception $e) {
                $this->error('Fetch failed: ' . $e->getMessage());
                $models = [];
            }
            $this->displayModels($models);

            if ($save) {
                if ($dryRun) {
                    $this->warn('Dry-run enabled: no models will be persisted.');
                } else {
                    $this->saveModelsForProvider($provider, $models);
                }
            }

            return Command::SUCCESS;
        }

        // All providers via direct HTTP
        $this->info('Fetching models for all active providers (direct HTTP only)');
        
        $providers = ApiProvider::where('is_active', true)->get();
        
        foreach ($providers as $provider) {
            $this->line("\nProvider: {$provider->provider_name}");
            try {
                $fetchResult = $this->testAndFetchModels($provider, false);
                $models = $fetchResult['success'] ? $fetchResult['models'] : [];
                if (!$fetchResult['success']) {
                    $this->error('Fetch failed: ' . $fetchResult['error']);
                }
            } catch (\Exception $e) {
                $this->error('Fetch failed: ' . $e->getMessage());
                $models = [];
            }
            $this->displayModels($models);
            
            if ($save && !$dryRun && !empty($models)) {
                $this->saveModelsForProvider($provider, $models);
            }
        }

        if ($save && $dryRun) {
            $this->warn('Dry-run enabled: no models will be persisted.');
        }

        $this->info('\nDone.');
        return Command::SUCCESS;
    }
}
