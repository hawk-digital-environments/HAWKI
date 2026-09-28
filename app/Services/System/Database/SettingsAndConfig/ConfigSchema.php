<?php

declare(strict_types=1);

namespace App\Services\System\Database\SettingsAndConfig;

use App\Services\Config\AbstractConfig;
use App\Services\Config\Repositories\ConfigValueRepository;

/**
 * Schema facade for app-config rows in the `config_values` table.
 *
 * Usage inside a migration:
 *
 * ```php
 * // First-time install — inserts all PHP class defaults with ON CONFLICT DO NOTHING,
 * // so existing rows (admin saves, previous migrations) are never overwritten:
 * ConfigSchema::create(AiConfig::class);
 *
 * // Adding a property / updating a default in a later version — new properties are
 * // inserted with their PHP defaults, existing rows stay untouched:
 * ConfigSchema::update(AiConfig::class);
 *
 * // Structural transform — values set inside the closure are upserted (overwrite):
 * ConfigSchema::update(AiConfig::class, function (ConfigAndSettingsBlueprint $b): void {
 *     $b->max_tokens = (int) $b->getRaw('token_limit');
 * });
 * ConfigSchema::dropKey(AiConfig::class, 'token_limit');
 *
 * // Moving the class to another plugin — migrates every row's namespace:
 * ConfigSchema::rename(OldConfig::class, NewConfig::class);
 * ```
 *
 * The `config_values` table currently exists as unused groundwork — no runtime code reads
 * it; the actual migration of the file-based config onto it is a future task. This facade
 * makes that migration a pure data task and is fully testable end-to-end.
 *
 * @api
 *
 * @see ConfigAndSettingsBlueprint
 */
class ConfigSchema extends AbstractConfigAndSettingsSchema
{
    public static function create(string $settingsClass, ?\Closure $fn = null): void
    {
        // Identical behaviour to update() — the name distinction is purely semantic:
        // create = first install, update = subsequent migration.
        self::flush(self::assertClassExtends($settingsClass, AbstractConfig::class), $fn);
    }

    public static function update(string $settingsClass, ?\Closure $fn = null): void
    {
        self::flush(self::assertClassExtends($settingsClass, AbstractConfig::class), $fn);
    }

    public static function dropKey(string $settingsClass, string $key): void
    {
        $settingsClass = self::assertClassExtends($settingsClass, AbstractConfig::class);

        self::repository()->deleteForNamespaceAndKey($settingsClass::namespace(), $key);
    }

    public static function drop(string $settingsClass): void
    {
        $settingsClass = self::assertClassExtends($settingsClass, AbstractConfig::class);

        self::repository()->deleteForNamespace($settingsClass::namespace());
    }

    public static function rename(string $oldClass, string $newClass): void
    {
        $oldClass = self::assertClassExtends($oldClass, AbstractConfig::class);
        $newClass = self::assertClassExtends($newClass, AbstractConfig::class);

        self::repository()->renameNamespace($oldClass::namespace(), $newClass::namespace());
    }

    /**
     * Runs the optional closure over the namespace's current rows, then writes the
     * result: PHP class defaults first with INSERT IGNORE (preserving any existing
     * row), explicitly set values afterwards as upserts (overwriting — the structural
     * transforms of a migration closure).
     *
     * @param class-string<AbstractConfig> $settingsClass
     */
    private static function flush(string $settingsClass, ?\Closure $fn): void
    {
        $repository = self::repository();
        $namespace = $settingsClass::namespace();

        $blueprint = new ConfigAndSettingsBlueprint($settingsClass, $repository->getRawRows($namespace));

        if (null !== $fn) {
            $fn($blueprint);
        }

        // Insert all PHP class defaults — ON CONFLICT DO NOTHING. Preserves any
        // existing DB value, including admin saves and previous migrations.
        foreach (self::defaults($settingsClass)->toStringArray() as $property => $serializedDefault) {
            $repository->insertIgnore($namespace, $property, $serializedDefault);
        }

        // Explicit values from the migration closure — upsert (overwrite). These are the
        // only writes that overwrite, reserved for structural transforms.
        foreach (self::serializePending($settingsClass, $blueprint->getPending()) as $property => $serializedValue) {
            $repository->upsertValue($namespace, $property, $serializedValue);
        }
    }

    /**
     * Resolves the row repository from the container — available in migration context.
     */
    private static function repository(): ConfigValueRepository
    {
        return app(ConfigValueRepository::class);
    }
}
