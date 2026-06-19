<?php

namespace App\JsonApi\V1\Connections;

use App\Services\Frontend\Connection\ConnectionFactory;
use LaravelJsonApi\NonEloquent\AbstractRepository;

class ConnectionRepository extends AbstractRepository
{
    public function __construct(
        private readonly ConnectionFactory $factory
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function find(string $resourceId): ?object
    {
        if ($resourceId === 'hawki') {
            return $this->factory->createHawkiConnection();
        }

        return $this->factory->createExtAppConnection($resourceId);
    }
}
