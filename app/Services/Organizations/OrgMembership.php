<?php

declare(strict_types=1);

namespace App\Services\Organizations;

use App\Models\Organization;
use App\Models\User;

/**
 * Single source of truth for "is the user an admin of this organization?".
 *
 * Extracted because {@see \App\Policies\AssistantPolicy::isAdminOf()} and
 * {@see \App\Policies\AssistantReviewPolicy::isOrgAdminOf()} were duplicated
 * copies of the same logic with slightly different signatures. Both now
 * delegate here.
 */
readonly class OrgMembership
{
    public function isAdminOf(User $user, null|int|Organization $organization): bool
    {
        $organizationId = $organization instanceof Organization ? $organization->id : $organization;

        if (null === $organizationId) {
            return false;
        }

        return $user->organizations()
            ->wherePivot('role', 'admin')
            ->where('organizations.id', $organizationId)
            ->exists();
    }

    /**
     * Is the user an admin of at least one organization? The class-level
     * verdict behind "may this user review at all".
     */
    public function isAdminAnywhere(User $user): bool
    {
        return $user->organizations()
            ->wherePivot('role', 'admin')
            ->exists();
    }
}
