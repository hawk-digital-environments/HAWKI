<?php

declare(strict_types=1);

namespace App\Services\Users\Repositories;

use App\Models\User;
use App\Models\UserSettingValue;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepositoryWithContextualScopes;
use Illuminate\Support\LazyCollection;

/**
 * Repository for the per-user settings rows in `user_setting_values`.
 *
 * The table stores one serialized string per (user, namespace, key); serialization,
 * casting and encryption are handled entirely by the settings classes via
 * {@see \App\Utils\Casts\AbstractCastableObject} — this repository only routes rows.
 *
 * Two access flavours live side by side:
 *
 * - **Per-user methods** take the {@see User} explicitly and are used by
 *   {@see \App\Services\Users\Settings\Storage\DatabaseUserSettingsStorage}. In request
 *   context the model's `'access'` contextual scope ({@see \App\Models\Scopes\Generic\BelongsToUserScope})
 *   additionally confines every query to the authenticated user as defense-in-depth;
 *   in CLI context (queue workers, migrations) the scope self-disables, so the explicit
 *   user argument is the only — and authoritative — filter.
 * - **Global methods** operate across all users and exist exclusively for the migration
 *   tooling ({@see \App\Services\System\Database\SettingsAndConfig\UserSettingsSchema});
 *   they disable the `'access'` scope explicitly so they cannot be narrowed by request
 *   context.
 *
 * @extends AbstractRepositoryWithContextualScopes<UserSettingValue>
 */
class UserSettingValueRepository extends AbstractRepositoryWithContextualScopes
{
    // -------------------------------------------------------
    // Per-user access (storage layer)
    // -------------------------------------------------------

    /**
     * Returns all raw serialized strings stored for the given user and namespace,
     * keyed by setting key.
     *
     * @return array<string, null|string>
     */
    public function getRawRowsForUser(User $user, string $namespace): array
    {
        return $this->getRawRowsForUserId($user->id, $namespace);
    }

    /**
     * Returns the distinct namespaces the given user has at least one row in.
     *
     * @return list<string>
     */
    public function getNamespacesForUser(User $user): array
    {
        return $this->getQuery()
            ->where('user_id', $user->id)
            ->distinct()
            ->orderBy('namespace')
            ->pluck('namespace')
            ->all();
    }

    /**
     * Upserts the given raw serialized strings for the user and namespace — existing
     * rows are overwritten, missing rows are created. Timestamps are maintained by
     * Eloquent's upsert.
     *
     * @param array<string, null|string> $values setting key → serialized value
     */
    public function upsertValuesForUser(User $user, string $namespace, array $values): void
    {
        if ([] === $values) {
            return;
        }

        $rows = [];

        foreach ($values as $key => $value) {
            $rows[] = [
                'user_id' => $user->id,
                'namespace' => $namespace,
                'key' => $key,
                'value' => $value,
            ];
        }

        UserSettingValue::upsert($rows, ['user_id', 'namespace', 'key'], ['value']);
    }

    /**
     * Deletes the given setting keys for the user and namespace — used when a value
     * reverted to its class default (sparse storage).
     *
     * @param list<string> $keys
     */
    public function deleteKeysForUser(User $user, string $namespace, array $keys): void
    {
        if ([] === $keys) {
            return;
        }

        $this->getQuery()
            ->where('user_id', $user->id)
            ->where('namespace', $namespace)
            ->whereIn('key', $keys)
            ->delete();
    }

    /**
     * Deletes every settings row of the given user, across all namespaces.
     * Used by the user-removal cleanup listener.
     */
    public function deleteAllForUser(User $user): void
    {
        $this->getQuery()
            ->where('user_id', $user->id)
            ->delete();
    }

    // -------------------------------------------------------
    // Global access (migration tooling)
    // -------------------------------------------------------

    /**
     * Returns the ids of all users that have at least one row in the given namespace,
     * evaluated lazily in chunks so migrations stay memory-bounded on large tables.
     *
     * @return LazyCollection<int, non-negative-int>
     */
    public function getUserIdsForNamespaceLazy(string $namespace): LazyCollection
    {
        /** @var LazyCollection<int, UserSettingValue> $rows the query builder loses the model generic through select()/distinct() */
        $rows = $this->getQueryWithoutContextualScopes('access')
            ->where('namespace', $namespace)
            ->select('user_id')
            ->distinct()
            ->lazyById(500, 'user_id');

        return $rows->map(static fn (UserSettingValue $row) => $row->user_id);
    }

    /**
     * Returns all raw serialized strings stored for the given user id and namespace,
     * keyed by setting key. ID-based variant for the migration tooling, which iterates
     * user ids without hydrating {@see User} models.
     *
     * @return array<string, null|string>
     */
    public function getRawRowsForUserId(int $userId, string $namespace): array
    {
        return $this->getQueryWithoutContextualScopes('access')
            ->where('namespace', $namespace)
            ->where('user_id', $userId)
            ->pluck('value', 'key')
            ->all();
    }

    /**
     * Upserts a single raw serialized string for the given user id and namespace,
     * overwriting any existing row.
     */
    public function upsertValueForUserId(int $userId, string $namespace, string $key, ?string $value): void
    {
        UserSettingValue::upsert(
            [
                [
                    'user_id' => $userId,
                    'namespace' => $namespace,
                    'key' => $key,
                    'value' => $value,
                ],
            ],
            ['user_id', 'namespace', 'key'],
            ['value'],
        );
    }

    /**
     * Deletes a single setting key for **all users** of the given namespace.
     * Used by the schema tooling when a property is removed from a settings class.
     */
    public function deleteForNamespaceAndKey(string $namespace, string $key): void
    {
        $this->getQueryWithoutContextualScopes('access')
            ->where('namespace', $namespace)
            ->where('key', $key)
            ->delete();
    }

    /**
     * Deletes every row of the given namespace, for all users.
     * Used by the schema tooling's `drop()` (uninstall).
     */
    public function deleteForNamespace(string $namespace): void
    {
        $this->getQueryWithoutContextualScopes('access')
            ->where('namespace', $namespace)
            ->delete();
    }

    /**
     * Moves all rows of one namespace to another namespace, for all users at once.
     * Used by the schema tooling when a settings class is moved or renamed — the
     * `user_id` column is untouched, so every user migrates in a single statement.
     */
    public function renameNamespace(string $fromNamespace, string $toNamespace): void
    {
        $this->getQueryWithoutContextualScopes('access')
            ->where('namespace', $fromNamespace)
            ->update(['namespace' => $toNamespace]);
    }
}
