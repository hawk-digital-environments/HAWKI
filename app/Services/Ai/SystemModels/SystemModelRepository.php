<?php
declare(strict_types=1);


namespace App\Services\Ai\SystemModels;


use App\Collections\SystemModelCollection;
use App\Models\Ai\AiModel;
use App\Models\Ai\SystemModel;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepositoryWithContextualScopes;
use App\Services\System\Database\Eloquent\Repositories\Value\ScopeOverrides;

/**
 * Database access layer for the {@see SystemModel} pivot table.
 *
 * System models are administrator-designated {@see AiModel} records that fulfil a
 * specific role within a usage type (e.g. the default chat model for a given tenant
 * context).  Each record is uniquely keyed on `(model_type, usage_type)`.
 *
 * The repository is used by the system-model sync flow: the config file defines which
 * model should serve each role, and the syncer calls {@see upsert()} to persist it.
 * {@see deleteWithTypeFilter()} is called beforehand to remove stale assignments.
 */
class SystemModelRepository extends AbstractRepositoryWithContextualScopes
{
    /**
     * Returns all system-model assignments, optionally narrowed by usage type and/or model type.
     *
     * Both filters are applied only when non-null, so omitting both returns the complete table.
     */
    public function findAllFiltered(
        string|null     $usageType = null,
        string|null     $modelType = null,
        ?ScopeOverrides $scopeOverrides = null
    ): SystemModelCollection
    {
        $query = $this->getQuery($scopeOverrides);

        if ($usageType) {
            $query->where('usage_type', $usageType);
        }

        if ($modelType) {
            $query->where('model_type', $modelType);
        }

        return $query->get();
    }

    /**
     * Deletes all system-model assignments that match the given model type and usage type.
     *
     * Called during config sync to clear existing assignments before writing the new ones,
     * ensuring removed entries in the config file are reflected in the database.
     */
    public function deleteWithTypeFilter(
        string          $modelType,
        string          $usageType,
        ?ScopeOverrides $scopeOverrides = null
    ): void
    {
        $this->getQuery($scopeOverrides)
            ->where('model_type', $modelType)
            ->where('usage_type', $usageType)
            ->delete();
    }

    /**
     * Creates or updates the system-model assignment for the given type combination.
     *
     * The unique key is `(model_type, usage_type)`; the only mutable column is `model_id`.
     * Contextual scopes are bypassed so that inactive or tenant-filtered models can still
     * be referenced as system models.
     */
    public function upsert(
        string  $modelType,
        string  $usageType,
        AiModel $model
    ): SystemModel
    {
        return $this->getQueryWithoutContextualScopes()->updateOrCreate(
            [
                'model_type' => $modelType,
                'usage_type' => $usageType
            ],
            [
                'model_id' => $model->model_id
            ]
        );
    }
}
