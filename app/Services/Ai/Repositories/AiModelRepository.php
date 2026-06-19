<?php
declare(strict_types=1);


namespace App\Services\Ai\Repositories;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Services\Ai\Exceptions\ModelIdNotAvailableException;
use App\Services\Ai\StatusCheck\AiModelDemandCollection;
use App\Services\Ai\StatusCheck\AiModelOnlineStatusCollection;
use App\Services\Ai\Values\ModelCapabilities;
use App\Services\Ai\Values\ModelIoMethods;
use App\Services\Ai\Values\ModelParameters;
use App\Services\Ai\Values\ModelSettings;
use App\Services\Ai\Values\OnlineStatus;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepositoryWithContextualScopes;
use App\Services\System\Database\Eloquent\Repositories\Value\ScopeOverrides;


class AiModelRepository extends AbstractRepositoryWithContextualScopes
{
    public function findOne(mixed $id, ?ScopeOverrides $scopeOverrides = null): AiModel|null
    {
        if (is_string($id) && !is_numeric($id)) {
            return $this->getQuery($scopeOverrides)->where('model_id', $id)->first();
        }

        return $this->getQuery($scopeOverrides)->find($id);
    }

    public function findOneOrFail(string|int $modelId, ?ScopeOverrides $scopeOverrides = null): AiModel
    {
        $model = $this->findOne($modelId, $scopeOverrides);
        if (!$model) {
            throw new ModelIdNotAvailableException($modelId);
        }
        return $model;
    }

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

    public function upsert(
        AiProvider             $provider,
        string                 $modelId,
        bool                   $active,
        string|null            $label,
        ModelIoMethods         $input,
        ModelIoMethods         $output,
        ModelParameters        $parameters,
        ModelSettings          $settings,
        ModelCapabilities|null $capabilities = null
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
                    'capabilities' => $capabilities,
                ]
            );
    }
}
