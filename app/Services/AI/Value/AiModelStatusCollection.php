<?php
declare(strict_types=1);


namespace App\Services\AI\Value;


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
 */
final class AiModelStatusCollection implements \IteratorAggregate
{
    private array $statuses = [];

    public function __construct(
        private readonly AiModelCollection $models
    )
    {
    }

    /**
     * Set the status of a model.
     * @param AiModel $model
     * @param ModelOnlineStatus $status
     * @return void
     */
    public function setStatus(AiModel $model, ModelOnlineStatus $status): void
    {
        if ($this->models->getModel($model->getId()) === null) {
            return;
        }

        $this->statuses[$model->getId()] = $status;
    }

    /**
     * The same as {@see self::setStatus} but accepts a model ID instead of a model instance.
     * If the model ID is not in the collection, nothing happens.
     * The model ID will be matched using the {@see AiModel::idMatches()} method for flexible matching.
     * @param string $id
     * @param ModelOnlineStatus $status
     * @return void
     */
    public function setStatusById(string $id, ModelOnlineStatus $status): void
    {
        $model = $this->models->getModel($id);
        if ($model === null) {
            return;
        }

        $this->setStatus($model, $status);
    }

    /**
     * Get the status of a model.
     * If the model is not in the collection, ModelOnlineStatus::UNKNOWN is returned.
     * @param AiModel $model
     * @return ModelOnlineStatus
     */
    public function getStatus(AiModel $model): ModelOnlineStatus
    {
        return $this->statuses[$model->getId()] ?? ModelOnlineStatus::UNKNOWN;
    }

    /**
     * Set all models to online.
     * @return void
     */
    public function setAllOnline(): void
    {
        foreach ($this->models as $model) {
            $this->setStatus($model, ModelOnlineStatus::ONLINE);
        }
    }

    /**
     * Set all models to offline.
     * @return void
     */
    public function setAllOffline(): void
    {
        foreach ($this->models as $model) {
            $this->setStatus($model, ModelOnlineStatus::OFFLINE);
        }
    }

    /**
     * Get all model IDs in the collection.
     * @return iterable<string>
     */
    public function getModelIds(): iterable
    {
        foreach ($this->models as $model) {
            yield $model->getId();
        }
    }

    /**
     * @inheritDoc
     * @return Traversable<AiModel>
     */
    public function getIterator(): Traversable
    {
        return $this->models->getIterator();
    }
}
