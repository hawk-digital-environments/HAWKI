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
        $dbModels = LanguageModel::select('language_models.*', 'provider_settings.provider_name', 'provider_settings.api_format')
            ->join('provider_settings', 'language_models.provider_id', '=', 'provider_settings.id')
            ->where('language_models.is_active', true)
            ->where('language_models.is_visible', true)
            ->where('provider_settings.is_active', true)
            ->orderBy('language_models.display_order')
            ->get();
        
        //Log::info(json_encode($dbModels->toArray(), JSON_PRETTY_PRINT));
        
        foreach ($dbModels as $model) {
            $modelData = [
                'id' => $model->model_id,
                'label' => $model->label,
                'streamable' => $model->streamable,
                'api_format' => $model->api_format ?? $model->provider_name,
                'provider_name' => $model->provider_name,
                'status' => 'ready' // 'ready', 'loading', 'unavailable' Default value, will be updated below
            ];

            // Note: this loads the model status from the database and only works for GWDG models
            // The status property can only be updated via admin panel atm – so this needs to be reworked
            // Calling the /models api on every page refresh by every user individually seems a bit overkill

            try {
                // Get provider for the model
                $provider = $this->providerFactory->getProviderForModel($model->model_id);
                //Log::info("Provider class: " . get_class($provider));
                if ($provider) {
                    // Retrieve details including status from the provider
                    $modelDetails = $provider->getModelDetails($model->model_id);
                    //Log::info($modelStatus);
                    // Extract status from details property in DB if available
                    if (isset($modelStatus['status'])) {
                        $modelData['status'] = $modelStatus['status'];
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Error retrieving model status for {$model->model_id}: {$e->getMessage()}");
                // In case of errors, the default status is maintained
            }
            //Log::info($modelData);
            $models[] = $modelData;
        }

        // Get models from the database instead of from the configuration
        $defaultModel = AppSetting::where('key', 'default_language_model')->value('value');

        $systemModels = [
            'title_generator' => AppSetting::where('key', 'system_model_title_generator')->value('value'),
            'prompt_improver' => AppSetting::where('key', 'system_model_prompt_improver')->value('value'),
            'summarizer' => AppSetting::where('key', 'system_model_summarizer')->value('value')
        ];
        if (config('logging.triggers.default_model')) {
            Log::info('Default model:', ['model' => $defaultModel]);
            Log::info('System models:', $systemModels);
        }
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

}