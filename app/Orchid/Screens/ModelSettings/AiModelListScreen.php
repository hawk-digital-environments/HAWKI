<?php

declare(strict_types=1);

namespace App\Orchid\Screens\ModelSettings;

use App\Models\AiModel;
use App\Models\ApiProvider;
use App\Orchid\Layouts\ModelSettings\AiModelFiltersLayout;
use App\Orchid\Layouts\ModelSettings\AiModelListLayout;
use App\Orchid\Layouts\ModelSettings\AiModelTabMenu;
use App\Orchid\Traits\AiConnectionTrait;
use App\Orchid\Traits\OrchidLoggingTrait;
use App\Orchid\Traits\OrchidSettingsManagementTrait;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class AiModelListScreen extends Screen
{
    use AiConnectionTrait, OrchidLoggingTrait, OrchidSettingsManagementTrait {
        OrchidLoggingTrait::logBatchOperation insteadof OrchidSettingsManagementTrait;
    }

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        return [
            'models' => AiModel::with(['provider', 'provider.apiFormat'])
                ->leftJoin('api_providers', 'ai_models.provider_id', '=', 'api_providers.id')
                ->select('ai_models.*', 'api_providers.provider_name')
                ->whereHas('provider', function ($query) {
                    $query->where('api_providers.is_active', true);
                })
                ->filters(AiModelFiltersLayout::class)
                ->defaultSort('ai_models.is_active', 'desc')
                ->paginate(50),
        ];
    }

    /**
     * The name of the screen displayed in the header.
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

            Button::make('Clear')
                ->icon('bs.trash')
                ->method('clearAllModels')
                ->confirm('This will permanently delete ALL language models from the database. This action cannot be undone. Are you sure?')
                ->class('btn btn-outline-danger ms-2'),
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
            AiModelTabMenu::class,
            AiModelFiltersLayout::class,
            AiModelListLayout::class,
        ];
    }

    /**
     * Toggle the active status of a model.
     */
    public function toggleActive(Request $request): void
    {
        try {
            $model = AiModel::findOrFail($request->get('id'));
            $originalStatus = $model->is_active;

            // Use trait method for model save with change detection
            $this->saveModelWithChangeDetection(
                $model,
                ['is_active' => ! $model->is_active],
                "Model '{$model->label}' active status",
                ['is_active' => $originalStatus],
                null,
                function ($savedModel) use ($originalStatus) {
                    // After-save callback with structured logging
                    $this->logModelOperation(
                        'toggle_active',
                        'AiModel',
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
                'AiModel',
                $request->get('id'),
                'error',
                ['error' => $e->getMessage()]
            );

            Toast::error('Error toggling model status: '.$e->getMessage());
        }
    }

    /**
     * Toggle the visibility status of a model.
     */
    public function toggleVisible(Request $request): void
    {
        try {
            $model = AiModel::findOrFail($request->get('id'));
            $originalVisibility = $model->is_visible;

            // Use trait method for model save with change detection
            $this->saveModelWithChangeDetection(
                $model,
                ['is_visible' => ! $model->is_visible],
                "Model '{$model->label}' visibility",
                ['is_visible' => $originalVisibility],
                null,
                function ($savedModel) use ($originalVisibility) {
                    // After-save callback with structured logging
                    $this->logModelOperation(
                        'toggle_visible',
                        'AiModel',
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
                'AiModel',
                $request->get('id'),
                'error',
                ['error' => $e->getMessage()]
            );

            Toast::error('Error toggling model visibility: '.$e->getMessage());
        }
    }

    /**
     * Toggle a specific capability for a model.
     */
    public function toggleCapability(Request $request): void
    {
        try {
            $model = AiModel::findOrFail($request->get('id'));
            $capability = $request->get('capability');
            
            // Validate capability
            $validCapabilities = ['file_upload', 'vision', 'web_search', 'reasoning'];
            if (!in_array($capability, $validCapabilities)) {
                Toast::error('Invalid capability specified.');
                return;
            }

            // Get current settings
            $settings = $model->settings ?? [];
            $tools = $settings['tools'] ?? [];
            
            // Get current status
            $currentStatus = !empty($tools[$capability]);
            $newStatus = !$currentStatus;
            
            // Toggle the capability
            $tools[$capability] = $newStatus;
            $settings['tools'] = $tools;

            // Use trait method for model save with change detection
            $this->saveModelWithChangeDetection(
                $model,
                ['settings' => $settings],
                "Model '{$model->label}' capability '{$capability}'",
                ['settings' => $model->settings],
                null,
                function ($savedModel) use ($capability, $currentStatus, $newStatus) {
                    // After-save callback with structured logging
                    $this->logModelOperation(
                        'toggle_capability',
                        'AiModel',
                        $savedModel->id,
                        'success',
                        [
                            'model_label' => $savedModel->label,
                            'provider_id' => $savedModel->provider_id,
                            'capability' => $capability,
                            'status_change' => ['from' => $currentStatus, 'to' => $newStatus],
                        ]
                    );
                }
            );

        } catch (\Exception $e) {
            $this->logModelOperation(
                'toggle_capability',
                'AiModel',
                $request->get('id'),
                'error',
                [
                    'capability' => $request->get('capability'),
                    'error' => $e->getMessage()
                ]
            );

            Toast::error('Error toggling model capability: '.$e->getMessage());
        }
    }

    /**
     * Delete a model.
     */
    public function deleteModel(Request $request)
    {
        try {
            $model = AiModel::findOrFail($request->get('id'));
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
                'AiModel',
                $modelData['model_id'],
                'success',
                $modelData
            );

            Toast::success("Model '{$modelData['model_label']}' has been deleted successfully.");

        } catch (\Exception $e) {
            $this->logModelOperation(
                'delete',
                'AiModel',
                $request->get('id'),
                'error',
                ['error' => $e->getMessage()]
            );

            Toast::error('Error deleting model: '.$e->getMessage());
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
            // Get all active providers - simplified approach like Artisan command
            $allActiveProviders = ApiProvider::where('is_active', true)->get();

            $refreshResults['all_active_providers'] = $allActiveProviders->map(function ($p) {
                return [
                    'name' => $p->provider_name,
                    'id' => $p->id,
                    'base_url' => $p->base_url,
                    'ping_url' => $p->ping_url,
                ];
            })->toArray();

            // Use all active providers - let AiConnectionTrait handle the endpoint logic
            $validProviders = $allActiveProviders->filter(function ($provider) {
                // Basic validation: provider must have either base_url or ping_url
                return !empty($provider->base_url) || !empty($provider->ping_url);
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
                Toast::warning('No active providers with valid URLs found to refresh models from.');

                return redirect()->back();
            }

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

                    // Use AiConnectionTrait to fetch and save models
                    $result = $this->testAndFetchModels($provider, true);

                    $providerEndTime = microtime(true);
                    $providerDuration = round(($providerEndTime - $providerStartTime) * 1000, 2);

                    if ($result['success']) {
                        // Extract results from AiConnectionTrait
                        $modelsFound = $result['models_count'];
                        $modelsCreated = $result['save_result']['created'] ?? 0;
                        $modelsUpdated = $result['save_result']['updated'] ?? 0;
                        $modelsSkipped = $result['save_result']['skipped'] ?? 0;

                        // Models processing is handled by AiConnectionTrait

                        $providerResult = array_merge($providerResult, [
                            'status' => 'success',
                            'models_found' => $modelsFound,
                            'models_created' => $modelsCreated,
                            'models_updated' => $modelsUpdated,
                            'models_skipped' => $modelsSkipped,
                            'response_time_ms' => $providerDuration,
                        ]);

                        $refreshResults['total_models_found'] += $modelsFound;
                        $refreshResults['total_models_created'] += $modelsCreated;
                        $refreshResults['total_models_updated'] += $modelsUpdated;
                        $refreshResults['successful_providers']++;
                    } else {
                        // Failed to fetch models
                        $providerResult = array_merge($providerResult, [
                            'status' => 'error',
                            'error' => $result['error'] ?? 'Unknown error',
                            'response_time_ms' => $providerDuration,
                        ]);

                        $refreshResults['errors'][] = "Provider '{$provider->provider_name}': " . ($result['error'] ?? 'Unknown error');
                        $refreshResults['failed_providers']++;
                    }

                } catch (\Exception $e) {
                    $providerResult = array_merge($providerResult, [
                        'status' => 'error',
                        'error' => $e->getMessage(),
                        'response_time_ms' => $providerDuration ?? 0,
                    ]);

                    $refreshResults['errors'][] = "Provider '{$provider->provider_name}': ".$e->getMessage();
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
                    Toast::warning($message.' Some providers failed - check logs for details.');
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

            Toast::error('Failed to refresh models: '.$e->getMessage());
        }

        return redirect()->back();
    }

    /**
     * Set a model as default for all AI assistants.
     */
    public function setAsDefaultForAssistants(Request $request)
    {
        try {
            $model = AiModel::findOrFail($request->get('id'));
            
            // Check if model is active
            if (!$model->is_active) {
                Toast::warning("Cannot set inactive model '{$model->label}' as default. Please activate it first.");
                return redirect()->back();
            }

            // Count assistants that will be updated
            $totalAssistants = \App\Models\AiAssistant::count();
            
            if ($totalAssistants === 0) {
                Toast::info('No AI assistants found to update.');
                return redirect()->back();
            }

            // Update all assistants to use this model's system_id
            $updatedCount = \App\Models\AiAssistant::query()
                ->update(['ai_model' => $model->system_id]);

            // Log the operation
            $this->logScreenOperation(
                'set_default_model_for_assistants',
                'completed',
                [
                    'model_id' => $model->id,
                    'model_label' => $model->label,
                    'system_id' => $model->system_id,
                    'provider_name' => $model->provider->provider_name ?? 'Unknown',
                    'total_assistants' => $totalAssistants,
                    'updated_count' => $updatedCount,
                    'initiated_by' => auth()->id(),
                ]
            );

            Toast::success("Successfully set '{$model->label}' as default model for {$updatedCount} AI assistants.");

        } catch (\Exception $e) {
            // Log the error
            $this->logScreenOperation(
                'set_default_model_for_assistants',
                'error',
                [
                    'model_id' => $request->get('id'),
                    'error' => $e->getMessage(),
                    'initiated_by' => auth()->id(),
                ],
                'error'
            );

            Toast::error('Failed to set default model: '.$e->getMessage());
        }

        return redirect()->back();
    }

    /**
     * Clear all language models from the database.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function clearAllModels(Request $request)
    {
        return $this->clearAllModelsOfType(
            AiModel::class,
            'language models'
        );
    }

    /**
     * Clear all models of a specific type from the database.
     *
     * @param string $modelClass
     * @param string $modelTypeName
     * @return \Illuminate\Http\RedirectResponse
     */
    private function clearAllModelsOfType(string $modelClass, string $modelTypeName)
    {
        $startTime = microtime(true);

        try {
            // Count models before deletion
            $totalModels = $modelClass::count();

            if ($totalModels === 0) {
                Toast::info("No {$modelTypeName} found to delete.");
                return redirect()->back();
            }

            // Delete all models
            $deletedCount = $modelClass::query()->delete();

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            // Log the operation
            $this->logScreenOperation(
                'clear_all_models',
                'completed',
                [
                    'model_type' => $modelClass,
                    'model_type_name' => $modelTypeName,
                    'total_models' => $totalModels,
                    'deleted_count' => $deletedCount,
                    'duration_ms' => $duration,
                    'initiated_by' => auth()->id(),
                ]
            );

            Toast::success("Successfully deleted {$deletedCount} {$modelTypeName}. (Duration: {$duration}ms)");

        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            // Log the error
            $this->logScreenOperation(
                'clear_all_models',
                'error',
                [
                    'model_type' => $modelClass,
                    'model_type_name' => $modelTypeName,
                    'error' => $e->getMessage(),
                    'duration_ms' => $duration,
                    'initiated_by' => auth()->id(),
                ],
                'error'
            );

            Toast::error("Failed to delete {$modelTypeName}: {$e->getMessage()}");
        }

        return redirect()->back();
    }
}
