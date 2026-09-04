<?php

declare(strict_types=1);

namespace App\JsonApi\V1\UserSettings;

use App\Services\Users\Settings\Registries\UserSettingsRegistry;
use App\Services\Users\Settings\Values\NamespacedUserSettings;
use LaravelJsonApi\Contracts\Server\Server;
use LaravelJsonApi\Contracts\Store\Repository;
use LaravelJsonApi\Core\Schema\Schema;
use LaravelJsonApi\Eloquent\Fields\ArrayList;
use LaravelJsonApi\NonEloquent\Fields\ID;

/**
 * Schema of the `user-settings` JSON:API resource — a non-Eloquent resource that
 * forces every read and write through the settings classes; the `user_setting_values`
 * table is never touched directly by the API layer.
 *
 * Resource identity: the resource id is the **namespace** (e.g. `hawki-core`), so a
 * single namespace can be (re)fetched incrementally. Fields are one `ArrayList` per
 * registered settings class, keyed by the class's globally-unique public key — the
 * registry provides them, so the schema carries no per-class code.
 *
 * Both the registry and the repository are injected via constructor (not the service
 * locator): schemas are container-made by the JSON:API layer, and `fields()` already
 * runs during route registration, where no locator-registered services exist.
 */
class UserSettingSchema extends Schema
{
    /**
     * The model the schema corresponds to — the per-namespace aggregate.
     */
    public static string $model = NamespacedUserSettings::class;

    public function __construct(
        private readonly UserSettingsRegistry $registry,
        private readonly UserSettingRepository $repository,
        Server $server,
    ) {
        parent::__construct($server);
    }

    /**
     * Get the resource fields.
     */
    public function fields(): array
    {
        $fields = [
            // Client-generated ids: a store request selects the namespace via data.id.
            ID::make()->matchAs('[a-z0-9-]+')->clientIds(),
        ];

        foreach ($this->registry->classesByPublicKey() as $publicKey => $settingsClass) {
            $fields[] = ArrayList::make($publicKey);
        }

        return $fields;
    }

    public function repository(): ?Repository
    {
        return $this->repository
            ->withServer($this->server)
            ->withSchema($this);
    }

    public function authorizable(): bool
    {
        // HAWKI handles auth at the middleware layer (UserContext/UsageContext); access
        // to *whose* settings these are is the storage-backend selection itself.
        return false;
    }
}
