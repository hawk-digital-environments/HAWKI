<?php

declare(strict_types=1);

namespace App\Policies\Traits;

use Illuminate\Support\Facades\Gate;

/**
 * Authorize creation of a child resource against a parent model's policy
 * ability (e.g. an avatar's creation gated by the parent assistant's `update`).
 *
 * The {@see \Illuminate\Auth\Access\Gate} invokes a resource policy's
 * `create(User $user)` without the parent model, so the parent is resolved from
 * the request's JSON:API relationship payload. This keeps the cross-resource
 * authorization decision inside the policy rather than in a controller hook.
 *
 * Authorization runs before validation (the policy's `create()` is evaluated
 * during request resolution, ahead of `rules()`). A request that cannot be
 * authorized against a concrete, existing parent is therefore rejected with
 * 403 rather than reaching validation with 422 — fail-closed by design, hiding
 * the resource's existence rather than leaking validation hints.
 */
trait AuthorizesCreationAgainstRelatedTrait
{
    /**
     * @param string $relation     JSON:API relationship key carrying the parent id
     * @param class-string $parentClass parent model class to resolve and authorize against
     * @param string $ability      parent policy ability to check (e.g. `update`, `view`)
     *
     * @return bool true when creation may proceed; false (or a thrown
     *              AuthorizationException via {@see Gate::authorize()}) otherwise
     */
    protected function authorizeCreationAgainstRelated(string $relation, string $parentClass, string $ability): bool
    {
        $parentId = request()->input("data.relationships.{$relation}.data.id");

        if (!is_numeric($parentId)) {
            return false;
        }

        $parent = $parentClass::find((int) $parentId);

        if (null === $parent) {
            return false;
        }

        Gate::authorize($ability, $parent);

        return true;
    }
}
