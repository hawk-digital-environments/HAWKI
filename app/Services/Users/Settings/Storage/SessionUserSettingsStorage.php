<?php

declare(strict_types=1);

namespace App\Services\Users\Settings\Storage;

use App\Services\Users\Settings\Contracts\UserSettingsStorageInterface;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Contracts\Session\Session;

/**
 * Session-backed storage for guests outside CLI context.
 *
 * Raw serialized strings live under a `user_settings.{namespace}` session key. Guest
 * settings are **not promoted to the database** when the user later registers —
 * registration already resets per-user state, so carrying them over would be
 * inconsistent.
 *
 * The storage is only selected by {@see \App\Services\Users\Settings\UserSettingsService}
 * when the user context resolves no user and the process is not running in the console
 * (the session is available whenever we are not in a CLI context).
 */
#[Singleton()]
class SessionUserSettingsStorage implements UserSettingsStorageInterface
{
    private const string SESSION_KEY_PREFIX = 'user_settings.';

    public function __construct(private readonly Session $session)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function loadRaw(string $namespace): array
    {
        $stored = $this->session->get(self::SESSION_KEY_PREFIX . $namespace);

        return \is_array($stored) ? $stored : [];
    }

    /**
     * {@inheritDoc}
     */
    public function persistChanged(string $namespace, array $changed): void
    {
        $this->session->put(
            self::SESSION_KEY_PREFIX . $namespace,
            array_merge($this->loadRaw($namespace), $changed),
        );
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

        $this->session->put(self::SESSION_KEY_PREFIX . $namespace, $remaining);
    }

    /**
     * {@inheritDoc}
     */
    public function getStorageId(): string
    {
        return 'session';
    }
}
