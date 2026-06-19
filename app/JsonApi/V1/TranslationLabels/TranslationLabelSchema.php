<?php

namespace App\JsonApi\V1\TranslationLabels;

use App\Services\System\Container\ServiceLocatorTrait;
use LaravelJsonApi\Contracts\Store\Repository;
use LaravelJsonApi\Core\Schema\Schema;
use LaravelJsonApi\NonEloquent\Fields\Attribute;
use LaravelJsonApi\NonEloquent\Fields\ID;

class TranslationLabelSchema extends Schema
{
    use ServiceLocatorTrait;

    /**
     * The model the schema corresponds to.
     *
     * @var string
     */
    public static string $model = TranslationLabelsDto::class;

    /**
     * Get the resource fields.
     *
     * @return array
     */
    public function fields(): array
    {
        return [
            ID::make()->matchAs('[a-zA-Z]{2}[\-_][a-zA-Z]{2}'),
            Attribute::make('locale'),
            Attribute::make('labels'),
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

    public function authorizable(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function repository(): ?Repository
    {
        return $this->getService(TranslationLabelRepository::class)
            ->withServer($this->server)
            ->withSchema($this);
    }
}
