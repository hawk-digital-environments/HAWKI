<?php

declare(strict_types=1);

namespace App\Services\Users\Settings\Contracts;

/**
 * Raw row storage for user settings — the backend the
 * {@see \App\Services\Users\Settings\UserSettingsService} talks to.
 *
 * Implementations deal exclusively in **raw serialized strings** keyed by property name;
 * hydration, casting and serialization stay with the settings classes via
 * {@see \App\Utils\Casts\AbstractCastableObject}. The service selects the right backend
 * per call (database for authenticated users, session for guests outside CLI, runtime
 * array for guests in CLI) — never at container level, because neither singletons nor
 * scoped instances are reset when the authenticated user changes.
 *
 * @see \App\Services\Users\Settings\Storage\DatabaseUserSettingsStorage
 * @see \App\Services\Users\Settings\Storage\SessionUserSettingsStorage
 * @see \App\Services\Users\Settings\Storage\RuntimeUserSettingsStorage
 *
 * @api
 */
interface UserSettingsStorageInterface
{
    /**
     * Returns all raw serialized strings stored for the namespace, keyed by property
     * name. Missing properties are simply absent — they keep their class defaults at
     * hydration time.
     *
     * @return array<string, null|string>
     */
    public function loadRaw(string $namespace): array;

    /**
     * Upserts the given raw serialized strings for the namespace — only the keys the
     * diff produced; existing rows for other keys are untouched.
     *
     * @param array<string, null|string> $changed
     */
    public function persistChanged(string $namespace, array $changed): void;

    /**
     * Removes the given keys' rows from the namespace — used when a value reverted to
     * its class default (sparse storage).
     *
     * @param list<string> $keys
     */
    public function removeKeys(string $namespace, array $keys): void;

    /**
     * Returns the identity of this storage backend — used by the service to key its
     * per-user identity map, so a user switch inside a long-lived worker can never leak
     * another user's instance. Must be stable for the same caller within one process.
     *
     * Example values: `'database:42'` (the user id), `'session'`, `'runtime'`.
     */
    public function getStorageId(): string;
}
