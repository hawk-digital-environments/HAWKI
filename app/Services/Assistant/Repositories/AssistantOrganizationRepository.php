<?php

declare(strict_types=1);

namespace App\Services\Assistant\Repositories;

use App\Models\Organization;
use App\Models\User;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepository;

class AssistantOrganizationRepository extends AbstractRepository
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
            ->whereHas('users', static fn ($q) => $q->where('users.id', $userB->id))
            ->exists();
    }
}
