<?php

namespace App\Services\AI;

use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\FilteredModelList;
use App\Services\AI\Value\ModelListUsageType;

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
     * @param bool|null $external Set this to true, to receive the models enabled for external applications.
     *                           If null, the default models will be returned.
     */
    public function getAvailableModels(?bool $external = null): array
    {
        $list = new FilteredModelList(
            $external ? ModelListUsageType::EXTERNAL_APP : ModelListUsageType::DEFAULT
        );
        
        $config = config('model_providers') ?? [];
        
        $list->setDefaultModelName(ModelListUsageType::DEFAULT, $config['defaultModel']);
        $list->setDefaultModelName(ModelListUsageType::EXTERNAL_APP, $config['defaultModel.external'] ?? null);
        
        foreach ($config['system_models'] as $modelType => $modelName) {
            $list->addSystemModelMapping($modelType, $modelName);
        }
        if ($external) {
            foreach ($config['system_models.external'] ?? [] as $modelType => $modelName) {
                $list->addSystemModelMapping($modelType, $modelName, ModelListUsageType::EXTERNAL_APP);
            }
        }
        
        foreach ($config['providers'] as $provider) {
            if ($provider['active']) {
                $providerInterface = $this->providerFactory->getProviderInterface($provider['id']);
                if (method_exists($providerInterface, 'getModelsStatus') &&
                    $provider['status_check'] && !empty($provider['ping_url'])) {
                    foreach ($providerInterface->getModelsStatus() as $stat) {
                        $list->addModel(new AiModel($stat));
                    }
                } else {
                    foreach ($provider['models'] as $model) {
                        $list->addModel(new AiModel($model));
                    }
                }
            }
        }

        return [
            'models' => array_map(static fn(AiModel $model) => $model->toArray(), $list->getModels()),
            'defaultModel' => $list->getDefaultModel()->getId(),
            'systemModels' => array_map(
                static fn(AiModel $model) => $model->getId(),
                $list->getSystemModels()
            )
        ];
    }
    
    /**
     * Get a specific model by its ID
     *
     * @param string $modelId The model ID to retrieve
     * @param bool|null $external Set this to true, to receive the models enabled for external applications.
     *                           If null, the default models will be returned.
     * @return AiModel|null
     */
    public function getModelById(string $modelId, ?bool $external = null): ?AiModel
    {
        foreach ($this->getAvailableModels($external)['models'] as $model) {
            if ($model['id'] === $modelId) {
                return new AiModel($model);
            }
        }
        return null;
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
