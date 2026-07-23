<?php

namespace App\JsonApi\V1\Configs;

use App\Services\Config\Registries\PublicConfigRegistry;
use App\Services\System\Container\ServiceLocatorTrait;
use LaravelJsonApi\Contracts\Store\Repository;
use LaravelJsonApi\Core\Schema\Schema;
use LaravelJsonApi\Eloquent\Fields\ArrayList;
use LaravelJsonApi\NonEloquent\Fields\ID;

class ConfigSchema extends Schema
{
    use ServiceLocatorTrait;

    /**
     * The model the schema corresponds to.
     *
     * @var string
     */
    public static string $model = PublicConfigRegistry::class;

    /**
     * Get the resource fields.
     *
     * @return array
     */
    public function fields(): array
    {
        return [
            ID::make()->matchAs('[a-z]+'),
            ArrayList::make('list'),
        ];
    }

    /**
     * Get the resource filters.
     *
     * @return array
     */
    public function filters(): array
    {
        return [
        ];
    }

    public function repository(): ?Repository
    {
        return $this->getService(ConfigRepository::class)
            ->withServer($this->server)
            ->withSchema($this);
    }

    public function authorizable(): bool
    {
        return false;
    }
}
