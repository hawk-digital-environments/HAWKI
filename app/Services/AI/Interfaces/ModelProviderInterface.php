<?php

namespace App\Services\AI\Interfaces;

use App\Services\AI\Value\AiModelCollection;
use App\Services\AI\Value\ProviderConfig;

interface ModelProviderInterface
{
    public function __construct(ProviderConfig $config);
    
    /**
     * Returns the configuration for the AI provider
     * @return ProviderConfig
     */
    public function getConfig(): ProviderConfig;
    
    /**
     * Retrieve available models from the AI provider
     */
    public function getModels(): AiModelCollection;
}
