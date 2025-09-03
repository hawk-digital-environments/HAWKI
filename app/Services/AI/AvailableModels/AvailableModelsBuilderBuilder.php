<?php
declare(strict_types=1);


namespace App\Services\AI\AvailableModels;


use App\Services\AI\Value\ModelUsageType;

class AvailableModelsBuilderBuilder
{
    /**
     * @var array<string, array<string, ?string>> $defaultModelsByType
     */
    private array $defaultModelsByType = [];
    private array $systemModelsByType = [];
    
    public function addDefaultModelName(string $modelType, ModelUsageType $usageType, ?string $modelId): self
    {
        $this->defaultModelsByType[$usageType->value][$modelType] = $modelId;
        return $this;
    }
    
    public function addSystemModelName(string $modelType, ModelUsageType $usageType, ?string $modelId): self
    {
        $this->systemModelsByType[$usageType->value][$modelType] = $modelId;
        return $this;
    }
    
    public function build(): AvailableModelsBuilder
    {
        $this->validateKeysOverAllTypes($this->defaultModelsByType);
        $this->validateKeysOverAllTypes($this->systemModelsByType);
        
        return new AvailableModelsBuilder($this->defaultModelsByType, $this->systemModelsByType);
    }
    
    private function validateKeysOverAllTypes(array $modelsByType): void
    {
        $defaultKeys = array_keys($modelsByType[ModelUsageType::DEFAULT->value] ?? []);
        
        foreach ($modelsByType as $type => $models) {
            if ($type === ModelUsageType::DEFAULT->value) {
                continue;
            }
            
            $typeKeys = array_keys($models);
            if (!empty(array_diff($typeKeys, $defaultKeys))) {
                throw new \RuntimeException("Inconsistent keys for model type '{$type}'. Keys must match those of the 'default' usage type. Expected keys: [" . implode(', ', $defaultKeys) . "], found: [" . implode(', ', $typeKeys) . "].");
            }
        }
    }
}
