<?php
declare(strict_types=1);


namespace App\Services\AI\AvailableModels;


use App\Services\AI\Exception\MissingDefaultModelsException;
use App\Services\AI\Exception\MissingSystemModelsException;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiModelCollection;
use App\Services\AI\Value\AiModelMap;
use App\Services\AI\Value\AvailableAiModels;
use App\Services\AI\Value\ModelUsageType;

class AvailableModelsBuilder
{
    private AiModelCollection $models;
    
    public function __construct(
        /**
         * @var array<string, array<string, ?string>> $defaultModelsByType
         */
        private readonly array $defaultModelsByType,
        /**
         * @var array<string, array<string, ?string>> $systemModelsByType
         */
        private readonly array $systemModelsByType
    )
    {
        $this->models = new AiModelCollection();
    }
    
    public function addModel(AiModel $model): self
    {
        $this->models = $this->models->withModel($model);
        return $this;
    }
    
    public function build(ModelUsageType $usageType): AvailableAiModels
    {
        $defaultModelIds = $this->getModelListWithFallbackForType($this->defaultModelsByType, $usageType);
        $systemModelIds = $this->getModelListWithFallbackForType($this->systemModelsByType, $usageType);
        
        $defaultModels = new AiModelMap();
        $systemModels = new AiModelMap();
        $models = new AiModelCollection();
        
        foreach ($this->models as $model) {
            if (!$model->isActive()) {
                continue;
            }
            
            $isDefaultModel = false;
            foreach ($defaultModelIds as $defaultModelType => $defaultModelId) {
                if ($model->idMatches($defaultModelId)) {
                    $defaultModels = $defaultModels->withModel($defaultModelType, $model);
                    $isDefaultModel = true;
                }
            }
            
            $isSystemModel = false;
            foreach ($systemModelIds as $systemModelType => $systemModelId) {
                if ($model->idMatches($systemModelId)) {
                    $systemModels = $systemModels->withModel($systemModelType, $model);
                    $isSystemModel = true;
                }
            }
            
            if (!$isDefaultModel && !$isSystemModel && !$model->isAvailableInUsageType($usageType)) {
                continue;
            }
            
            $models = $models->withModel($model);
        }
        
        if (count($defaultModelIds) !== $defaultModels->count()) {
            throw MissingDefaultModelsException::createForMissing($defaultModelIds, $defaultModels);
        }
        if (count($systemModelIds) !== $systemModels->count()) {
            throw MissingSystemModelsException::createForMissing($systemModelIds, $systemModels);
        }
        
        return new AvailableAiModels(
            models: $models,
            defaultModels: $defaultModels,
            systemModels: $systemModels
        );
    }
    
    private function getModelListWithFallbackForType(array $list, ModelUsageType $usageType): array
    {
        $defaultModels = $list[ModelUsageType::DEFAULT->value] ?? [];
        $specificModels = $list[$usageType->value] ?? [];
        return array_merge(
            $defaultModels,
            array_filter($specificModels)
        );
    }
}
