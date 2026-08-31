<?php

declare(strict_types=1);

namespace App\Services\Assistant\Repositories;

use App\Models\Organization;
use App\Models\User;
use App\Services\Assistant\Exceptions\AmbiguousOrganizationException;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepository;

class AssistantOrganizationRepository extends AbstractRepository
{
    /**
     * Returns the single organization the user belongs to, or null if they
     * belong to none.
     *
     * @throws AmbiguousOrganizationException when the user belongs to more than one organization and the caller did not disambiguate via {@see getForUserById()}
     */
    public function getForUser(User $user): ?Organization
    {
        $organizations = $user->organizations()->limit(2)->get();

        if ($organizations->isEmpty()) {
            return null;
        }

        if (2 <= $organizations->count()) {
            throw AmbiguousOrganizationException::forUser($user);
        }

        return $organizations->first();
    }

    /**
     * Returns the explicitly-requested organization if the user is a member,
     * otherwise null. Use this when the caller can supply an organization id
     * (e.g. via the request body) to disambiguate a multi-org user.
     */
    public function getForUserById(User $user, int $organizationId): ?Organization
    {
        return $user->organizations()->where('organizations.id', $organizationId)->first();
    }

    public function usersShareOrganization(User $userA, User $userB): bool
    {
        if ($userA->id === $userB->id) {
            return true;
        }

        return $userA->organizations()
            ->whereHas('users', static fn ($q) => $q->where('users.id', $userB->id))
            ->exists();
    }
}
