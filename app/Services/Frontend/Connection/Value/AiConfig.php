<?php
declare(strict_types=1);


namespace App\Services\Frontend\Connection\Value;


use App\Services\AI\Value\AvailableAiModels;
use App\Utils\Assert\Assert;
use App\Utils\JsonSerializableTrait;

readonly class AiConfig implements \JsonSerializable
{
    use JsonSerializableTrait;
    
    public array $defaultModels;
    public array $systemModels;
    
    public function __construct(
        public string     $handle,
        AvailableAiModels $models
    )
    {
        Assert::withKeyPrefix(
            static::class,
            fn() => Assert::isNonEmptyString($this->handle, 'handle'),
        );
        
        $this->defaultModels = $models->defaultModels->toIdArray();
        $this->systemModels = $models->systemModels->toIdArray();
    }
}
