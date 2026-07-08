<?php
declare(strict_types=1);


namespace App\Services\Ai\StatusCheck;


use App\Collections\AiModelCollection;
use App\Models\Ai\AiModel;
use App\Services\Ai\StatusCheck\Values\ModelDemand;
use Psr\Log\LoggerInterface;

/**
 * @implements \IteratorAggregate<int, AiModel>
 */
class AiModelDemandCollection implements \IteratorAggregate
{
    /**
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

    public function getModels(): AiModelCollection
    {
        return $this->models;
    }

    public function set(string|int|AiModel $modelOrId, ModelDemand $demand): void
    {
        $model = $this->findModel($modelOrId);
        if ($model === null) {
            $this->logger->debug("Attempted to set demand for model {$model} which is not in the collection. Ignoring.");
            return;
        }

        $this->logger->info("Model {$model->model_id} demand set to {$demand->value}");
        $this->demands[$model->model_id] = $demand;
    }

    public function setLow(string|int|AiModel $modelOrId): void
    {
        $this->set($modelOrId, ModelDemand::LOW);
    }

    public function setMedium(string|int|AiModel $modelOrId): void
    {
        $this->set($modelOrId, ModelDemand::MEDIUM);
    }

    public function setHigh(string|int|AiModel $modelOrId): void
    {
        $this->set($modelOrId, ModelDemand::HIGH);
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->models->all());
    }

    /**
     * @return iterable<string, ModelDemand>
     */
    public function getChangedList(): iterable
    {
        yield from $this->demands;
    }

    private function findModel(string|int|AiModel $model): AiModel|null
    {
        if ($model instanceof AiModel) {
            return $this->models->getModel($model->model_id);
        }

        return $this->models->getModel($model);
    }
}
