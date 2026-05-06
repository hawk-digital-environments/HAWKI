<?php

declare(strict_types=1);

namespace App\Services\Assistant\Repositories;

use App\Models\Organization;
use App\Models\User;

readonly class OrganizationRepository
{
    public function getForUser(User $user): ?Organization
    {
        return $user->organizations()->first();
    }

    public function usersShareOrganization(User $userA, User $userB): bool
    {
        if ($userA->id === $userB->id) {
            return true;
        }

        return $userA->organizations()
            ->whereHas('users', fn ($q) => $q->where('users.id', $userB->id))
            ->exists();
    }
}
