<?php
declare(strict_types=1);


namespace App\Services\Ai\StatusCheck;


use App\Collections\AiModelCollection;
use App\Models\Ai\AiModel;
use App\Services\Ai\Values\OnlineStatus;
use Psr\Log\LoggerInterface;
use Traversable;

/**
 * A helper to collect the online status of multiple models.
 * Usage:
 *
 * ```php
 * foreach($aiModelStatusCollection as $model) {
 *      $status = requestStatusFromProvider($model);
 *      $aiModelStatusCollection->setStatus($model, $status);
 * }
 * ```
 * @implements \IteratorAggregate<int, AiModel>
 */
class AiModelOnlineStatusCollection implements \IteratorAggregate
{
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

    public function getModels(): AiModelCollection
    {
        return $this->models;
    }

    /**
     * Set the status of a model.
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

    public function setOnline(string|int|AiModel $modelOrId): void
    {
        $this->set($modelOrId, OnlineStatus::ONLINE);
    }

    public function setOfflineById(string|int|AiModel $modelOrId): void
    {
        $this->set($modelOrId, OnlineStatus::OFFLINE);
    }

    public function setUnknownById(string|int|AiModel $modelOrId): void
    {
        $this->set($modelOrId, OnlineStatus::UNKNOWN);
    }

    /**
     * Set all models to online.
     * @return void
     */
    public function setAllOnline(): void
    {
        foreach ($this->models as $model) {
            $this->set($model, OnlineStatus::ONLINE);
        }
    }

    /**
     * Set all models to offline.
     * @return void
     */
    public function setAllOffline(): void
    {
        foreach ($this->models as $model) {
            $this->set($model, OnlineStatus::OFFLINE);
        }
    }

    public function setAllUnknown(): void
    {
        foreach ($this->models as $model) {
            $this->set($model, OnlineStatus::UNKNOWN);
        }
    }

    public function setAllUnknownToOffline(): void
    {
        foreach ($this->statuses as $modelId => $status) {
            if ($status === OnlineStatus::UNKNOWN) {
                $this->statuses[$modelId] = OnlineStatus::OFFLINE;
            }
        }
    }

    /**
     * Returns all changed statuses as an iterable of model ID => status pairs.
     * Only includes models whose status was changed from the original value in the collection.
     *
     * @return iterable<string, OnlineStatus>
     */
    public function getChangedList(): iterable
    {
        yield from $this->statuses;
    }

    /**
     * @inheritDoc
     * @return Traversable<int, AiModel>
     */
    public function getIterator(): Traversable
    {
        return $this->models->getIterator();
    }

    private function findModel(string|int|AiModel $model): AiModel|null
    {
        if ($model instanceof AiModel) {
            return $this->models->getModel($model->model_id);
        }

        return $this->models->getModel($model);
    }
}
