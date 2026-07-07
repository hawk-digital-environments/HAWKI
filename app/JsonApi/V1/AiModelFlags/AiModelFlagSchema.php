<?php

namespace App\JsonApi\V1\AiModelFlags;

use App\Services\Ai\Models\Flags\Values\ModelFlagDefinition;
use App\Services\System\Container\ServiceLocatorTrait;
use LaravelJsonApi\Contracts\Store\Repository;
use LaravelJsonApi\Core\Schema\Schema;
use LaravelJsonApi\NonEloquent\Fields\Attribute;
use LaravelJsonApi\NonEloquent\Fields\ID;

class AiModelFlagSchema extends Schema
{
    use ServiceLocatorTrait;

    /**
     * The model the schema corresponds to.
     *
     * @var string
     */
    public static string $model = ModelFlagDefinition::class;

    /**
     * Get the resource fields.
     *
     * @return array
     */
    public function fields(): array
    {
        return [
            ID::make(),
            Attribute::make('title_label'),
            Attribute::make('description_label'),
            Attribute::make('color_code'),
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
        return $this->getService(AiModelFlagRepository::class)
            ->withServer($this->server)
            ->withSchema($this);
    }
}
