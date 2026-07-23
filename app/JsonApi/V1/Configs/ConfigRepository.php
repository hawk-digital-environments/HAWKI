<?php
declare(strict_types=1);


namespace App\JsonApi\V1\Configs;


use App\Services\Config\Registries\PublicConfigRegistry;
use LaravelJsonApi\NonEloquent\AbstractRepository;

class ConfigRepository extends AbstractRepository
{
    public function __construct(
        private readonly PublicConfigRegistry $publicConfigRegistry
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function find(string $resourceId): ?object
    {
        if ($resourceId !== 'public') {
            return null;
        }

        return $this->publicConfigRegistry;
    }
}
