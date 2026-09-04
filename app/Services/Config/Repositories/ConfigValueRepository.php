<?php

declare(strict_types=1);

namespace App\Services\Config\Repositories;

use App\Models\ConfigValue;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepository;
use App\Services\System\Time\CarbonClockInterface;

/**
 * Repository for the app-config rows in `config_values`.
 *
 * The table is unused groundwork for the planned migration of the file-based config onto
 * the database — in the meantime this repository is used exclusively by the
 * {@see \App\Services\System\Database\SettingsAndConfig\ConfigSchema} migration tooling.
 * All methods operate globally on a namespace; there is no per-user scoping here.
 *
 * @extends AbstractRepository<ConfigValue>
 */
class ConfigValueRepository extends AbstractRepository
{
    public function __construct(private readonly CarbonClockInterface $clock)
    {
    }

    /**
     * Returns all raw serialized strings stored for the namespace, keyed by config key.
     *
     * @return array<string, null|string>
     */
    public function getRawRows(string $namespace): array
    {
        return ConfigValue::query()
            ->where('namespace', $namespace)
            ->pluck('value', 'key')
            ->all();
    }

    /**
     * Inserts a single row with ON CONFLICT DO NOTHING — any existing row (admin save,
     * previous migration) is preserved. Used for PHP class defaults.
     */
    public function insertIgnore(string $namespace, string $key, ?string $value): void
    {
        ConfigValue::insertOrIgnore([
            'namespace' => $namespace,
            'key' => $key,
            'value' => $value,
            'created_at' => $this->clock->now(),
            'updated_at' => $this->clock->now(),
        ]);
    }

    /**
     * Upserts a single row — overwrites any existing value. Used for values explicitly
     * set inside a migration closure (structural transforms).
     */
    public function upsertValue(string $namespace, string $key, ?string $value): void
    {
        ConfigValue::upsert(
            [
                [
                    'namespace' => $namespace,
                    'key' => $key,
                    'value' => $value,
                ],
            ],
            ['namespace', 'key'],
            ['value'],
        );
    }

    /**
     * Deletes a single config key of the namespace.
     */
    public function deleteForNamespaceAndKey(string $namespace, string $key): void
    {
        ConfigValue::query()
            ->where('namespace', $namespace)
            ->where('key', $key)
            ->delete();
    }

    /**
     * Deletes every row of the namespace (uninstall).
     */
    public function deleteForNamespace(string $namespace): void
    {
        ConfigValue::query()
            ->where('namespace', $namespace)
            ->delete();
    }

    /**
     * Moves all rows of one namespace to another namespace, in a single statement —
     * used when a config class is moved to another plugin.
     */
    public function renameNamespace(string $fromNamespace, string $toNamespace): void
    {
        ConfigValue::query()
            ->where('namespace', $fromNamespace)
            ->update(['namespace' => $toNamespace]);
    }
}
