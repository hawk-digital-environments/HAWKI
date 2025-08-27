<?php

declare(strict_types=1);

namespace App\Orchid\Screens\ModelSettings;

use App\Models\LanguageModel;
use App\Orchid\Layouts\ModelSettings\LanguageModelListLayout;
use App\Orchid\Layouts\ModelSettings\LanguageModelFiltersLayout;
use App\Orchid\Layouts\ModelSettings\LanguageModelTabMenu;
use App\Orchid\Traits\OrchidLoggingTrait;
use App\Orchid\Traits\OrchidSettingsManagementTrait;
use App\Services\Settings\ModelSettingsService;
use App\Models\ProviderSetting;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class LanguageModelListScreen extends Screen
{
    use OrchidLoggingTrait, OrchidSettingsManagementTrait;

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        return [
            'models' => LanguageModel::with(['provider', 'provider.apiFormat'])
                ->whereHas('provider', function ($query) {
                    $query->where('is_active', true);
                })
                ->filters(LanguageModelFiltersLayout::class)
                ->defaultSort('label')
                ->paginate(50)
        ];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'Language Models';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Manage and configure language models from all providers.';
    }

    /**
     * Permission required to access this screen.
     */
    public function permission(): ?iterable
    {
        return [
            'platform.modelsettings.models',
        ];
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Button::make('Refresh Models')
                ->icon('bs.arrow-clockwise')
                ->method('refreshModels')
                ->confirm('This will contact all active providers to check for new models. Continue?'),
        ];
    }

    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        return [
            LanguageModelTabMenu::class,
            LanguageModelFiltersLayout::class,
            LanguageModelListLayout::class,
        ];
    }

    /**
     * Toggle the active status of a model.
     */
    public function toggleActive(Request $request): void
    {
        try {
            $model = LanguageModel::findOrFail($request->get('id'));
            $originalStatus = $model->is_active;
            $model->is_active = !$model->is_active;
            
            // Use trait method for model save with change detection
            $this->saveModelWithChangeDetection(
                $model,
                $request,
                "Model '{$model->label}' active status has been " . ($model->is_active ? 'activated' : 'deactivated') . ".",
                function($savedModel) use ($originalStatus) {
                    // After-save callback with structured logging
                    $this->logModelOperation(
                        'toggle_active',
                        'LanguageModel',
                        $savedModel->id,
                        'success',
                        [
                            'model_label' => $savedModel->label,
                            'provider_id' => $savedModel->provider_id,
                            'status_change' => ['from' => $originalStatus, 'to' => $savedModel->is_active],
                        ]
                    );
                }
            );
            
        } catch (\Exception $e) {
            $this->logModelOperation(
                'toggle_active',
                'LanguageModel',
                $request->get('id'),
                'error',
                ['error' => $e->getMessage()]
            );
            
            Toast::error('Error toggling model status: ' . $e->getMessage());
        }
    }

    /**
     * Toggle the visibility status of a model.
     */
    public function toggleVisible(Request $request): void
    {
        try {
            $model = LanguageModel::findOrFail($request->get('id'));
            $originalVisibility = $model->is_visible;
            $model->is_visible = !$model->is_visible;
            
            // Use trait method for model save with change detection
            $this->saveModelWithChangeDetection(
                $model,
                $request,
                "Model '{$model->label}' has been " . ($model->is_visible ? 'made visible' : 'hidden') . ".",
                function($savedModel) use ($originalVisibility) {
                    // After-save callback with structured logging
                    $this->logModelOperation(
                        'toggle_visible',
                        'LanguageModel',
                        $savedModel->id,
                        'success',
                        [
                            'model_label' => $savedModel->label,
                            'provider_id' => $savedModel->provider_id,
                            'visibility_change' => ['from' => $originalVisibility, 'to' => $savedModel->is_visible],
                        ]
                    );
                }
            );
            
        } catch (\Exception $e) {
            $this->logModelOperation(
                'toggle_visible',
                'LanguageModel',
                $request->get('id'),
                'error',
                ['error' => $e->getMessage()]
            );
            
            Toast::error('Error toggling model visibility: ' . $e->getMessage());
        }
    }

    /**
     * Delete a model.
     */
    public function deleteModel(Request $request)
    {
        try {
            $model = LanguageModel::findOrFail($request->get('id'));
            $modelData = [
                'model_id' => $model->id,
                'model_label' => $model->label,
                'provider_id' => $model->provider_id,
                'model_identifier' => $model->model_id,
            ];
            
            $model->delete();
            
            // Use trait method for structured logging
            $this->logModelOperation(
                'delete',
                'LanguageModel',
                $modelData['model_id'],
                'success',
                $modelData
            );
            
            Toast::success("Model '{$modelData['model_label']}' has been deleted successfully.");
            
        } catch (\Exception $e) {
            $this->logModelOperation(
                'delete',
                'LanguageModel',
                $request->get('id'),
                'error',
                ['error' => $e->getMessage()]
            );
            
            Toast::error('Error deleting model: ' . $e->getMessage());
        }
        
        return redirect()->back();
    }

    /**
     * Refresh models from all providers.
     */
    public function refreshModels(Request $request)
    {
        $startTime = microtime(true);
        $refreshResults = [
            'total_providers' => 0,
            'successful_providers' => 0,
            'failed_providers' => 0,
            'total_models_found' => 0,
            'total_models_created' => 0,
            'total_models_updated' => 0,
            'providers_processed' => [],
            'errors' => [],
            'initiated_by' => auth()->id(),
        ];

        try {
            // Get all active providers with their API formats
            $allActiveProviders = ProviderSetting::with('apiFormat.endpoints')
                ->where('is_active', true)
                ->get();

            $refreshResults['all_active_providers'] = $allActiveProviders->map(function($p) {
                return [
                    'name' => $p->provider_name,
                    'id' => $p->id,
                    'api_format' => $p->apiFormat?->unique_name,
                    'ping_url' => $p->ping_url,
                ];
            })->toArray();

            // Filter providers that have valid models endpoints
            $validProviders = $allActiveProviders->filter(function($provider) {
                if (!$provider->apiFormat) {
                    return false;
                }
                
                $modelsEndpoint = $provider->apiFormat->getModelsEndpoint();
                $pingUrl = $provider->ping_url;
                
                return !empty($pingUrl) && $modelsEndpoint && $modelsEndpoint->is_active;
            });

            $refreshResults['total_providers'] = $validProviders->count();

            if ($validProviders->isEmpty()) {
                $refreshResults['result'] = 'no_valid_providers';
                $this->logScreenOperation(
                    'refresh_models',
                    'warning',
                    $refreshResults,
                    'warning'
                );
                Toast::warning('No active providers with models endpoints found to refresh models from.');
                return redirect()->back();
            }

            $modelSettingsService = app(ModelSettingsService::class);

            // Process each valid provider
            foreach ($validProviders as $provider) {
                $providerResult = [
                    'provider_id' => $provider->id,
                    'provider_name' => $provider->provider_name,
                    'api_format' => $provider->apiFormat->unique_name,
                    'ping_url' => $provider->ping_url,
                ];

                try {
                    $providerStartTime = microtime(true);
                    
                    // Fetch models from provider API
                    $modelsData = $modelSettingsService->getModelStatus($provider->provider_name);
                    
                    $providerEndTime = microtime(true);
                    $providerDuration = round(($providerEndTime - $providerStartTime) * 1000, 2);

                    // Process and save models
                    $modelsCreated = 0;
                    $modelsUpdated = 0;
                    $modelsFound = 0;
                    $apiResponseStructure = [];
                    
                    // Debug: Log the API response structure
                    if (is_array($modelsData)) {
                        $apiResponseStructure = [
                            'keys' => array_keys($modelsData),
                            'has_models_key' => isset($modelsData['models']),
                            'has_data_key' => isset($modelsData['data']),
                            'total_keys' => count($modelsData),
                            'sample_data' => array_slice($modelsData, 0, 2, true)
                        ];
                    }
                    
                    // Handle different API response formats
                    $modelsList = [];
                    if (isset($modelsData['models']) && is_array($modelsData['models'])) {
                        // Format: { "models": [...] } - Ollama style
                        $modelsList = $modelsData['models'];
                    } elseif (isset($modelsData['data']) && is_array($modelsData['data'])) {
                        // Format: { "data": [...] } - OpenAI style
                        $modelsList = $modelsData['data'];
                    } elseif (is_array($modelsData) && !empty($modelsData)) {
                        // Check if it's a direct array of models
                        $firstItem = reset($modelsData);
                        if (is_array($firstItem) && (isset($firstItem['id']) || isset($firstItem['name']))) {
                            $modelsList = $modelsData;
                        }
                    }
                    
                    if (!empty($modelsList)) {
                        $modelsFound = count($modelsList);
                        
                        foreach ($modelsList as $modelData) {
                            $modelId = $modelData['id'] ?? $modelData['name'] ?? null;
                            $modelLabel = $modelData['name'] ?? $modelData['id'] ?? 'Unknown Model';
                            
                            if (!$modelId) {
                                continue; // Skip models without valid ID
                            }

                            // Check if model already exists for this provider
                            $existingModel = LanguageModel::where('model_id', $modelId)
                                ->where('provider_id', $provider->id)
                                ->first();

                            $modelAttributes = [
                                'model_id' => $modelId,
                                'label' => $modelLabel,
                                'provider_id' => $provider->id,
                                'is_active' => false,
                                'streamable' => true,
                                'is_visible' => false,
                                'display_order' => 0,
                                'information' => $modelData,
                                'settings' => [],
                            ];

                            if ($existingModel) {
                                // Update existing model
                                $existingModel->update([
                                    'label' => $modelLabel,
                                    'information' => $modelData,
                                    'updated_at' => now(),
                                ]);
                                $modelsUpdated++;
                            } else {
                                // Create new model
                                LanguageModel::create($modelAttributes);
                                $modelsCreated++;
                            }
                        }
                    }

                    $providerResult = array_merge($providerResult, [
                        'status' => 'success',
                        'models_found' => $modelsFound,
                        'models_created' => $modelsCreated,
                        'models_updated' => $modelsUpdated,
                        'response_time_ms' => $providerDuration,
                        'api_response_structure' => $apiResponseStructure,
                    ]);

                    $refreshResults['total_models_found'] += $modelsFound;
                    $refreshResults['total_models_created'] += $modelsCreated;
                    $refreshResults['total_models_updated'] += $modelsUpdated;
                    $refreshResults['successful_providers']++;

                } catch (\Exception $e) {
                    $providerResult = array_merge($providerResult, [
                        'status' => 'error',
                        'error' => $e->getMessage()
                    ]);
                    
                    $refreshResults['errors'][] = "Provider '{$provider->provider_name}': " . $e->getMessage();
                    $refreshResults['failed_providers']++;
                }

                $refreshResults['providers_processed'][] = $providerResult;
            }

            $endTime = microtime(true);
            $refreshResults['total_duration_ms'] = round(($endTime - $startTime) * 1000, 2);
            $refreshResults['result'] = 'completed';

            // Single consolidated log entry using trait method
            $this->logScreenOperation(
                'refresh_models',
                'completed',
                $refreshResults
            );

            // User feedback
            if ($refreshResults['successful_providers'] > 0) {
                $message = "Successfully refreshed models from {$refreshResults['successful_providers']} providers. ";
                $message .= "Found {$refreshResults['total_models_found']} models, ";
                $message .= "created {$refreshResults['total_models_created']}, ";
                $message .= "updated {$refreshResults['total_models_updated']}. ";
                $message .= "(Duration: {$refreshResults['total_duration_ms']}ms)";
                
                if ($refreshResults['failed_providers'] === 0) {
                    Toast::success($message);
                } else {
                    Toast::warning($message . " Some providers failed - check logs for details.");
                }
            } else {
                Toast::error('Failed to refresh models from any provider. Check logs for details.');
            }

        } catch (\Exception $e) {
            $refreshResults['result'] = 'fatal_error';
            $refreshResults['fatal_error'] = $e->getMessage();
            $refreshResults['total_duration_ms'] = round((microtime(true) - $startTime) * 1000, 2);
            
            // Use trait method for error logging
            $this->logScreenOperation(
                'refresh_models',
                'fatal_error',
                $refreshResults,
                'error'
            );
            
            Toast::error('Failed to refresh models: ' . $e->getMessage());
        }

        return redirect()->back();
    }
}
