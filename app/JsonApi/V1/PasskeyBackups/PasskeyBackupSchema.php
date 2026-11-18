<?php

namespace App\JsonApi\V1\PasskeyBackups;

use App\Services\Profile\Values\PasskeyBackupBlob;
use LaravelJsonApi\Contracts\Store\Repository;
use LaravelJsonApi\Core\Schema\Schema;
use LaravelJsonApi\NonEloquent\Fields\Attribute;
use LaravelJsonApi\NonEloquent\Fields\ID;

class PasskeyBackupSchema extends Schema
{
    /**
     * The model the schema corresponds to.
     *
     * @var string
     */
    public static string $model = PasskeyBackupBlob::class;

    /**
     * Get the resource fields.
     *
     * @return array
     */
    public function fields(): array
    {
        return [
            // The only id the repository resolves is `me`; everything else has to reach the
            // repository as well, so it can answer with a plain 404 instead of a route miss
            // that would tell the caller which ids are shaped like a real one.
            ID::make()->matchAs('[^/]+'),
            Attribute::make('ciphertext'),
            Attribute::make('iv'),
            Attribute::make('tag'),
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

    public function authorizable(): bool
    {
        // Ownership is not a policy question here: the repository resolves the backup from the
        // current user, so there is no id that could point at somebody else's row.
        return false;
    }

    /**
     * @inheritDoc
     */
    public function repository(): ?Repository
    {
        // Resolve anew: the repository injects #[CurrentUser], which must not be cached.
        return PasskeyBackupRepository::make()
            ->withServer($this->server)
            ->withSchema($this);
    }
}
