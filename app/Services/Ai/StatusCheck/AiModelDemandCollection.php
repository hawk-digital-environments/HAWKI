<?php
declare(strict_types=1);


namespace App\Services\Ai\StatusCheck;


use App\Collections\AiModelCollection;
use App\Models\Ai\AiModel;
use App\Services\Ai\StatusCheck\Values\ModelDemand;
use Psr\Log\LoggerInterface;

/**
 * Mutable demand registry for a fixed set of {@see AiModel} instances, used during a single
 * model-status-check run.
 *
 * Every model in the supplied collection is initialised to {@see ModelDemand::LOW}.  Provider
 * adapters or event listeners then call {@see set()}, {@see setMedium()}, or {@see setHigh()} to
 * record the demand signal returned by the provider's status endpoint.  After the check run the
 * {@see ModelStatusUpdater} reads back the final state via {@see getChangedList()} and persists
 * it to the database.
 *
 * The collection is iterable and yields {@see AiModel} instances, so adapters can iterate
 * the models they need to check directly on this object:
 *
 * ```php
 * foreach ($demandCollection as $model) {
 *     $demand = $provider->fetchDemand($model->model_id);
 *     $demandCollection->set($model, $demand);
 * }
 * ```
 *
 * Attempts to set demand for a model that is not in the initial collection are silently ignored
 * and logged at DEBUG level, so a provider returning extra model IDs does not cause errors.
 *
 * @implements \IteratorAggregate<int, AiModel>
 */
class AiModelDemandCollection implements \IteratorAggregate
{
    /**
     * Map of model_id → current demand level for every model in the collection.
     *
     * @var array<string, ModelDemand>
     */
    private array $demands = [];

    public function __construct(
        private readonly AiModelCollection $models,
        private readonly LoggerInterface   $logger
    )
    {
        foreach ($this->models as $model) {
            $this->demands[$model->model_id] = ModelDemand::LOW;
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
     * Sets the demand level for the given model.
     *
     * If the model is not part of this collection the call is a no-op and a DEBUG message is
     * logged.  This guards against provider responses that include models not yet imported into
     * the database.
     */
    public function set(string|int|AiModel $modelOrId, ModelDemand $demand): void
    {
        $model = $this->findModel($modelOrId);
        if ($model === null) {
            $this->logger->debug("Attempted to set demand for model {$modelOrId} which is not in the collection. Ignoring.");
            return;
        }

        $this->logger->info("Model {$model->model_id} demand set to {$demand->value}");
        $this->demands[$model->model_id] = $demand;
    }

    /**
     * Convenience wrapper — sets the demand for the given model to {@see ModelDemand::LOW}.
     */
    public function setLow(string|int|AiModel $modelOrId): void
    {
        $this->set($modelOrId, ModelDemand::LOW);
    }

    /**
     * Convenience wrapper — sets the demand for the given model to {@see ModelDemand::MEDIUM}.
     */
    public function setMedium(string|int|AiModel $modelOrId): void
    {
        $this->set($modelOrId, ModelDemand::MEDIUM);
    }

    /**
     * Convenience wrapper — sets the demand for the given model to {@see ModelDemand::HIGH}.
     */
    public function setHigh(string|int|AiModel $modelOrId): void
    {
        $this->set($modelOrId, ModelDemand::HIGH);
    }

    /**
     * @inheritDoc
     * @return \Traversable<int, AiModel>
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->models->all());
    }

    /**
     * Yields every model's final demand level as `model_id => ModelDemand` pairs.
     *
     * All models are included — not just those whose demand changed from the initial LOW default —
     * so the caller can always write a complete snapshot to the database.
     *
     * @return iterable<string, ModelDemand>
     */
    public function getChangedList(): iterable
    {
        yield from $this->demands;
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
