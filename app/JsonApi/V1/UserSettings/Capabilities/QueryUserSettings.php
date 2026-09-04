<?php

declare(strict_types=1);

namespace App\JsonApi\V1\UserSettings\Capabilities;

use App\Services\Users\Settings\Registries\UserSettingsRegistry;
use App\Services\Users\Settings\UserSettingsService;
use App\Services\Users\Settings\Values\NamespacedUserSettings;
use LaravelJsonApi\NonEloquent\Capabilities\QueryAll;

/**
 * Query-all capability of the `user-settings` resource: the index returns one
 * aggregate per registered namespace, so the frontend fetches everything in a single
 * request.
 */
class QueryUserSettings extends QueryAll
{
    public function __construct(
        private readonly UserSettingsRegistry $registry,
        private readonly UserSettingsService $userSettingsService,
    ) {
        parent::__construct();
    }

    /**
     * {@inheritDoc}
     */
    public function get(): iterable
    {
        foreach ($this->registry->namespaces() as $namespace) {
            yield $this->aggregate($namespace);
        }
    }

    /**
     * Builds the namespace aggregate: one instance per settings class of the namespace,
     * hydrated for the current caller.
     */
    private function aggregate(string $namespace): NamespacedUserSettings
    {
        $settings = [];

        foreach ($this->registry->classesForNamespace($namespace) as $settingsClass) {
            $settings[$settingsClass::publicKey()] = $this->userSettingsService->get($settingsClass);
        }

        return new NamespacedUserSettings($namespace, $settings);
    }
}
