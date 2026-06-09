<?php

namespace App\Policies;

use App\Models\Assistants\Review;
use App\Models\User;

class ReviewPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isOrgAdmin($user);
    }

    public function view(User $user, Review $review): bool
    {
        return $this->isOrgAdminOf($user, $review->assistant->organization_id);
    }

    public function viewAssistant(User $user, Review $review): bool
    {
        return $this->view($user, $review);
    }

    public function update(User $user, Review $review): bool
    {
        return $this->isOrgAdminOf($user, $review->assistant->organization_id);
    }

    private function isOrgAdmin(User $user): bool
    {
        return $user->organizations()
            ->wherePivot('role', 'admin')
            ->exists();
    }

    private function isOrgAdminOf(User $user, ?int $organizationId): bool
    {
        if ($organizationId === null) {
            return false;
        }

        return $user->organizations()
            ->wherePivot('role', 'admin')
            ->where('organizations.id', $organizationId)
            ->exists();
    }
}
