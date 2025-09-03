<?php
declare(strict_types=1);


namespace App\Services\AI\Value;


readonly class AvailableAiModels implements \JsonSerializable
{
    public function __construct(
        public AiModelCollection $models,
        public AiModelMap        $defaultModels,
        public AiModelMap        $systemModels
    )
    {
    }
    
    public function toArray(): array
    {
        return [
            'models' => $this->models->toArray(),
            'defaultModels' => $this->defaultModels->toIdArray(),
            'systemModels' => $this->systemModels->toIdArray(),
        ];
    }
    
    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
