<?php

namespace App\JsonApi\V1\Migrations;

use App\Services\Frontend\Migrations\Values\MigrationToApply;
use App\Services\System\Container\ServiceLocatorTrait;
use LaravelJsonApi\Contracts\Store\Repository;
use LaravelJsonApi\Core\Schema\Schema;
use LaravelJsonApi\NonEloquent\Fields\Attribute;
use LaravelJsonApi\NonEloquent\Fields\ID;

class MigrationSchema extends Schema
{
    use ServiceLocatorTrait;

    /**
     * The model the schema corresponds to.
     *
     * @var string
     */
    public static string $model = MigrationToApply::class;

    /**
     * Get the resource fields.
     *
     * @return array
     */
    public function fields(): array
    {
        return [
            ID::make(),
            Attribute::make('data')
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
        return $this->getService(MigrationRepository::class)
            ->withServer($this->server)
            ->withSchema($this);
    }
}
