<?php

declare(strict_types=1);

namespace App\Services\Users\Settings;

use App\Models\User;
use App\Services\System\Container\SystemEnvironment;
use App\Services\System\Database\SettingsAndConfig\Values\SettingsValueComparator;
use App\Services\System\UserTypes\UserContext;
use App\Services\Users\Exceptions\InvalidUserSettingsClassException;
use App\Services\Users\Settings\Contracts\UserSettingsStorageInterface;
use App\Services\Users\Settings\Storage\DatabaseUserSettingsStorage;
use App\Services\Users\Settings\Storage\RuntimeUserSettingsStorage;
use App\Services\Users\Settings\Storage\SessionUserSettingsStorage;
use Illuminate\Container\Attributes\Singleton;

/**
 * Central access point for per-user settings objects.
 *
 * Settings classes (subclasses of {@see AbstractUserSettings}) are hydrated from the
 * raw rows of the storage backend that matches the caller: the **database** for fully
 * authenticated users, the **session** for guests outside CLI, and a **runtime array**
 * for guests in CLI. The backend is selected **per call** from the {@see UserContext} —
 * never at container level — because neither singletons nor scoped instances are reset
 * when the authenticated user changes (only queue workers flush scoped instances
 * between jobs), and a backend resolved once would stick to the first user for the
 * lifetime of a worker process.
 *
 * Reads are cached in an identity map keyed by **storage identity + class** — never by
 * class alone — so a user switch inside a long-lived process can never leak another
 * user's instance. The intended write pattern mirrors the planned `ConfigService`:
 * mutate the instance returned by {@see get()} and call {@see save()}.
 *
 * Persistence is **diff-based (sparse storage)**: a row exists for a user and property
 * iff the current value differs from the class default. On save, differing properties
 * are upserted and properties that reverted to their defaults are deleted, so the table
 * stays small and future default changes propagate to every user who never customized.
 *
 * @api
 *
 * @see AbstractUserSettings
 * @see UserSettingsStorageInterface
 */
#[Singleton()]
class UserSettingsService
{
    /**
     * @var array<string, AbstractUserSettings> keyed by "<storageId>|<class>"
     */
    private array $map = [];

    public function __construct(
        private readonly UserContext $userContext,
        private readonly SystemEnvironment $systemEnvironment,
        private readonly DatabaseUserSettingsStorage $databaseStorage,
        private readonly SessionUserSettingsStorage $sessionStorage,
        private readonly RuntimeUserSettingsStorage $runtimeStorage,
        private readonly SettingsValueComparator $comparator,
    ) {
    }

    /**
     * Returns the settings instance of the given class for the current caller, hydrating
     * it from the matching storage backend on first access.
     *
     * Properties missing from the stored rows retain their declared PHP defaults —
     * a caller with zero stored rows gets a fully-defaulted instance. Repeated calls
     * with the same class return the same instance until the user or backend changes.
     *
     * @template T of AbstractUserSettings
     *
     * @param class-string<T> $settingsClass
     *
     * @throws InvalidUserSettingsClassException when $settingsClass does not extend {@see AbstractUserSettings}
     *
     * @return T
     */
    public function get(string $settingsClass): AbstractUserSettings
    {
        $settingsClass = $this->assertSettingsClass($settingsClass);
        $storage = $this->selectStorage();
        $mapKey = $this->mapKey($storage, $settingsClass);

        if (isset($this->map[$mapKey])) {
            /** @var T */
            return $this->map[$mapKey];
        }

        $settings = $settingsClass::fromStringArray($storage->loadRaw($settingsClass::namespace()));

        return $this->map[$mapKey] = $settings;
    }

    /**
     * Persists the given settings instance for the current caller using diff-based
     * (sparse) storage: properties that differ from the class defaults are upserted,
     * properties that match the defaults again are deleted from storage.
     *
     * Stale rows for properties that no longer exist on the class are removed as well —
     * under the sparse-storage rule they can never match a default, so they are garbage.
     * Writes touch only the diff keys, so two parallel saves of different properties
     * for the same user merge cleanly.
     *
     * @throws InvalidUserSettingsClassException when $settings is not an {@see AbstractUserSettings} subclass
     */
    public function save(AbstractUserSettings $settings): void
    {
        $settingsClass = $this->assertSettingsClass($settings::class);
        $storage = $this->selectStorage();
        $namespace = $settingsClass::namespace();

        $diff = $this->comparator->diffObjects(
            $settings,
            // The defaults instance: every property at its declared PHP default.
            $settingsClass::fromStringArray([]),
        );

        $serialized = $settings->toStringArray();

        $changed = [];

        foreach (array_keys($serialized) as $property) {
            if ($diff->isDifferent($property)) {
                $changed[$property] = $serialized[$property];
            }
        }

        $removeKeys = [];

        foreach (array_keys($storage->loadRaw($namespace)) as $property) {
            if (!$diff->isDifferent($property)) {
                $removeKeys[] = $property;
            }
        }

        $storage->persistChanged($namespace, $changed);
        $storage->removeKeys($namespace, $removeKeys);

        $this->map[$this->mapKey($storage, $settingsClass)] = $settings;
    }

    /**
     * Validates the class extends the settings base and returns it narrowed.
     *
     * @template T of AbstractUserSettings
     *
     * @param class-string<T> $settingsClass
     *
     * @return class-string<T>
     */
    private function assertSettingsClass(string $settingsClass): string
    {
        if (!is_subclass_of($settingsClass, AbstractUserSettings::class)) {
            throw InvalidUserSettingsClassException::forClass($settingsClass);
        }

        return $settingsClass;
    }

    /**
     * Selects the storage backend for the current call — the only robust point of
     * decision, because the authenticated user can change within the lifetime of a
     * process (queue workers, long-running contexts).
     *
     * - Fully authenticated {@see User} → database storage.
     * - Guest (including {@see \App\Services\System\UserTypes\Values\RegisteringUser})
     *   outside CLI → session storage (the session is available whenever we are not in
     *   a CLI context).
     * - Guest in CLI → runtime storage.
     */
    private function selectStorage(): UserSettingsStorageInterface
    {
        if ($this->userContext->getUser() instanceof User) {
            return $this->databaseStorage;
        }

        return $this->systemEnvironment->runningInConsole()
            ? $this->runtimeStorage
            : $this->sessionStorage;
    }

    /**
     * Returns the identity-map key for the settings class on the given storage.
     *
     * @param class-string $settingsClass
     */
    private function mapKey(UserSettingsStorageInterface $storage, string $settingsClass): string
    {
        return $storage->getStorageId() . '|' . $settingsClass;
    }
}
