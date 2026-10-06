<?php

declare(strict_types=1);

namespace App\JsonApi\V1\UserSettings;

use App\JsonApi\V1\UserSettings\Capabilities\CrudUserSetting;
use App\JsonApi\V1\UserSettings\Capabilities\QueryUserSettings;
use App\Services\Users\Settings\Registries\UserSettingsRegistry;
use App\Services\Users\Settings\UserSettingsService;
use App\Services\Users\Settings\Values\NamespacedUserSettings;
use LaravelJsonApi\Contracts\Store\CreatesResources;
use LaravelJsonApi\Contracts\Store\QueriesAll;
use LaravelJsonApi\Contracts\Store\UpdatesResources;
use LaravelJsonApi\NonEloquent\AbstractRepository;
use LaravelJsonApi\NonEloquent\Concerns\HasCrudCapability;

/**
 * Repository of the `user-settings` JSON:API resource — the non-Eloquent store over the
 * {@see UserSettingsRegistry} and {@see UserSettingsService}.
 *
 * Resource identity: one resource per **namespace** (the resource id), each aggregating
 * the settings classes of that namespace keyed by their public keys. This is what
 * enables incremental updates — a single namespace can be (re)fetched without pulling
 * everything — and the index returns all namespaces as a list.
 */
class UserSettingRepository extends AbstractRepository implements CreatesResources, QueriesAll, UpdatesResources
{
    use HasCrudCapability;

    public function __construct(
        private readonly UserSettingsRegistry $registry,
        private readonly UserSettingsService $userSettingsService,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function find(string $resourceId): ?object
    {
        $classes = $this->registry->classesForNamespace($resourceId);

        if ([] === $classes) {
            return null;
        }

        $settings = [];

        foreach ($classes as $settingsClass) {
            $settings[$settingsClass::publicKey()] = $this->userSettingsService->get($settingsClass);
        }

        return new NamespacedUserSettings($resourceId, $settings);
    }

    /**
     * {@inheritDoc}
     */
    public function queryAll(): QueryUserSettings
    {
        return QueryUserSettings::make()
            ->withServer($this->server())
            ->withSchema($this->schema());
    }

    /**
     * {@inheritDoc}
     */
    protected function crud(): CrudUserSetting
    {
        return CrudUserSetting::make();
    }
}
