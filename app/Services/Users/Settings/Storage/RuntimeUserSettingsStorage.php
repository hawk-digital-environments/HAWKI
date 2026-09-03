<?php

declare(strict_types=1);

namespace App\Services\Users\Settings\Storage;

use App\Services\Users\Settings\Contracts\UserSettingsStorageInterface;
use Illuminate\Container\Attributes\Singleton;

/**
 * In-memory storage for guests in CLI context (console commands, tests) — the fallback
 * when no session is available.
 *
 * Values live for the remainder of the process only. The storage is a singleton, so
 * multiple resolutions within one process share the same data array; the user-settings
 * service keys its identity map by the `'runtime'` storage id.
 */
#[Singleton()]
class RuntimeUserSettingsStorage implements UserSettingsStorageInterface
{
    /**
     * @var array<string, array<string, null|string>> namespace → (property → raw value)
     */
    private array $data = [];

    /**
     * {@inheritDoc}
     */
    public function loadRaw(string $namespace): array
    {
        return $this->data[$namespace] ?? [];
    }

    /**
     * {@inheritDoc}
     */
    public function persistChanged(string $namespace, array $changed): void
    {
        $this->data[$namespace] = array_merge($this->loadRaw($namespace), $changed);
    }

    /**
     * {@inheritDoc}
     */
    public function removeKeys(string $namespace, array $keys): void
    {
        $remaining = $this->loadRaw($namespace);

        foreach ($keys as $key) {
            unset($remaining[$key]);
        }

        $this->data[$namespace] = $remaining;
    }

    /**
     * {@inheritDoc}
     */
    public function getStorageId(): string
    {
        return 'runtime';
    }
}
