<?php

namespace App\JsonApi\V1\AiToolCapabilities;

use App\Services\Ai\Tools\Values\AiToolCapabilityDefinition;
use App\Services\System\Container\ServiceLocatorTrait;
use LaravelJsonApi\Contracts\Store\Repository;
use LaravelJsonApi\Core\Schema\Schema;
use LaravelJsonApi\NonEloquent\Fields\Attribute;
use LaravelJsonApi\NonEloquent\Fields\ID;

class AiToolCapabilitySchema extends Schema
{
    use ServiceLocatorTrait;

    /**
     * The model the schema corresponds to.
     *
     * @var string
     */
    public static string $model = AiToolCapabilityDefinition::class;

    /**
     * Get the resource fields.
     *
     * @return array
     */
    public function fields(): array
    {
        return [
            ID::make(),
            Attribute::make('default_value'),
            Attribute::make('title_label'),
            Attribute::make('description_label'),
            Attribute::make('icon_path'),
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

    /**
     * @inheritDoc
     */
    public function repository(): ?Repository
    {
        return $this->getService(AiToolCapabilityRepository::class)
            ->withServer($this->server)
            ->withSchema($this);
    }
}
