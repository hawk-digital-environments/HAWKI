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
    use ArrayUserSettingsStorageTrait;

    /**
     * @var array<string, array<string, null|string>> namespace → (property → raw value)
     */
    private array $data = [];

    /**
     * {@inheritDoc}
     */
    public function getStorageId(): string
    {
        return 'runtime';
    }

    /**
     * {@inheritDoc}
     */
    protected function readAll(): array
    {
        return $this->data;
    }

    /**
     * {@inheritDoc}
     */
    protected function writeAll(array $data): void
    {
        $this->data = $data;
    }
}
