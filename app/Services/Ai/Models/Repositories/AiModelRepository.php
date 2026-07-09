<?php
declare(strict_types=1);


namespace App\Services\Ai\Models\Repositories;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Services\Ai\Exceptions\ModelIdNotAvailableException;
use App\Services\Ai\Models\Capabilities\Values\NativeAiModelCapabilities;
use App\Services\Ai\Models\Flags\Values\AiModelFlags;
use App\Services\Ai\Models\Io\Values\AiModelIoMethods;
use App\Services\Ai\Models\Limits\AiModelLimitsInterface;
use App\Services\Ai\Models\Parameters\Values\AiModelParameters;
use App\Services\Ai\Models\Pricing\AiModelPricingInterface;
use App\Services\Ai\Models\Settings\Values\AiModelSettings;
use App\Services\Ai\StatusCheck\AiModelDemandCollection;
use App\Services\Ai\StatusCheck\AiModelOnlineStatusCollection;
use App\Services\Ai\Values\OnlineStatus;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepositoryWithContextualScopes;
use App\Services\System\Database\Eloquent\Repositories\Value\ScopeOverrides;
use Carbon\Carbon;


/**
 * Repository for {@see AiModel} entities.
 *
 * Extends {@see AbstractRepositoryWithContextualScopes}, which applies request-aware
 * query scopes by default (e.g. active-only or visibility filters). Pass a
 * {@see ScopeOverrides} instance to bypass these when administrative access is needed.
 */
class AiModelRepository extends AbstractRepositoryWithContextualScopes
{
    /**
     * Looks up a model by its numeric primary key or by its string `model_id`.
     *
     * A string $id that is numeric is treated as a primary key, not a model_id.
     * Returns null when no match is found.
     */
    public function findOne(mixed $id, ?ScopeOverrides $scopeOverrides = null): AiModel|null
    {
        if (is_string($id) && !is_numeric($id)) {
            return $this->getQuery($scopeOverrides)->where('model_id', $id)->first();
        }

        return $this->getQuery($scopeOverrides)->find($id);
    }

    /**
     * Like {@see findOne()} but throws {@see ModelIdNotAvailableException} when the model does not exist.
     */
    public function findOneOrFail(string|int $modelId, ?ScopeOverrides $scopeOverrides = null): AiModel
    {
        $model = $this->findOne($modelId, $scopeOverrides);
        if (!$model) {
            throw ModelIdNotAvailableException::forModelId($modelId);
        }
        return $model;
    }

    /**
     * Batch-updates the `status` column for all models in the collection's change-list.
     *
     * Only models whose status has changed are written; unchanged models are skipped.
     * Groups updates by status value to minimise the number of queries issued.
     */
    public function setAiModelStatusTo(AiModelOnlineStatusCollection $statusCollection): void
    {
        $modelIdsByStatus = [];
        foreach ($statusCollection->getChangedList() as $modelId => $status) {
            $modelIdsByStatus[$status->value][] = $modelId;
        }
        foreach ($modelIdsByStatus as $status => $modelIds) {
            $this->getQueryWithoutContextualScopes()
                ->whereIn('model_id', $modelIds)
                ->update(['status' => OnlineStatus::from($status)]);
        }
    }

    /**
     * Marks a model as inactive and saves the change to the database.
     * @param AiModel $model
     * @return void
     * @see AiModelRepository::disableAllExcept() for disabling multiple models at once.
     */
    public function disable(AiModel $model): void
    {
        $model->active = false;
        $model->save();
    }

    /**
     * Marks all models as inactive except for the ones with the given model IDs.
     * @param array<string|int|AiModel> $modelIds
     * @param AiProvider|null $provider An optional provider to filter the models by. If provided, only models from this provider will be considered for disabling.
     * @return void
     * @see AiModelRepository::disable() for disabling a single model.
     */
    public function disableAllExcept(array $modelIds, AiProvider|null $provider = null): void
    {
        $idFilter = [];
        $modelIdFilter = [];

        foreach ($modelIds as $modelId) {
            if ($modelId instanceof AiModel) {
                $idFilter[] = $modelId->id;
            } else if (is_numeric((string)$modelId)) {
                $idFilter[] = (int)$modelId;
            } else {
                $modelIdFilter[] = (string)$modelId;
            }
        }

        $query = $this->getQueryWithoutContextualScopes()
            ->whereNotIn('id', $idFilter)
            ->whereNotIn('model_id', $modelIdFilter);
        if ($provider) {
            $query->where('provider_id', $provider->id);
        }
        $query->update(['active' => false]);
    }

    /**
     * Batch-updates the `demand` column for all models in the collection's change-list.
     *
     * Groups updates by demand value to minimise the number of queries issued.
     */
    public function setAiModelDemandTo(AiModelDemandCollection $demandCollection): void
    {
        $modelIdsByDemand = [];
        foreach ($demandCollection->getChangedList() as $modelId => $demand) {
            $modelIdsByDemand[$demand->value][] = $modelId;
        }
        foreach ($modelIdsByDemand as $demand => $modelIds) {
            $this->getQueryWithoutContextualScopes()
                ->whereIn('model_id', $modelIds)
                ->update(['demand' => $demand]);
        }
    }

    /**
     * Inserts or updates a model record identified by (model_id, provider_id).
     *
     * On conflict the remaining fields are overwritten. This is used by the sync process
     * to reflect the latest state from the provider adapter.
     */
    public function upsert(
        string                         $modelType,
        AiProvider                     $provider,
        string                         $modelId,
        bool                           $active,
        string|null                    $label,
        AiModelIoMethods               $input,
        AiModelIoMethods               $output,
        AiModelParameters              $parameters,
        AiModelSettings                $settings,
        AiModelLimitsInterface|null    $limits = null,
        AiModelPricingInterface|null   $pricing = null,
        AiModelFlags|null              $flags = null,
        NativeAiModelCapabilities|null $nativeCapabilities = null,
        \DateTimeImmutable|Carbon|null $deprecationDate = null,
        string|null                    $documentationUrl = null
    ): AiModel
    {
        return $this->getQueryWithoutContextualScopes()
            ->updateOrCreate(
                ['model_id' => $modelId, 'provider_id' => $provider->id],
                [
                    'active' => $active,
                    'label' => $label,
                    'input' => $input,
                    'output' => $output,
                    'parameters' => $parameters,
                    'settings' => $settings,
                    'limits' => $limits,
                    'pricing' => $pricing,
                    'flags' => $flags,
                    'native_capabilities' => $nativeCapabilities,
                    'model_type' => $modelType,
                    'deprecation_date' => $deprecationDate,
                    'documentation_url' => $documentationUrl
                ]
            );
    }
}
