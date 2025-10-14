<?php
declare(strict_types=1);


namespace App\Services\AI\Value;


use Traversable;

readonly class AiModelCollection implements \IteratorAggregate, \Countable
{
    /**
     * @var array<string,AiModel> $models
     */
    private array $models;
    
    public function __construct(
        AiModel ...$models
    )
    {
        $modelsByIds = [];
        foreach ($models as $model) {
            $modelsByIds[$model->getId()] = $model;
        }
        $this->models = $modelsByIds;
    }
    
    public function withModel(AiModel $model): self
    {
        return new self(...array_values(array_merge($this->models, [$model->getId() => $model])));
    }
    
    public function getModel(string $id): ?AiModel
    {
        if (isset($this->models[$id])) {
            return $this->models[$id];
        }
        
        foreach ($this->models as $model) {
            if ($model->idMatches($id)) {
                return $model;
            }
        }
        
        return null;
    }
    
    /**
     * @inheritDoc
     * @return iterable<AiModel>
     */
    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->models);
    }
    
    public function count(): int
    {
        return count($this->models);
    }
    
    public function toArray(): array
    {
        return array_map(
            static fn(AiModel $model) => $model->toArray(),
            array_values($this->models)
        );
    }
}
