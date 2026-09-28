<?php
declare(strict_types=1);


namespace App\Services\Ai\ConfigFileSync\Syncers;

use App\Services\Ai\ConfigFileSync\Contracts\ConfigSyncerInterface;
use App\Services\Ai\Models\Repositories\AiModelRepository;
use App\Services\Ai\SystemModels\SystemModelRegistry;
use App\Services\Ai\SystemModels\SystemModelRepository;
use App\Services\Ai\SystemModels\Values\WellKnownSystemModelTypes;
use App\Services\System\UsageTypes\Contracts\WellKnownUsageTypes;
use App\Utils\JobMetrics;
use Illuminate\Config\Repository;
use Illuminate\Container\Attributes\Config;

/**
 * Syncs system-model assignments from config into the database.
 *
 * System models are the specific AI model instances that HAWKI selects automatically for
 * built-in tasks such as "default chat", "title generation", "prompt improvement",
 * "summary" and "translation". They are configured via `model_providers.system_models` (main app) and
 * `model_providers.system_models_ext_app` (external app).
 *
 * Config keys resolve to canonical types via {@see resolveModelType()}: the
 * Ai core's own legacy file-key names are mapped by the core itself, and any
 * other key resolves through the {@see SystemModelRegistry}'s declarations —
 * recognition is derived from the contributions, so a domain's key never
 * appears inside this syncer. A null value for an external-app key removes
 * the corresponding assignment. After all assignments are written, a sanity
 * check validates that every system model points to an active model that is
 * permitted for its usage type.
 *
 * @internal
 */
readonly class SystemModelSyncer implements ConfigSyncerInterface
{
    public function __construct(
        #[Config('model_providers.system_models')]
        private array                 $systemModels,
        #[Config('model_providers.system_models_ext_app', [])]
        private array                 $extAppSystemModels,
        private SystemModelRegistry   $registry,
        private Repository            $configRepository,
        private SystemModelRepository $systemModelRepository,
        private AiModelRepository     $modelRepository
    )
    {
    }

    public function getCurrentHash(): string
    {
        return md5(
            json_encode([
                'systemModels' => $this->mergedSystemModels(),
                'extAppSystemModels' => $this->extAppSystemModels,
                'registryEntries' => $this->registry->entries(),
            ])
        );
    }

    public function sync(JobMetrics $metrics): void
    {
        $systemModels = $this->mergedSystemModels();
        $defaultModelFromLegacyConfig = $this->warnForLegacyConfiguration($metrics);
        if ($defaultModelFromLegacyConfig !== null) {
            $systemModels['default_model'] = $defaultModelFromLegacyConfig;
        }

        foreach ($systemModels as $key => $modelId) {
            $this->syncModel(WellKnownUsageTypes::MAIN_APP, $key, $modelId, $metrics);
        }

        foreach ($this->extAppSystemModels as $key => $modelId) {
            $modelType = $this->resolveModelType($key);
            if ($modelType === null) {
                $metrics = $metrics->addError("Invalid system model type key '$key' in configuration for external app default models.");
                continue;
            }
            if ($modelId === null) {
                try {
                    $this->systemModelRepository->deleteWithTypeFilter(
                        modelType: $modelType,
                        usageType: WellKnownUsageTypes::EXTERNAL_APP,
                        scopeOverrides: $this->systemModelRepository->makeScopeOverrides()
                    );
                } catch (\Throwable) {
                    $metrics = $metrics->addError("Invalid system model type key '$key' in configuration for external app default models.");
                }
                continue;
            }
            $this->syncModel(WellKnownUsageTypes::EXTERNAL_APP, $key, $modelId, $metrics);
        }

        $this->doModelSanityCheck($metrics);
    }

    private function warnForLegacyConfiguration(JobMetrics $metrics): string|null
    {
        $defaultModels = $this->configRepository->get('model_providers.default_models');
        if (!empty($defaultModels)) {
            if (!isset($this->systemModels['default_model']) && isset($defaultModels['default_model'])) {
                $metrics->warning("The 'model_providers.default_models.default_model' configuration is missing, but a default model is set in the legacy 'model_providers.default_models' configuration. Using the value from the legacy configuration for now, but please move it to 'model_providers.system_models.default_model' and remove 'model_providers.default_models' completely.");
                return $defaultModels['default_model'];
            }
            $metrics->warning("The 'model_providers.default_models' configuration is still in use. Please move 'model_providers.default_models.default_model' to 'model_providers.system_models.default_model' and remove 'model_providers.default_models' completely.");
        }
        return null;
    }

    /**
     * The file-based configuration with the registry's domain-contributed
     * entries merged over it (per key, registry wins): domains own their
     * defaults in their own config and declare them through the
     * {@see SystemModelRegistry} instead of writing into the Ai core's
     * config file.
     *
     * @return array<string, string|null>
     */
    private function mergedSystemModels(): array
    {
        return array_merge($this->systemModels, $this->registry->systemModels());
    }

    private function syncModel(string $usageType, string $key, string $modelId, JobMetrics $metrics): void
    {
        $model = $this->modelRepository->findOne($modelId, $this->modelRepository->makeScopeOverrides());
        if (!$model) {
            $metrics->error("Model with ID '$modelId' for type '$key' not found in database.");
            return;
        }

        $modelType = $this->resolveModelType($key);
        if ($modelType === null) {
            $metrics->error("Invalid system model type key '$key' in configuration.");
            return;
        }

        $this->systemModelRepository->upsert(
            modelType: $modelType,
            usageType: $usageType,
            model: $model
        );

        $metrics->increment('System model');
    }

    private function doModelSanityCheck(JobMetrics $metrics): void
    {
        foreach ($this->systemModelRepository->findAllFiltered(scopeOverrides: $this->systemModelRepository->makeScopeOverrides()) as $systemModel) {
            if (!$systemModel->model) {
                $metrics->error("System model with ID '{$systemModel->id}' is linked to a non-existing model with ID '{$systemModel->model_id}'.");
                continue;
            }
            if (!$systemModel->model->active) {
                $metrics->error("System model with ID '{$systemModel->id}' is linked to an inactive model with ID '{$systemModel->model_id}'.");
                continue;
            }
            if (!$systemModel->model->usageRules->isAllowedIn($systemModel->usage_type)) {
                $metrics->error("System model with ID '{$systemModel->id}' is linked to a model with ID '{$systemModel->model_id}' that is not allowed for usage type '{$systemModel->usage_type}' according to its usage rules.");
            }
        }
    }

    /**
     * Resolves a configuration-style key to its canonical system-model type.
     * The Ai core's own legacy file-key names are mapped here — the core's
     * history is the core's to keep — while every other key resolves through
     * the {@see SystemModelRegistry}'s declarations, so a contributing
     * domain's key never appears inside this syncer. Null when nothing
     * declares the key.
     */
    private function resolveModelType(string $key): ?string
    {
        $legacyCoreType = match ($key) {
            'default_model' => WellKnownSystemModelTypes::DEFAULT,
            'title_generator' => WellKnownSystemModelTypes::TITLE_GENERATION,
            'prompt_improver' => WellKnownSystemModelTypes::PROMPT_IMPROVEMENT,
            'summarizer' => WellKnownSystemModelTypes::SUMMARY,
            default => null,
        };

        return $legacyCoreType ?? $this->registry->typeForKey($key);
    }
}
