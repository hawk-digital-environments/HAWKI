<?php
declare(strict_types=1);


namespace App\Services\Ai\SystemModels;


use App\Collections\SystemModelCollection;
use App\Models\Ai\AiModel;
use App\Models\Ai\SystemModel;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepositoryWithContextualScopes;
use App\Services\System\Database\Eloquent\Repositories\Value\ScopeOverrides;

class SystemModelRepository extends AbstractRepositoryWithContextualScopes
{
    /**
     * Finds all models in the database, with an optional filter
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
