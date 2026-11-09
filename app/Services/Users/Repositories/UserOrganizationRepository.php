<?php

declare(strict_types=1);

namespace App\Services\Users\Repositories;

use App\Models\Organization;
use App\Models\User;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepository;
use App\Services\System\Database\Eloquent\Repositories\Attributes\UseModel;

#[UseModel(Organization::class)]
class UserOrganizationRepository extends AbstractRepository
{
    public function findByName(string $name): ?Organization
    {
        /** @var null|Organization */
        return $this->getQuery()->where('name', $name)->first();
    }

    /**
     * Attaches the user to the organization as a member, unless they are already a member.
     * An existing membership, including its role, is never touched.
     */
    public function attachUserAsMember(User $user, Organization $organization): void
    {
        if ($user->organizations()->whereKey($organization->id)->exists()) {
            return;
        }

        $user->organizations()->attach($organization->id, ['role' => 'member']);
    }
}
