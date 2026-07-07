<?php

namespace App\JsonApi\V1\AiModelFlags;

use App\Services\Ai\Models\Flags\AiModelFlagRegistry;
use App\Services\System\JsonApi\NonEloquent\Capabilities\GenericQueryAll;
use LaravelJsonApi\Contracts\Store\QueriesAll;
use LaravelJsonApi\Contracts\Store\QueryManyBuilder;
use LaravelJsonApi\NonEloquent\AbstractRepository;

class AiModelFlagRepository extends AbstractRepository implements QueriesAll
{
    public function __construct(
        private readonly AiModelFlagRegistry $registry
    )
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
