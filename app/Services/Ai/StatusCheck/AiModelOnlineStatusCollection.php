<?php
declare(strict_types=1);


namespace App\Services\Ai\StatusCheck;


use App\Collections\AiModelCollection;
use App\Models\Ai\AiModel;
use App\Services\Ai\Values\OnlineStatus;
use Psr\Log\LoggerInterface;
use Traversable;

/**
 * Mutable online-status registry for a fixed set of {@see AiModel} instances, used during a
 * single model-status-check run.
 *
 * Every model in the supplied collection is initialised to {@see OnlineStatus::UNKNOWN}.
 * Provider adapters or event listeners then call {@see set()}, {@see setOnline()}, or
 * {@see setOffline()} to record what the provider's status endpoint reported.
 *
 * After the adapter has finished, {@see ModelStatusUpdater} calls {@see setAllUnknownToOffline()}
 * to treat any model that the provider did not mention as offline.  The final state is then
 * read back via {@see getChangedList()} and persisted to the database by
 * {@see \App\Services\Ai\Models\Repositories\AiModelRepository::setAiModelStatusTo()}.
 *
 * The collection is iterable and yields {@see AiModel} instances, so adapters can iterate
 * the models they need to probe directly on this object:
 *
 * ```php
 * foreach ($statusCollection as $model) {
 *     $isReachable = $provider->ping($model->model_id);
 *     $statusCollection->set($model, $isReachable ? OnlineStatus::ONLINE : OnlineStatus::OFFLINE);
 * }
 * ```
 *
 * When a provider returns model IDs that are not in the initial collection, the call is silently
 * ignored and logged at DEBUG level, so stale or extra provider responses do not cause errors.
 *
 * @implements \IteratorAggregate<int, AiModel>
 */
class AiModelOnlineStatusCollection implements \IteratorAggregate
{
    /**
     * Map of model_id → current online status for every model in the collection.
     *
     * @var array<string, OnlineStatus>
     */
    private array $statuses = [];

    public function __construct(
        private readonly AiModelCollection $models,
        private readonly LoggerInterface   $logger
    )
    {
        foreach ($this->models as $model) {
            $this->statuses[$model->model_id] = OnlineStatus::UNKNOWN;
        }
    }

    /**
     * Returns the underlying model collection.
     */
    public function getModels(): AiModelCollection
    {
        return $this->models;
    }

    /**
     * Sets the online status for the given model.
     *
     * If the model is not part of this collection the call is a no-op and a DEBUG message is
     * logged.  This guards against provider responses that include models not yet imported into
     * the database.
     */
    public function set(string|int|AiModel $modelOrId, OnlineStatus $status): void
    {
        $model = $this->findModel($modelOrId);
        if ($model === null) {
            $this->logger->debug("Attempted to set status for model {$modelOrId} which is not in the collection. Ignoring.");
            return;
        }

        $this->logger->info("Model {$model->model_id} status set {$status->value}");
        $this->statuses[$model->model_id] = $status;
    }

    /**
     * Convenience wrapper — marks the given model as {@see OnlineStatus::ONLINE}.
     */
    public function setOnline(string|int|AiModel $modelOrId): void
    {
        $this->set($modelOrId, OnlineStatus::ONLINE);
    }

    /**
     * Convenience wrapper — marks the given model as {@see OnlineStatus::OFFLINE}.
     */
    public function setOffline(string|int|AiModel $modelOrId): void
    {
        $this->set($modelOrId, OnlineStatus::OFFLINE);
    }

    /**
     * Convenience wrapper — resets the given model's status to {@see OnlineStatus::UNKNOWN}.
     */
    public function setUnknown(string|int|AiModel $modelOrId): void
    {
        $this->set($modelOrId, OnlineStatus::UNKNOWN);
    }

    /**
     * Marks every model in the collection as {@see OnlineStatus::ONLINE}.
     *
     * Useful for providers that do not expose a per-model health endpoint and consider all
     * configured models online as long as the provider itself is reachable.
     */
    public function setAllOnline(): void
    {
        foreach ($this->models as $model) {
            $this->set($model, OnlineStatus::ONLINE);
        }
    }

    /**
     * Marks every model in the collection as {@see OnlineStatus::OFFLINE}.
     */
    public function setAllOffline(): void
    {
        foreach ($this->models as $model) {
            $this->set($model, OnlineStatus::OFFLINE);
        }
    }

    /**
     * Resets every model in the collection back to {@see OnlineStatus::UNKNOWN}.
     */
    public function setAllUnknown(): void
    {
        foreach ($this->models as $model) {
            $this->set($model, OnlineStatus::UNKNOWN);
        }
    }

    /**
     * Promotes all models still in {@see OnlineStatus::UNKNOWN} to {@see OnlineStatus::OFFLINE}.
     *
     * Called by {@see \App\Services\Ai\StatusCheck\ModelStatusUpdater} after the provider adapter
     * has finished its check.  A model that remains UNKNOWN was not mentioned in the provider's
     * response, which is treated as an implicit offline signal.
     *
     * Unlike {@see set()}, this method writes directly to the status map without going through the
     * model lookup or logging at INFO level, since it is a bulk finalization step rather than an
     * explicit per-model decision.
     */
    public function setAllUnknownToOffline(): void
    {
        foreach ($this->statuses as $modelId => $status) {
            if ($status === OnlineStatus::UNKNOWN) {
                $this->statuses[$modelId] = OnlineStatus::OFFLINE;
            }
        }
    }

    /**
     * Yields every model's final online status as `model_id => OnlineStatus` pairs.
     *
     * All models are included regardless of whether their status changed from the initial
     * UNKNOWN default, so the caller always receives a complete snapshot suitable for
     * persisting to the database.
     *
     * @return iterable<string, OnlineStatus>
     */
    public function getChangedList(): iterable
    {
        yield from $this->statuses;
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Traversable
    {
        return $this->models->getIterator();
    }

    /**
     * Resolves a model from the collection by model instance, string model_id, or integer
     * primary key.  Returns `null` when no matching model is found.
     */
    private function findModel(string|int|AiModel $model): AiModel|null
    {
        if ($model instanceof AiModel) {
            return $this->models->getModel($model->model_id);
        }

        return $this->models->getModel($model);
    }
}
