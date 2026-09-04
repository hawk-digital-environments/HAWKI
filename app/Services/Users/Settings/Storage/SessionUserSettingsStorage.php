<?php

declare(strict_types=1);

namespace App\Services\Users\Settings\Storage;

use App\Services\Users\Settings\Contracts\UserSettingsStorageInterface;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Contracts\Session\Session;

/**
 * Session-backed storage for guests outside CLI context.
 *
 * Raw serialized strings live under a single `user_settings` session key,
 * mapping namespace → (property → serialized value). Guest settings are **not
 * promoted to the database** automatically — when the user registers,
 * {@see \App\Services\Users\Settings\UserSettingsService::persistSessionSettings()}
 * converts them via {@see inheritFrom()}.
 *
 * The storage is only selected by {@see \App\Services\Users\Settings\UserSettingsService}
 * when the user context resolves no user and the process is not running in the console
 * (the session is available whenever we are not in a CLI context).
 */
#[Singleton()]
class SessionUserSettingsStorage implements UserSettingsStorageInterface
{
    private const string SESSION_ROOT_KEY = 'user_settings';

    public function __construct(private readonly Session $session)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function loadRaw(string $namespace): array
    {
        return $this->sessionData()[$namespace] ?? [];
    }

    /**
     * {@inheritDoc}
     */
    public function persistChanged(string $namespace, array $changed): void
    {
        $this->put(array_merge($this->sessionData(), [
            $namespace => array_merge($this->sessionData()[$namespace] ?? [], $changed),
        ]),);
    }

    /**
     * {@inheritDoc}
     */
    public function removeKeys(string $namespace, array $keys): void
    {
        $data = $this->sessionData();

        foreach ($keys as $key) {
            unset($data[$namespace][$key]);
        }

        $this->put($data);
    }

    /**
     * {@inheritDoc}
     */
    public function getStorageId(): string
    {
        return 'session';
    }

    /**
     * {@inheritDoc}
     */
    public function getNamespaces(): array
    {
        return array_keys($this->sessionData());
    }

    /**
     * {@inheritDoc}
     */
    public function inheritFrom(UserSettingsStorageInterface $source): void
    {
        foreach ($source->getNamespaces() as $namespace) {
            $this->persistChanged($namespace, $source->loadRaw($namespace));
        }
    }

    /**
     * Returns the full session data map (namespace → property → raw value),
     * defaulting to an empty array.
     *
     * @return array<string, array<string, null|string>>
     */
    private function sessionData(): array
    {
        $stored = $this->session->get(self::SESSION_ROOT_KEY);

        return \is_array($stored) ? $stored : [];
    }

    /**
     * Writes the full session data map back.
     *
     * @param array<string, array<string, null|string>> $data
     */
    private function put(array $data): void
    {
        $this->session->put(self::SESSION_ROOT_KEY, $data);
    }
}
