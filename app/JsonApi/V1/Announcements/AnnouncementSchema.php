<?php

namespace App\JsonApi\V1\Announcements;

use App\Services\Announcements\Values\AnnouncementForUser;
use LaravelJsonApi\Contracts\Store\Repository;
use LaravelJsonApi\Core\Schema\Schema;
use LaravelJsonApi\NonEloquent\Fields\Attribute;
use LaravelJsonApi\NonEloquent\Fields\ID;

class AnnouncementSchema extends Schema
{
    /**
     * The model the schema corresponds to.
     *
     * @var string
     */
    public static string $model = AnnouncementForUser::class;

    /**
     * Get the resource fields.
     *
     * @return array
     */
    public function fields(): array
    {
        return [
            ID::make(),
            Attribute::make('title'),
            Attribute::make('type'),
            Attribute::make('is_forced'),
            Attribute::make('anchor'),
            Attribute::make('starts_at'),
            Attribute::make('expires_at'),
            Attribute::make('is_active'),
            Attribute::make('content'),
            Attribute::make('seen_at'),
            Attribute::make('accepted_at'),
            Attribute::make('seen_count'),
        ];
    }

    /**
     * Get the resource filters.
     *
     * @return array
     */
    public function filters(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function repository(): ?Repository
    {
        // Deliberately AnnouncementRepository::make() (container-resolved) instead of the
        // ServiceLocatorTrait used by e.g. MigrationSchema: the trait disables container
        // fallback under PHPUnit, which would break the feature tests hitting this resource.
        return AnnouncementRepository::make()
            ->withServer($this->server)
            ->withSchema($this);
    }
}
