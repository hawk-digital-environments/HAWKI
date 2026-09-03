<?php

declare(strict_types=1);

namespace App\Services\System\Database\SettingsAndConfig;

use App\Services\System\Database\SettingsAndConfig\Exceptions\ConfigAndSettingsSchemaException;
use App\Utils\Casts\AbstractCastableObject;

/**
 * Migration blueprint for structural changes to config/settings classes — shared by
 * **both** flavours: {@see ConfigSchema} runs it once per namespace, while
 * {@see UserSettingsSchema} constructs one {@see UserSettingsBlueprint} per user.
 *
 * Receives the raw stored rows of one namespace (or, for user settings, one namespace of
 * one user) and lets the migration closure read and transform them with property syntax.
 * The schema facade flushes the collected changes after the closure runs — see the
 * concrete schemas for the flushing semantics (default seeding vs. explicit upserts).
 *
 * Property access:
 *
 * - `__get($name)` returns the **typed current DB value** via the class's cast definition,
 *   or the PHP class default when no row exists for the property.
 * - `getRaw($name)` returns the raw serialized string as stored in the database,
 *   bypassing all casts. Use it when the property was removed from the class or its cast
 *   type changed and the old raw format must be read for manual conversion.
 * - `__set($name, $value)` queues a typed value for upsert — these are the only writes
 *   that overwrite existing rows.
 *
 * @template T of AbstractCastableObject
 *
 * @see ConfigSchema
 * @see UserSettingsSchema
 */
class ConfigAndSettingsBlueprint
{
    /**
     * Values explicitly set inside the migration closure — these are upserted
     * (overwrite) on flush.
     *
     * @var array<string, mixed>
     */
    private array $pending = [];

    /**
     * @param class-string<T>            $settingsClass the config/settings class being migrated
     * @param array<string, null|string> $rawRows       raw stored rows, keyed by property name
     */
    public function __construct(
        private readonly string $settingsClass,
        private readonly array $rawRows,
    ) {
    }

    /**
     * Queues a typed value for upsert — overwrites the stored value on flush.
     *
     * @param string $name  property name (must be declared on the class)
     * @param mixed  $value the new typed value
     */
    public function __set(string $name, mixed $value): void
    {
        $this->assertPropertyExists($name);

        $this->pending[$name] = $value;
    }

    /**
     * Returns true when a value was queued for the property via {@see __set()} —
     * the property will be upserted on flush.
     */
    public function __isset(string $name): bool
    {
        return isset($this->pending[$name]);
    }

    /**
     * Returns the typed current DB value for the property, deserialized via the class's
     * cast definition, or the PHP class default when no row exists for it.
     *
     * For properties removed from the class, use {@see getRaw()} instead.
     */
    public function __get(string $name): mixed
    {
        $this->assertPropertyExists($name);

        $raw = $this->rawRows[$name] ?? null;

        if (null === $raw) {
            return $this->defaults()->{$name};
        }

        return $this->settingsClass::fromStringArray([$name => $raw])->{$name};
    }

    /**
     * Returns the raw serialized string stored for the property, bypassing all casts,
     * or null when no row exists. Required when the property was removed from the class
     * or its cast type changed and the old raw format must be read.
     */
    public function getRaw(string $name): ?string
    {
        return $this->rawRows[$name] ?? null;
    }

    /**
     * Returns true when a row exists for the property (independent of its value).
     */
    public function hasRow(string $name): bool
    {
        return \array_key_exists($name, $this->rawRows);
    }

    /**
     * Returns the queued (explicitly set) values, keyed by property name.
     *
     * @return array<string, mixed>
     */
    public function getPending(): array
    {
        return $this->pending;
    }

    /**
     * Returns the typed defaults instance of the migrated class (all PHP class defaults).
     */
    protected function defaults(): AbstractCastableObject
    {
        return $this->settingsClass::fromStringArray([]);
    }

    /**
     * Asserts the property is declared on the class — the typed accessors need it.
     */
    private function assertPropertyExists(string $name): void
    {
        if (!property_exists($this->settingsClass, $name)) {
            throw ConfigAndSettingsSchemaException::forUnknownProperty($this->settingsClass, $name);
        }
    }
}
