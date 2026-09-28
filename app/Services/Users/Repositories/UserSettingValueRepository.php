<?php

declare(strict_types=1);

namespace App\Services\Users\Repositories;

use App\Models\User;
use App\Models\UserSettingValue;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepositoryWithContextualScopes;
use Illuminate\Support\Facades\DB;
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
     * Applies one diff-based save for the user and namespace: `$changed` is upserted
     * (existing rows overwritten, missing rows created) and `$removed` is deleted.
     *
     * Both halves run in a single transaction because they are one logical write — a
     * save that landed only its upserts would leave a property that reverted to its
     * class default still showing the old customized value until the next save.
     * Timestamps are maintained by Eloquent's upsert.
     *
     * @param array<string, null|string> $changed setting key → serialized value
     * @param list<string> $removed setting keys whose rows are to be deleted
     */
    public function persistForUser(User $user, string $namespace, array $changed, array $removed): void
    {
        if ([] === $changed && [] === $removed) {
            return;
        }

        DB::transaction(function () use ($user, $namespace, $changed, $removed): void {
            if ([] !== $changed) {
                $rows = [];

                foreach ($changed as $key => $value) {
                    $rows[] = [
                        'user_id' => $user->id,
                        'namespace' => $namespace,
                        'key' => $key,
                        'value' => $value,
                    ];
                }

                $this->getQueryWithoutContextualScopes('access')
                    ->upsert($rows, ['user_id', 'namespace', 'key'], ['value']);
            }

            if ([] !== $removed) {
                $this->getQueryWithoutContextualScopes('access')
                    ->where('user_id', $user->id)
                    ->where('namespace', $namespace)
                    ->whereIn('key', $removed)
                    ->delete();
            }
        });
    }

    /**
     * Deletes every settings row of the given user, across all namespaces.
     * Used by the user-removal cleanup listener.
     */
    public function deleteAllForUser(User $user): void
    {
        $this->getQueryWithoutContextualScopes('access')
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

        return $rows->map(static fn(UserSettingValue $row) => $row->user_id);
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
        $this->getQueryWithoutContextualScopes('access')
            ->upsert(
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
