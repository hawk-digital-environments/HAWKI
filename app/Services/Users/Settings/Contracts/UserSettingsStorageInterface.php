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
 * The contract is deliberately the minimum a backend must offer: read a namespace,
 * apply one save to it, and identify itself. Anything beyond that (listing namespaces,
 * moving rows between backends) is an implementation concern of the backends that
 * actually need it — see {@see \App\Services\Users\Settings\Storage\SessionUserSettingsStorage::promoteTo()}.
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
     * Applies one save to the namespace: upserts `$changed` and deletes `$removed`.
     *
     * Both halves are the two sides of a single diff-based save — customized values go
     * in, values that reverted to their class default come out (sparse storage) — so
     * backends that can apply them atomically must do so. A half-applied save would
     * leave a reverted property showing its old customized value until the next write.
     *
     * Keys mentioned in neither argument are left untouched, which is what lets two
     * parallel saves of different properties merge cleanly.
     *
     * Passing two empty arrays must be a no-op: the service saves unconditionally, so
     * a save that changed nothing may not cost a write, a session round-trip or a
     * transaction.
     *
     * @param array<string, null|string> $changed raw serialized values to upsert, keyed by property name
     * @param list<string>               $removed property names whose stored rows are to be deleted
     */
    public function persist(string $namespace, array $changed, array $removed): void;

    /**
     * Returns the identity of this storage backend — used by the service to key its
     * per-user identity map, so a user switch inside a long-lived worker can never leak
     * another user's instance. Must be stable for the same caller within one process.
     *
     * Example values: `'database:42'` (the user id), `'session'`, `'runtime'`.
     */
    public function getStorageId(): string;
}
