<?php

namespace App\JsonApi\V1\AiToolCapabilities;

use App\Services\Ai\Registries\AiModelCapabilityRegistry;
use App\Services\System\JsonApi\NonEloquent\Capabilities\GenericQueryAll;
use LaravelJsonApi\Contracts\Store\QueriesAll;
use LaravelJsonApi\Contracts\Store\QueryManyBuilder;
use LaravelJsonApi\NonEloquent\AbstractRepository;

class AiToolCapabilityRepository extends AbstractRepository implements QueriesAll
{
    public function __construct(private readonly AiModelCapabilityRegistry $registry)
    {
    }

    /**
     * @inheritDoc
     */
    public function find(string $resourceId): ?object
    {
        return $this->registry->getDefinition($resourceId);
    }

    /**
     * @inheritDoc
     */
    public function queryAll(): QueryManyBuilder
    {
        return GenericQueryAll::make(
            collect($this->registry->getIterator())
                ->map(fn($_, string $key) => $this->registry->getDefinition($key))
        );
    }
}
