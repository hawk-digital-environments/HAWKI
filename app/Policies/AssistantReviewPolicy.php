<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Assistants\AssistantReview;
use App\Models\User;
use App\Policies\Traits\AuthorizeViewAnyForUserTrait;
use App\Services\Admin\Permission;
use App\Services\Organizations\OrgMembership;
use Illuminate\Auth\Access\HandlesAuthorization;

class AssistantReviewPolicy
{
    use HandlesAuthorization;
    use AuthorizeViewAnyForUserTrait;

    public function __construct(private readonly OrgMembership $orgMembership)
    {
    }

    public function view(User $user, AssistantReview $review): bool
    {
        if ($review->assistant->creator_id === $user->id) {
            return true;
        }

        if ($this->isSiteAdmin($user)) {
            return true;
        }

        return $this->orgMembership->isAdminOf($user, $review->assistant->organization_id);
    }

    public function viewAssistant(User $user, AssistantReview $review): bool
    {
        return $this->view($user, $review);
    }

    public function update(User $user, AssistantReview $review): bool
    {
        if ($this->isSiteAdmin($user)) {
            return true;
        }

        return $this->orgMembership->isAdminOf($user, $review->assistant->organization_id);
    }

    private function isSiteAdmin(User $user): bool
    {
        return $user->can(Permission::ASSISTANTS_MANAGE->value);
    }
}

