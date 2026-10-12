<?php

declare(strict_types=1);

namespace App\JsonApi\V1\UserSettings\Capabilities;

use App\Services\System\Http\RequestToObjectMapper;
use App\Services\Users\Settings\AbstractUserSettings;
use App\Services\Users\Settings\Registries\UserSettingsRegistry;
use App\Services\Users\Settings\UserSettingsService;
use App\Services\Users\Settings\Values\NamespacedUserSettings;
use LaravelJsonApi\NonEloquent\Capabilities\CrudResource;

/**
 * CRUD capability of the `user-settings` resource — the write path. Every write goes
 * through the settings classes: validated data is typed onto the instances via the
 * {@see RequestToObjectMapper} and persisted through the
 * {@see UserSettingsService}'s diff-based (sparse) storage. The table is never touched
 * directly by the API layer.
 *
 * Validated data arrives keyed by public key (`{'core': {'theme': 'dark'}}`); only
 * properties carrying a `#[ValidateInput]` attribute can ever occur in it.
 */
class CrudUserSetting extends CrudResource
{
    public function __construct(
        private readonly UserSettingsRegistry $registry,
        private readonly UserSettingsService $userSettingsService,
        private readonly RequestToObjectMapper $mapper,
    ) {
        parent::__construct();
    }

    /**
     * Creates a namespace's settings from their defaults plus the validated overrides —
     * functionally a full PATCH. The namespace comes from the client-generated resource
     * id (`data.id`), which the request validation already constrained to registered
     * namespaces.
     */
    public function create(array $validatedData): object
    {
        $namespace = $validatedData['id'] ?? null;

        \assert(\is_string($namespace) && '' !== $namespace);

        // Fresh default instances per settings class of the namespace.
        $settings = [];

        foreach ($this->registry->classesForNamespace($namespace) as $settingsClass) {
            $settings[$settingsClass::publicKey()] = $settingsClass::fromStringArray([]);
        }

        return $this->apply($namespace, $settings, $validatedData);
    }

    /**
     * Updates a namespace's settings: for every settings class the request touched,
     * the validated values are typed onto a clone of the current instance and saved.
     * Untouched classes keep their values (JSON:API PATCH semantics: missing
     * attributes keep the current value).
     */
    public function update(object $settings, array $validatedData): object
    {
        \assert($settings instanceof NamespacedUserSettings);

        return $this->apply($settings->namespace, $settings->settings, $validatedData);
    }

    /**
     * Applies the validated per-class partials onto the instances and persists the
     * touched ones. Untouched classes keep their identity-mapped instance untouched —
     * no rehydration, no save.
     *
     * @param array<string, AbstractUserSettings> $settings      instances keyed by public key
     * @param array<string, mixed>                $validatedData validated data keyed by public key
     */
    private function apply(string $namespace, array $settings, array $validatedData): NamespacedUserSettings
    {
        $updated = [];

        foreach ($settings as $publicKey => $instance) {
            $partial = $validatedData[$publicKey] ?? [];

            $updatedInstance = \is_array($partial) && [] !== $partial
                ? $this->mapper->mapOnto($instance, $partial)
                : $instance;

            if ($updatedInstance !== $instance) {
                $this->userSettingsService->save($updatedInstance);
            }

            $updated[$publicKey] = $updatedInstance;
        }

        return new NamespacedUserSettings($namespace, $updated);
    }
}
