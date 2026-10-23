<?php
declare(strict_types=1);


namespace App\Services\Ai\Models\Repositories;

use App\Collections\AiModelCollection;
use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Services\Ai\Exceptions\ModelIdNotAvailableException;
use App\Services\Ai\Models\Capabilities\Values\NativeAiModelCapabilities;
use App\Services\Ai\Models\Flags\Values\AiModelFlags;
use App\Services\Ai\Models\Identifiers\Values\AiModelIdentifier;
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
use Illuminate\Database\Eloquent\Builder;


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
     * Looks up a model by its numeric primary key or its provider-qualified identifier
     * (`<provider_id>/<model_id>`, see {@see AiModelIdentifier}).
     *
     * A string $id that is numeric is treated as a primary key. A plain `model_id` never matches,
     * because the same `model_id` may exist for several providers.
     * Returns null when no match is found.
     */
    public function findOne(mixed $id, ?ScopeOverrides $scopeOverrides = null): AiModel|null
    {
        if (is_string($id) && !is_numeric($id)) {
            $identifier = AiModelIdentifier::fromString($id);
            if (!$identifier->isQualified()) {
                return null;
            }

            return $this->getQuery($scopeOverrides)
                ->where('model_id', $identifier->modelId)
                ->whereHas('provider', fn(Builder $query) => $query->where('provider_id', $identifier->providerId))
                ->first();
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
     * Looks up a model by its `model_id` within the given provider. Returns null when no match is found.
     */
    public function findOneByProvider(AiProvider $provider, string $modelId, ?ScopeOverrides $scopeOverrides = null): AiModel|null
    {
        return $this->getQuery($scopeOverrides)
            ->where('provider_id', $provider->id)
            ->where('model_id', $modelId)
            ->first();
    }

    /**
     * Like {@see findOneByProvider()} but throws {@see ModelIdNotAvailableException} when the model does not exist.
     */
    public function findOneByProviderOrFail(AiProvider $provider, string $modelId, ?ScopeOverrides $scopeOverrides = null): AiModel
    {
        $model = $this->findOneByProvider($provider, $modelId, $scopeOverrides);
        if (!$model) {
            throw ModelIdNotAvailableException::forModelId(
                (string)new AiModelIdentifier($provider->provider_id, $modelId)
            );
        }
        return $model;
    }

    /**
     * Returns all models with the given plain `model_id` across all providers (with their provider loaded).
     * More than one result means the plain `model_id` is ambiguous. Only meant for resolving plain ids
     * from external surfaces, see {@see \App\Services\Ai\Models\Identifiers\ModelFinder::findAllowingPlainModelId()}.
     */
    public function findAllByModelId(string $modelId, ?ScopeOverrides $scopeOverrides = null): AiModelCollection
    {
        return $this->getQuery($scopeOverrides)
            ->with('provider')
            ->where('model_id', $modelId)
            ->get();
    }

    /**
     * Batch-updates the `status` column for all models in the collection's change-list.
     *
     * Only models whose status has changed are written; unchanged models are skipped.
     * Groups updates by status value to minimise the number of queries issued.
     * Pass the $provider the collection was built for, otherwise models with the same
     * `model_id` of other providers are updated as well.
     */
    public function setAiModelStatusTo(AiModelOnlineStatusCollection $statusCollection, AiProvider|null $provider = null): void
    {
        $modelIdsByStatus = [];
        foreach ($statusCollection->getChangedList() as $modelId => $status) {
            $modelIdsByStatus[$status->value][] = $modelId;
        }
        foreach ($modelIdsByStatus as $status => $modelIds) {
            $this->getQueryWithoutContextualScopes()
                ->whereIn('model_id', $modelIds)
                ->when($provider, fn(Builder $query) => $query->where('provider_id', $provider->id))
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
     * Pass the $provider the collection was built for, see {@see setAiModelStatusTo()}.
     */
    public function setAiModelDemandTo(AiModelDemandCollection $demandCollection, AiProvider|null $provider = null): void
    {
        $modelIdsByDemand = [];
        foreach ($demandCollection->getChangedList() as $modelId => $demand) {
            $modelIdsByDemand[$demand->value][] = $modelId;
        }
        foreach ($modelIdsByDemand as $demand => $modelIds) {
            $this->getQueryWithoutContextualScopes()
                ->whereIn('model_id', $modelIds)
                ->when($provider, fn(Builder $query) => $query->where('provider_id', $provider->id))
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
