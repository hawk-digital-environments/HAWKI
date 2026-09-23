<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Admin;

use LaravelJsonApi\Contracts\Store\Repository as RepositoryContract;
use LaravelJsonApi\Core\Schema\Schema as BaseSchema;
use LaravelJsonApi\NonEloquent\Fields\Attribute;
use LaravelJsonApi\NonEloquent\Fields\ID;

abstract class Schema extends BaseSchema
{
    protected const ATTRIBUTES = [];
    protected const ID_PATTERN = '[0-9]+';
    protected const REPOSITORY = '';

    /**
     * @var class-string<Record>
     */
    public static string $model;
    protected bool $selfLink = false;

    final public function fields(): array
    {
        return [
            ID::make()->matchAs(static::ID_PATTERN),
            ...array_map(static fn (string $name) => Attribute::make($name), static::ATTRIBUTES),
        ];
    }

    final public static function resource(): string
    {
        return Resource::class;
    }

    final public function repository(): RepositoryContract
    {
        return (new Repository(app(static::REPOSITORY), static::$model))
            ->withServer($this->server)
            ->withSchema($this);
    }

    final public function authorizable(): bool
    {
        // Section repositories authorize before reading or mutating admin records.
        return false;
    }
}
