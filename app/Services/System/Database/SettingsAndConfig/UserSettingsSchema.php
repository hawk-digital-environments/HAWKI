<?php

declare(strict_types=1);

namespace App\Services\System\Database\SettingsAndConfig;

use App\Services\System\Database\SettingsAndConfig\Exceptions\ConfigAndSettingsSchemaException;
use App\Services\Users\Repositories\UserSettingValueRepository;
use App\Services\Users\Settings\AbstractUserSettings;

/**
 * Schema facade for user-settings rows in the `user_setting_values` table.
 *
 * Usage inside a migration:
 *
 * ```php
 * // Structural transform across every user that has rows for the namespace — the
 * // closure runs once per user, so the developer-facing code is identical to the
 * // ConfigSchema flavour:
 * UserSettingsSchema::update(CoreUserSettings::class, function (UserSettingsBlueprint $b): void {
 *     $b->max_tokens = (int) $b->getRaw('token_limit');
 * });
 * UserSettingsSchema::dropKey(CoreUserSettings::class, 'token_limit');
 *
 * // Moving the class to another plugin — user_id is untouched, so all users migrate
 * // in a single statement:
 * UserSettingsSchema::rename(OldSettings::class, NewSettings::class);
 * ```
 *
 * Behavioural differences to {@see ConfigSchema}:
 *
 * - `create()` is a **validated no-op**: there is nothing to seed per user — a user with
 *   zero rows hydrates a fully-defaulted instance from the class at read time
 *   (`AbstractCastableObject::fromStringArray()` keeps PHP defaults for missing keys).
 * - `update()` iterates **all users that have rows** for the namespace (chunked), one
 *   blueprint per user.
 * - `dropKey()` / `drop()` operate across **all users**.
 *
 * @api
 *
 * @see UserSettingsBlueprint
 */
class UserSettingsSchema extends AbstractConfigAndSettingsSchema
{
    public static function create(string $settingsClass, ?\Closure $fn = null): void
    {
        self::assertClassExtends($settingsClass, AbstractUserSettings::class);

        // Nothing to seed per user — defaults materialize at hydrate time. A closure
        // would silently never run (there is no per-user row set at install time), so
        // passing one is rejected instead of silently ignored.
        if (null !== $fn) {
            throw ConfigAndSettingsSchemaException::forUnsupportedClosure(self::class, 'create');
        }
    }

    public static function update(string $settingsClass, ?\Closure $fn = null): void
    {
        $settingsClass = self::assertClassExtends($settingsClass, AbstractUserSettings::class);

        $repository = self::repository();
        $namespace = $settingsClass::namespace();

        // One blueprint per user that has rows — chunked iteration keeps migrations
        // memory-bounded on large user tables.
        foreach ($repository->getUserIdsForNamespaceLazy($namespace) as $userId) {
            $blueprint = new UserSettingsBlueprint(
                $settingsClass,
                $repository->getRawRowsForUserId($userId, $namespace),
                $userId,
            );

            if (null !== $fn) {
                $fn($blueprint);
            }

            foreach (self::serializePending($settingsClass, $blueprint->getPending()) as $property => $serializedValue) {
                $repository->upsertValueForUserId($userId, $namespace, $property, $serializedValue);
            }
        }
    }

    public static function dropKey(string $settingsClass, string $key): void
    {
        $settingsClass = self::assertClassExtends($settingsClass, AbstractUserSettings::class);

        self::repository()->deleteForNamespaceAndKey($settingsClass::namespace(), $key);
    }

    public static function drop(string $settingsClass): void
    {
        $settingsClass = self::assertClassExtends($settingsClass, AbstractUserSettings::class);

        self::repository()->deleteForNamespace($settingsClass::namespace());
    }

    public static function rename(string $oldClass, string $newClass): void
    {
        $oldClass = self::assertClassExtends($oldClass, AbstractUserSettings::class);
        $newClass = self::assertClassExtends($newClass, AbstractUserSettings::class);

        self::repository()->renameNamespace($oldClass::namespace(), $newClass::namespace());
    }

    /**
     * Resolves the row repository from the container — available in migration context.
     */
    private static function repository(): UserSettingValueRepository
    {
        return app(UserSettingValueRepository::class);
    }
}
