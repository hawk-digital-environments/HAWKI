<?php

namespace App\Services\AI;

use App\Services\AI\AIProviderFactory;
use Illuminate\Support\Facades\Log;
use App\Models\AppSetting; // Hinzugefügt für den Zugriff auf die Datenbank
use App\Models\LanguageModel; // Hinzugefügt für Zugriff auf die Modelltabelle
use App\Models\ProviderSetting; // Hinzugefügt für Zugriff auf die Providertabelle

class AIConnectionService
{
    /**
     * The provider factory
     * 
     * @var AIProviderFactory
     */
    private $providerFactory;
    
    /**
     * Create a new connection service
     * 
     * @param AIProviderFactory $providerFactory
     */
    public function __construct(AIProviderFactory $providerFactory)
    {
        $this->providerFactory = $providerFactory;
    }
    
    /**
     * Process a request to an AI model
     * 
     * @param array $rawPayload The unformatted payload
     * @param bool $streaming Whether to stream the response
     * @param callable|null $streamCallback Callback for streaming responses
     * @return mixed The response from the AI model
     */
    public function processRequest(array $rawPayload, bool $streaming = false, ?callable $streamCallback = null)
    {
        $modelId = $rawPayload['model'];
        $provider = $this->providerFactory->getProviderForModel($modelId);
        
        // Format the payload according to provider requirements
        $formattedPayload = $provider->formatPayload($rawPayload);
        
        if ($streaming && $streamCallback) {
            // Handle streaming response
            return $provider->connect($formattedPayload, $streamCallback);
        } else {
            // Handle standard response
            $response = $provider->connect($formattedPayload);
            return $provider->formatResponse($response);
        }
    }
    
    /**
     * Get a list of all available models
     * 
     * @return array
     */
    public function getAvailableModels(): array
    {
        $models = [];
        
        // Read models from the database
        $dbModels = LanguageModel::select('language_models.*', 'provider_settings.provider_name')
            ->join('provider_settings', 'language_models.provider_id', '=', 'provider_settings.id')
            ->where('language_models.is_active', true)
            ->where('language_models.is_visible', true)
            ->where('provider_settings.is_active', true)
            ->orderBy('language_models.display_order')
            ->get();
            
        foreach ($dbModels as $model) {
            $modelData = [
                'id' => $model->model_id,
                'label' => $model->label,
                'streamable' => $model->streamable,
                'provider' => $model->provider_name
            ];
            
            // Extract status from the information field, if available
            if (!empty($model->information)) {
                try {
                    // Check if information is already an array
                    $information = is_array($model->information) ? 
                                  $model->information : 
                                  json_decode($model->information, true);
                    
                    if (is_array($information)) {
                        // Set the default value for status
                        $modelData['status'] = 'ready';
                        
                        // If dynamic status query is needed (recognizable by the status key)
                        if (isset($information['status'])) {
                            // Get the provider for this model
                            $providerInterface = $this->providerFactory->getProviderInterface($model->provider_name);
                            
                            // If the provider has a getModelsStatus method, use it
                            if (method_exists($providerInterface, 'getModelsStatus')) {
                                $stats = $providerInterface->getModelsStatus();
                                
                                // Search for the current model in the status results
                                foreach ($stats as $stat) {
                                    if (isset($stat['id']) && $stat['id'] === $model->model_id) {
                                        // Update the status if available
                                        if (isset($stat['status'])) {
                                            $modelData['status'] = $stat['status'];
                                        }
                                        break;
                                    }
                                }
                            } else {
                                // Use the status stored in the database as default
                                $modelData['status'] = $information['status'];
                            }
                        }
                    }
                } catch (\Exception $e) {
                    \Log::warning("Error processing model status for model {$model->model_id}: {$e->getMessage()}");
                    $modelData['status'] = 'ready'; // Default value on error
                }
            } else {
                $modelData['status'] = 'ready'; // Default value if information is empty
            }
            
            $models[] = $modelData;
        }

        // Get models from the database instead of from the configuration
        $defaultModel = AppSetting::where('key', 'default_language_model')->value('value');

        $systemModels = [
            'title_generator' => AppSetting::where('key', 'system_model_title_generator')->value('value'),
            'prompt_improver' => AppSetting::where('key', 'system_model_prompt_improver')->value('value'),
            'summarizer' => AppSetting::where('key', 'system_model_summarizer')->value('value')
        ];

        return [
            'models' => $models,
            'defaultModel' => $defaultModel,
            'systemModels' => $systemModels
        ];
    }
    
    /**
     * Get details for a specific model
     * 
     * @param string $modelId
     * @return array
     */
    public function getModelDetails(string $modelId): array
    {
        $provider = $this->providerFactory->getProviderForModel($modelId);
        return $provider->getModelDetails($modelId);
    }
    
    /**
     * Get the provider instance for a specific model
     * 
     * @param string $modelId
     * @return \App\Services\AI\Interfaces\AIModelProviderInterface
     */
    public function getProviderForModel(string $modelId)
    {
        return $this->providerFactory->getProviderForModel($modelId);
    }


    public function checkModelsStatus(){

    }
}