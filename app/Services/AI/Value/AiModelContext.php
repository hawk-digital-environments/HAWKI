<?php
declare(strict_types=1);


namespace App\Services\AI\Value;


use App\Services\AI\Interfaces\ClientInterface;
use App\Services\AI\Interfaces\ModelProviderInterface;

readonly class AiModelContext
{
    public function __construct(
        private AiModel                $model,
        private ModelProviderInterface $provider,
        private \Closure               $clientResolver,
        private \Closure               $statusResolver
    )
    {
    }
    
    public function getProvider(): ModelProviderInterface
    {
        return $this->provider;
    }
    
    public function getStatus(): ModelOnlineStatus
    {
        return ($this->statusResolver)($this->model);
    }
    
    public function getClient(): ClientInterface
    {
        return ($this->clientResolver)($this->model);
    }
}
