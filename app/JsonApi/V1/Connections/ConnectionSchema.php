<?php

namespace App\JsonApi\V1\Connections;

use App\Services\Frontend\Connection\Values\Connection;
use App\Services\System\Container\ServiceLocatorTrait;
use LaravelJsonApi\Contracts\Store\Repository;
use LaravelJsonApi\Core\Schema\Schema;
use LaravelJsonApi\NonEloquent\Fields\Attribute;
use LaravelJsonApi\NonEloquent\Fields\ID;

class ConnectionSchema extends Schema
{
    use ServiceLocatorTrait;

    /**
     * The model the schema corresponds to.
     *
     * @var string
     */
    public static string $model = Connection::class;

    /**
     * Get the resource fields.
     *
     * @return array
     */
    public function fields(): array
    {
        return [
            ID::make()->matchAs('[^/]*?'),
            Attribute::make('type'),
            Attribute::make('version'),
            Attribute::make('locale'),
            Attribute::make('userinfo'),
            Attribute::make('ext_app_secrets'),
            Attribute::make('ext_app_connect_request'),
            Attribute::make('migrations_to_apply'),
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
            // WhereIdIn::make($this)
        ];
    }

    /**
     * @inheritDoc
     */
    public function repository(): ?Repository
    {
        return $this->getService(ConnectionRepository::class)
            ->withServer($this->server)
            ->withSchema($this);
    }

    /**
     * @inheritDoc
     */
    public function authorizable(): bool
    {
        return false;
    }
}
