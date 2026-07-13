<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Assistants\AssistantReview;
use App\Models\User;

class AssistantReviewPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isOrgAdmin($user);
    }

    public function view(User $user, AssistantReview $review): bool
    {
        if ($review->assistant->creator_id === $user->id) {
            return true;
        }

        return $this->isOrgAdminOf($user, $review->assistant->organization_id);
    }

    public function viewAssistant(User $user, AssistantReview $review): bool
    {
        return $this->view($user, $review);
    }

    public function update(User $user, AssistantReview $review): bool
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
        if (null === $organizationId) {
            return false;
        }

        return $user->organizations()
            ->wherePivot('role', 'admin')
            ->where('organizations.id', $organizationId)
            ->exists();
    }
}
