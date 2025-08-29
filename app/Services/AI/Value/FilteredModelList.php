<?php
declare(strict_types=1);


namespace App\Services\AI\Value;

/**
 * This list is used to filter the models based on the usage type.
 * It allows to define default models and system models for different usage types.
 * Note, that "default" and "system" models may be forced to be active, even if they are configured as "not available" in the current usage type;
 * this is because if a model is used as a system model or default model, it should always be available for the application to function correctly.
 */
class FilteredModelList
{
    private ModelListUsageType $usageType;
    private array $defaultModelNames = [];
    private array $systemModelsMapping = [];
    private AiModel|null $defaultModel = null;
    private array $systemModels = [];

    /**
     * @var array
     */
    private array $systemModelTypes = [];

    /**
     * @var AiModel[]
     */
    private array $models = [];

    public function __construct(
        ModelListUsageType $usageType
    )
    {
        $this->usageType = $usageType;
    }

    /**
     * Adds a new model to the list.
     * Note, this MUST be done AFTER all your model names have been configured.
     *
     * @param AiModel $model
     * @return void
     */
    public function addModel(AiModel $model): void
    {
        if (!$model->isActive()) {
            return;
        }

        if (isset($this->models[$model->getId()])) {
            // This reeks like a misconfiguration, so we just skip this model
            return;
        }

        $isSystemModel = $this->isUsedAsSystemModel($model);
        $isDefaultModel = $this->defaultModel === null && $this->isUsedAsDefaultModel($model);

        if (!$isSystemModel && !$isDefaultModel && $this->usageType === ModelListUsageType::EXTERNAL_APP && !$model->isAllowedInExternalApp()) {
            // Skip if this model is not a system model, not a default model and not allowed in external apps, but we are in external app mode
            return;
        }

        if ($isDefaultModel) {
            $this->defaultModel = $model;
        }

        if ($isSystemModel) {
            foreach ($this->systemModelTypes as $modelType) {
                $systemModelName = $this->systemModelsMapping[$this->usageType->value][$modelType]
                    ?? $this->systemModelsMapping[ModelListUsageType::DEFAULT->value][$modelType]
                    ?? '';

                if ($model->idMatches($systemModelName)) {
                    $this->systemModels[$modelType] = $model;
                }
            }
        }

        $this->models[$model->getId()] = $model;
    }

    /**
     * Sets the name of the default model for the given usage type.
     * The default model is used when no specific model is selected for the usage type.
     * This MUST be called before adding any models to the list, otherwise it will throw an exception.
     * The default model will be forced to be available even if it is configured as "not available" in the current usage type.
     * The "active" state of the model will ALWAYS win, so if the model is not active, it will not be added to the list, leading to an exception when trying to get the default model.
     *
     * @param ModelListUsageType $usageType
     * @param string|null $modelName
     * @return void
     */
    public function setDefaultModelName(ModelListUsageType $usageType, ?string $modelName): void
    {
        $this->assertNoModelsSetYet();
        $this->defaultModelNames[$usageType->value] = $modelName;
    }

    /**
     * Adds a system model mapping for the given model type and model name.
     * System models are considered to be overrides in a non "default" usage type, leading to all default system models being also available in the current usage type.
     * This MUST be called before adding any models to the list, otherwise it will throw an exception.
     * The system model will be forced to be available even if it is configured as "not available" in the current usage type.
     * The "active" state of the model will ALWAYS win, so if the model is not active, it will not be added to the list, leading to an exception when trying to get the system models.
     *
     * @param string $modelType
     * @param string $modelName
     * @param ModelListUsageType|null $usageType
     * @return void
     */
    public function addSystemModelMapping(string $modelType, string $modelName, ?ModelListUsageType $usageType = null): void
    {
        $this->assertNoModelsSetYet();
        $usageType = $usageType ?? ModelListUsageType::DEFAULT;
        $this->systemModelsMapping[$usageType->value][$modelType] = $modelName;
        if (!in_array($modelType, $this->systemModelTypes, true)) {
            $this->systemModelTypes[] = $modelType;
        }
    }

    /**
     * Returns the default model for the current usage type.
     * If no default model is set, it will throw an exception.
     * @return AiModel
     */
    public function getDefaultModel(): AiModel
    {
        if ($this->defaultModel === null) {
            throw new \RuntimeException('No default model is available, please check your configuration.');
        }

        return $this->defaultModel;
    }

    /**
     * Returns the system models for the current usage type.
     * The result will contain all system models that are configured for the current usage type by their type.
     * @return array<string, AiModel>
     */
    public function getSystemModels(): array
    {
        if (count($this->systemModels) !== count($this->systemModelTypes)) {
            $missingModelTypes = array_diff($this->systemModelTypes, array_keys($this->systemModels));
            throw new \RuntimeException('Not all system models are set, please check your configuration. Missing models for: ' . implode(', ', $missingModelTypes));
        }
        return $this->systemModels;
    }

    /**
     * Returns the list of all models that are available in the current usage type.
     * This will include the default model and the system models, but also all other models that
     * are configured for the current usage type and are active.
     * @return AiModel[]
     */
    public function getModels(): array
    {
        return array_values($this->models);
    }

    private function assertNoModelsSetYet(): void
    {
        if (!empty($this->models)) {
            throw new \RuntimeException('At least one Model has already been set, you can not modify the filters after that.');
        }
    }

    private function isUsedAsSystemModel(AiModel $model): bool
    {
        foreach ($this->systemModelTypes as $modelType) {
            $modelName = $this->systemModelsMapping[$this->usageType->value][$modelType]
                ?? $this->systemModelsMapping[ModelListUsageType::DEFAULT->value][$modelType]
                ?? null;
            if ($modelName === null) {
                // Weired, but maybe a misconfiguration, so we just skip this model
                continue;
            }
            if ($model->idMatches($modelName)) {
                return true;
            }
        }
        return false;
    }

    private function isUsedAsDefaultModel(AiModel $model): bool
    {
        $configuredDefaultModelName = $this->defaultModelNames[$this->usageType->value]
            ?? $this->defaultModelNames[ModelListUsageType::DEFAULT->value]
            ?? null;

        if ($configuredDefaultModelName === null) {
            return false;
        }

        return $model->idMatches($configuredDefaultModelName);
    }
}
