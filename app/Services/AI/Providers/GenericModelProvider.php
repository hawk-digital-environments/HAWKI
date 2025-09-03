<?php
declare(strict_types=1);


namespace App\Services\AI\Providers;


use App\Services\AI\Interfaces\ModelProviderInterface;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiModelCollection;
use App\Services\AI\Value\ProviderConfig;

class GenericModelProvider implements ModelProviderInterface
{
    private ?AiModelCollection $models = null;
    
    public function __construct(
        private readonly ProviderConfig $config
    )
    {
    }
    
    public function getConfig(): ProviderConfig
    {
        return $this->config;
    }
    
    /**
     * @inheritDoc
     */
    public function getModels(): AiModelCollection
    {
        if ($this->models !== null) {
            return $this->models;
        }
        
        $models = [];
        foreach ($this->config->getModels() as $modelConfig) {
            $model = new AiModel($modelConfig);
            if ($model->isActive()) {
                $models[] = $model;
            }
        }
        
        return $this->models = new AiModelCollection(...$models);
    }
}
