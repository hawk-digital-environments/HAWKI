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
 * hands them over via {@see promoteTo()}.
 *
 * The storage is only selected by {@see \App\Services\Users\Settings\UserSettingsService}
 * when the user context resolves no user and the process is not running in the console
 * (the session is available whenever we are not in a CLI context).
 */
#[Singleton()]
class SessionUserSettingsStorage implements UserSettingsStorageInterface
{
    use ArrayUserSettingsStorageTrait;
    private const string SESSION_ROOT_KEY = 'user_settings';

    public function __construct(private readonly Session $session)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getStorageId(): string
    {
        return 'session';
    }

    /**
     * Hands every namespace stored for the guest over to the target backend and empties
     * the session afterwards — the registration-time promotion of a guest's settings
     * onto the freshly created user.
     *
     * Everything is copied before anything is cleared, so an aborted run can only leave
     * the guest's settings duplicated, never lost. The stored rows are already sparse
     * (the diff-based save only writes customized keys), so the copy preserves sparsity.
     *
     * Deliberately **not** on {@see UserSettingsStorageInterface}: the session is the
     * only backend that is ever promoted, and
     * {@see \App\Services\Users\Settings\UserSettingsService::persistSessionSettings()}
     * is the only caller. Putting it on the contract would force two other backends to
     * implement a method nobody calls.
     */
    public function promoteTo(UserSettingsStorageInterface $target): void
    {
        $data = $this->readAll();

        if ([] === $data) {
            return;
        }

        foreach ($data as $namespace => $rows) {
            $target->persist($namespace, $rows, []);
        }

        // Cleared wholesale rather than key by key, so the same session can never
        // resurface promoted values as guest defaults.
        $this->writeAll([]);
    }

    /**
     * {@inheritDoc}
     */
    protected function readAll(): array
    {
        $stored = $this->session->get(self::SESSION_ROOT_KEY);

        return \is_array($stored) ? $stored : [];
    }

    /**
     * {@inheritDoc}
     */
    protected function writeAll(array $data): void
    {
        $this->session->put(self::SESSION_ROOT_KEY, $data);
    }
}
