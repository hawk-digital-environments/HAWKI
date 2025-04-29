<?php

namespace App\Orchid\Screens\ModelSettings;

use App\Models\LanguageModel;
use App\Models\ProviderSetting;
//use App\Services\AI\ModelUtilityService;
use App\Services\Settings\ModelSettingsService;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Fields\Group;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\Switcher;
use Orchid\Screen\Screen;
use Orchid\Screen\TD;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;
use Illuminate\Support\Facades\Log;


class ModelSettingsScreen extends Screen
{
    /**
     * @var modelSettingsService
     */
    private $modelSettingsService;

    /**
     * Construct the controller.
     *
     * @param ModelSettingsService $ModelSettingsService
     */
    public function __construct(
        ModelSettingsService $modelSettingsService
    ) {
        $this->modelSettingsService = $modelSettingsService;
    }

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        // Get all models and providers
        $models = LanguageModel::with('provider')->orderBy('display_order')->get();
        $providers = ProviderSetting::where('is_active', true)->get();
        
        // Group models by provider
        $modelsByProvider = $models->groupBy('provider_id');
        
        return [
            'models' => $models,
            'modelsByProvider' => $modelsByProvider,
            'providers' => $providers,
            'hasModels' => $models->isNotEmpty(),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'LLM Models Configuration';
    }
    /**
     * Display header description.
     */
     public function description(): ?string
    {
        return 'Set up and update the available language models.';
    }
    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [      
            Button::make('Get Available Models')
                ->icon('cloud-download')
                ->method('checkAvailableModels')
                ->confirm('This will contact each provider API to check for available models. This might take some time. Continue?'),
                
            Button::make('Save')
                ->icon('save')
                ->method('saveModelChanges'),
        ];
    }

    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        $providers = $this->query()['providers'];

        if ($providers->isEmpty()) {
            return [
                Layout::view('orchid.model-settings.no-providers'),
            ];
        }
        
        $modelsByProvider = $this->query()['modelsByProvider'];
        
        // Create tabs for each provider
        $tabs = [];
        
        foreach ($providers as $provider) {
            $tabTitle = $provider->provider_name;
            $hasModels = isset($modelsByProvider[$provider->id]) && !$modelsByProvider[$provider->id]->isEmpty();
            
            if ($hasModels) {
                // Provider has models, show table
                $tabs[$tabTitle] = [
                    Layout::table('modelsByProvider.'.$provider->id, [
                        TD::make('model_id', 'Model ID')
                            ->sort()
                            ->render(function (LanguageModel $model) {
                                return $model->model_id;
                            }),
                        
                        TD::make('label', 'Display Name')
                            ->sort()
                            ->render(function (LanguageModel $model) {
                                return Input::make("models[{$model->id}][label]")
                                    ->value($model->label)
                                    ->required();
                            }),
                        
                        TD::make('status', 'Settings')
                            ->render(function (LanguageModel $model) {
                                return Group::make([
                                    Switcher::make("models[{$model->id}][is_active]")
                                        ->sendTrueOrFalse()
                                        ->value($model->is_active)
                                        ->title('Active'),
                                        
                                    Switcher::make("models[{$model->id}][is_visible]")
                                        ->sendTrueOrFalse()
                                        ->value($model->is_visible)
                                        ->title('Visible'),
                                    
                                    //Do we really need this parameter?
                                    //Switcher::make("models[{$model->id}][streamable]")
                                    //    ->sendTrueOrFalse()
                                    //    ->value($model->streamable)
                                    //    ->title('Streamable'),
                                ]);
                            }),
                        
                        TD::make('actions', 'Actions')
                            ->render(function (LanguageModel $model) {
                                $modelId = $model->id;
                                
                                return DropDown::make()
                                    ->icon('bs.three-dots')
                                    ->list([
                                        Link::make('Information')
                                            ->route('platform.modelsettings.models.info', $modelId)
                                            ->icon('bs.info'),
                                            
                                        Link::make('Edit Settings')
                                            ->route('platform.modelsettings.models.settings', $modelId)
                                            ->icon('bs.gear'),
                                            
                                        Button::make('Delete')
                                            ->icon('trash')
                                            ->confirm("Are you sure you want to delete model '{$model->label}'?")
                                            ->method('deleteModel', ['id' => $modelId]),    
                                    ]);
                            }),
                    ]),
                    
                    Layout::rows([
                      Group::make([
                        Button::make('Check Models for ' . $provider->provider_name)
                            ->method('checkProviderModels', ['provider_id' => $provider->id])
                            ->icon('cloud-download'),
                            
                        Button::make('Delete All Models for ' . $provider->provider_name)
                            ->method('deleteAllModelsForProvider', ['provider_id' => $provider->id])
                            ->icon('trash')
                            ->confirm('Are you sure you want to delete ALL models for provider "' . $provider->provider_name . '"? This action cannot be undone.'),
                    
                      ])->widthColumns('max-content max-content'),
                    ])->title('Provider Actions'),
                ];
            } else {
                // Provider has no models, show empty state
                $tabs[$tabTitle] = [
                    Layout::view('orchid.model-settings.no-models-for-provider', [
                        'provider' => $provider
                    ]),
                    
                    Layout::rows([
                        Button::make('Fetch Models for ' . $provider->provider_name)
                            ->method('checkProviderModels', ['provider_id' => $provider->id])
                            ->icon('cloud-download'),
                    ])->title('Provider Actions'),
                ];
            }
        }
        
        $layouts = [];
        
        // Add the tabbed view
        if (!empty($tabs)) {
            $layouts[] = Layout::tabs($tabs);
        }
        
        // Add the general information view at the end
        $layouts[] = Layout::view('orchid.model-settings.info');
        
        return $layouts;
    }
    
    
    /**
     * Save model changes.
     *
     * @param Request $request
     * @return
     */
    public function saveModelChanges(Request $request)
    {
        $modelData = $request->get('models', []);
        
        foreach ($modelData as $id => $data) {
            $model = LanguageModel::find($id);
            if ($model) {
                // Handle JSON fields
                if (isset($data['information'])) {
                    try {
                        $data['information'] = json_decode($data['information'], true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            Toast::warning("Invalid JSON format in information field for model ID {$id}. Using default value.");
                            $data['information'] = null;
                        }
                    } catch (\Exception $e) {
                        $data['information'] = null;
                    }
                }
                
                if (isset($data['settings'])) {
                    try {
                        $data['settings'] = json_decode($data['settings'], true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            Toast::warning("Invalid JSON format in settings field for model ID {$id}. Using default value.");
                            $data['settings'] = null;
                        }
                    } catch (\Exception $e) {
                        $data['settings'] = null;
                    }
                }
                
                $model->update($data);
            }
        }
        
        Toast::success('Model settings saved successfully.');
        return redirect()->route('platform.modelsettings.models');
    }
    
    
    /**
     * Check available models for all providers.
     *
     * @return
     */
    public function checkAvailableModels()
    {
        // Query only active providers
        $providers = ProviderSetting::where('is_active', true)->get();
        
        if ($providers->isEmpty()) {
            Toast::warning('No active providers found. Please configure providers first.');
            return redirect()->route('platform.modelsettings.models');
        }
        
        $totalFound = 0;
        $errors = [];
        
        foreach ($providers as $provider) {
            try {
                // Check if the provider has a ping URL
                if (!$provider->ping_url) {
                    $errors[] = "Provider '{$provider->provider_name}' has no configured API URL for model discovery.";
                    continue;
                }
                
                $request = new Request(['provider_id' => $provider->id]);
                $result = $this->checkProviderModels($request, false);
                
                if (is_array($result) && isset($result['added'])) {
                    $totalFound += $result['added'];
                }
            } catch (\Exception $e) {
                $errors[] = "Error checking models for {$provider->provider_name}: " . $e->getMessage();
            }
        }
        
        return;
    }
    
    /**
     * Check available models for a specific provider and import new ones.
     *
     * @param Request $request
     * @param bool $redirect Whether to redirect after the check
     * @return
     */
    public function checkProviderModels(Request $request, $redirect = true)
    {
        $providerId = $request->input('provider_id');
        $provider = ProviderSetting::find($providerId);
        
        if (!$provider) {
            Toast::error('Provider not found.');
            return;
        }
        
        try {
            // Check if the provider is active and has a ping URL
            if (!$provider->is_active) {
                throw new \Exception("Provider '{$provider->provider_name}' is not active");
            }
            
            if (!$provider->ping_url) {
                throw new \Exception("Provider '{$provider->provider_name}' has no models URL configured");
            }
            
            // Get available models from provider API - Here comes the API response
            $apiResponse = $this->modelSettingsService->getModelStatus($provider->provider_name);
            
            // Debug log for the complete API response
            Log::debug('Raw API Response: ' . json_encode($apiResponse));
            
            if (empty($apiResponse) || !is_array($apiResponse)) {
                Toast::warning("No models returned from {$provider->provider_name} API.");
                return;
            }
            
            // Extract the models array from the API response
            $availableModels = [];
            if (isset($apiResponse['data']) && is_array($apiResponse['data'])) {
                // For APIs that have a 'data' property (as in the example)
                $availableModels = $apiResponse['data'];
                Log::info("Using 'data' array from API response containing " . count($availableModels) . " models");
            } else {
                // Fallback, if the format is different
                $availableModels = $apiResponse;
                Log::info("Using entire API response as models array containing " . count($availableModels) . " items");
            }
            
            // Statistics counters
            $added = 0;
            $updated = 0;
            $skipped = 0;
            
            Log::info("Processing " . count($availableModels) . " models for provider {$provider->provider_name} (ID: {$provider->id})");
            
            // Process each available model
            foreach ($availableModels as $modelObject) {
                // Ensure that the model object has an ID
                if (!isset($modelObject['id']) || empty($modelObject['id'])) {
                    Log::warning("Skipped model without ID from provider {$provider->provider_name}");
                    $skipped++;
                    continue;
                }
                
                $modelId = $modelObject['id'];
                $label = isset($modelObject['name']) ? $modelObject['name'] : $modelId;
                
                // Debug log for the individual model object
                Log::debug("Model object for ID {$modelId}: " . json_encode($modelObject));
                
                // IMPORTANT: Save the entire, unmodified model object as information
                // Do not perform any further modifications
                $information = $modelObject;
                
                // Check if the model already exists, but ONLY for this provider
                $existingModel = LanguageModel::where('model_id', $modelId)
                    ->where('provider_id', $provider->id)
                    ->first();
                
                if ($existingModel) {
                    // Update existing model for the same provider
                    Log::info("Updating existing model {$modelId} for provider {$provider->provider_name}");
                    
                    $existingModel->update([
                        'label' => $label,
                        'streamable' => $modelObject['streamable'] ?? true,
                        'information' => $information, // Store the complete object information
                    ]);
                    $updated++;
                    continue;
                }
                
                // Check if the model exists for a different provider
                $conflictModel = LanguageModel::where('model_id', $modelId)
                    ->where('provider_id', '!=', $provider->id)
                    ->first();
                    
                if ($conflictModel) {
                    // If the model ID exists for another provider, we generate
                    // a unique model ID by appending the provider name
                    $uniqueModelId = $modelId . '-' . strtolower($provider->provider_name);
                    
                    Log::info("Model ID {$modelId} already exists under provider {$conflictModel->provider->provider_name}, creating as {$uniqueModelId}");
                    
                    // Create the new model with the unique ID
                    LanguageModel::create([
                        'model_id' => $uniqueModelId,
                        'label' => $label . ' (' . $provider->provider_name . ')',
                        'provider_id' => $provider->id,
                        'is_active' => false,
                        'streamable' => $modelObject['streamable'] ?? true,
                        'is_visible' => false,
                        'display_order' => 0,
                        'information' => $information, // Store the complete object information
                        'settings' => null,
                    ]);
                    
                    $added++;
                    continue;
                }
                
                // Create new model (without conflicts)
                Log::info("Creating new model {$modelId} for provider {$provider->provider_name}");
                
                LanguageModel::create([
                    'model_id' => $modelId,
                    'label' => $label,
                    'provider_id' => $provider->id,
                    'is_active' => false,
                    'streamable' => $modelObject['streamable'] ?? true,
                    'is_visible' => false,
                    'display_order' => 0,
                    'information' => $information, // Store complete object information
                    'settings' => null,
                ]);
                
                $added++;
            }
            
            // Detailed success message
            if ($added > 0 || $updated > 0) {
                $message = "";
                if ($added > 0) {
                    $message .= "Added {$added} new models. ";
                }
                if ($updated > 0) {
                    $message .= "Updated {$updated} existing models. ";
                }
                if ($skipped > 0) {
                    $message .= "Skipped {$skipped} models. ";
                }
                
                Toast::success("Provider {$provider->provider_name}: {$message}");
            } else {
                if ($skipped > 0) {
                    Toast::warning("No models processed for {$provider->provider_name}. {$skipped} models were skipped.");
                } else {
                    Toast::info("No new models found for {$provider->provider_name}.");
                }
            }
            
            return;
        } catch (\Exception $e) {
            Log::error("Error checking models for {$provider->provider_name}: " . $e->getMessage());
            Toast::error("Error checking models for {$provider->provider_name}: " . $e->getMessage());
            return;
        }
    }

    /**
     * Delete a model.
     * 
     * @param Request $request
     * @return
     */
     public function deleteModel(Request $request)
    {
        Log::info('Delete model method called');

        try {
            $id = $request->get('id');
            
            // Modell vor dem Löschen finden, um den Namen für die Erfolgsmeldung zu haben
            $model = LanguageModel::find($id);
            if (!$model) {
                Log::warning("Model with ID {$id} not found for deletion");
                Toast::error('Model not found');
                return redirect()->back();
            }
            
            $modelName = $model->label;
            
            // Löschen des Modells
            $result = $model->delete();
            
            if ($result) {
                Log::info("Model '{$modelName}' (ID: {$id}) was successfully deleted");
                Toast::success("Model '{$modelName}' has been deleted");
            } else {
                Log::error("Failed to delete model '{$modelName}' (ID: {$id})");
                Toast::error("Could not delete model");
            }
        } catch (\Exception $e) {
            Log::error("Error deleting model: " . $e->getMessage());
            Toast::error("Error deleting model: " . $e->getMessage());
        }
        
        return;
    }

    /**
    * Deletes all models for a specific provider
     *
     * @param Request $request
     * @return
     */
    public function deleteAllModelsForProvider(Request $request)
    {
        try {
            $providerId = $request->get('provider_id');
            
            Log::info("Attempting to delete all models for provider ID: {$providerId}");
            
            $provider = ProviderSetting::find($providerId);
            if (!$provider) {
                Toast::error('Provider not found');
                return;
            }
            
            // Count how many models are to be deleted
            $count = LanguageModel::where('provider_id', $providerId)->count();
            
            if ($count === 0) {
                Toast::info("No models found for provider '{$provider->provider_name}'");
                return;
            }
            
            // Delete all models for this provider
            LanguageModel::where('provider_id', $providerId)->delete();
            
            Log::info("Successfully deleted {$count} models for provider '{$provider->provider_name}' (ID: {$providerId})");
            Toast::success("{$count} models were deleted successfully for provider '{$provider->provider_name}'");
            
        } catch (\Exception $e) {
            Log::error("Error deleting models for provider: " . $e->getMessage());
            Toast::error("Error deleting models: " . $e->getMessage());
        }
        
        return;
    }
}
