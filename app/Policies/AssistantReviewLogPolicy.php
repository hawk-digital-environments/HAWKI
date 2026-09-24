<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Assistants\AssistantReviewLog;
use App\Models\User;
use App\Policies\Traits\AuthorizeViewAnyForUserTrait;
use App\Services\Admin\Permission;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * The administrative log is admin-facing only — unlike `assistant_reviews`
 * (whose current `reason` a creator can already see), the log additionally
 * names the acting admin, so it stays behind `assistants.manage` rather than
 * the creator/org-admin "privileged" tier the rest of the review data uses.
 */
class AssistantReviewLogPolicy
{
    use HandlesAuthorization;
    use AuthorizeViewAnyForUserTrait;

    public function view(User $user, AssistantReviewLog $log): bool
    {
        return $user->can(Permission::ASSISTANTS_MANAGE->value);
    }
}
