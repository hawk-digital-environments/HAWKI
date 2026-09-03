<?php

declare(strict_types=1);

namespace App\Services\System\Database\SettingsAndConfig\Contracts;

use App\Services\System\Database\SettingsAndConfig\ConfigAndSettingsBlueprint;
use App\Utils\Casts\AbstractCastableObject;

/**
 * Shared contract for **both** schema facades — {@see ConfigSchema} (app config rows)
 * and {@see UserSettingsSchema} (per-user settings rows) — which manage their rows
 * through migrations: the `Schema::`-style tooling for first-class database rows.
 *
 * Both flavours expose the same developer-facing contract;
 * they differ only in their target table and seeding behaviour:
 *
 * - `create()` — first-time install: insert the class's PHP property defaults (app
 *   config) / validated no-op (user settings — defaults materialize at hydrate time).
 * - `update()` — subsequent migration: run the closure once over the existing rows to
 *   add new properties or transform existing ones.
 * - `dropKey()` — remove a single property's rows.
 * - `drop()` — remove every row of the class (uninstall).
 * - `rename()` — move all rows to the namespace derived from the new class, required
 *   whenever a config/settings class is moved to another plugin.
 *
 * @see \App\Services\System\Database\SettingsAndConfig\ConfigSchema
 * @see \App\Services\System\Database\SettingsAndConfig\UserSettingsSchema
 *
 * @api
 */
interface ConfigAndSettingsSchemaInterface
{
    /**
     * Registers a config/settings class with its default rows (app config) or validates
     * it (user settings — there is nothing to seed per user).
     *
     * @template T of AbstractCastableObject
     *
     * @param class-string<T>                                   $settingsClass
     * @param null|(\Closure(ConfigAndSettingsBlueprint): void) $fn
     */
    public static function create(string $settingsClass, ?\Closure $fn = null): void;

    /**
     * Migrates existing rows of the class: new properties are inserted with their PHP
     * defaults, values explicitly set inside the closure are upserted, and untouched
     * existing rows are preserved.
     *
     * @template T of AbstractCastableObject
     *
     * @param class-string<T>                                   $settingsClass
     * @param null|(\Closure(ConfigAndSettingsBlueprint): void) $fn
     */
    public static function update(string $settingsClass, ?\Closure $fn = null): void;

    /**
     * Deletes a single property's rows (for user settings: of all users).
     *
     * @template T of AbstractCastableObject
     *
     * @param class-string<T> $settingsClass
     */
    public static function dropKey(string $settingsClass, string $key): void;

    /**
     * Deletes every row of the class (for user settings: of all users). Used in `down()`
     * or uninstall migrations.
     *
     * @template T of AbstractCastableObject
     *
     * @param class-string<T> $settingsClass
     */
    public static function drop(string $settingsClass): void;

    /**
     * Moves all rows of the old class to the namespace derived from the new class.
     * Required whenever a config/settings class is moved to another plugin — the derived
     * namespace changes with the owning package, which would otherwise orphan the rows.
     *
     * @template T of AbstractCastableObject
     *
     * @param class-string<T> $oldClass
     * @param class-string<T> $newClass
     */
    public static function rename(string $oldClass, string $newClass): void;
}
