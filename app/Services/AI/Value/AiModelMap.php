<?php
declare(strict_types=1);


namespace App\Services\AI\Value;


use Traversable;

readonly class AiModelMap implements \JsonSerializable, \IteratorAggregate, \Countable
{
    
    public function __construct(
        /** @var array<string, AiModel> */
        private array $map = []
    )
    {
    }
    
    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->map);
    }
    
    public function withModel(string $key, AiModel $model): self
    {
        return new self(array_merge(
            $this->map,
            [$key => $model]
        ));
    }
    
    public function toIdArray(): array
    {
        return array_map(
            static fn(AiModel $model) => $model->getId(),
            $this->map
        );
    }
    
    public function toArray(): array
    {
        return array_map(
            static fn(AiModel $model) => $model->toArray(),
            $this->map
        );
    }
    
    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
    
    /**
     * @inheritDoc
     */
    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->map);
    }
}
