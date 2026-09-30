<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantFieldFlag;
use App\Models\User;
use App\Policies\Traits\AuthorizesCreationAgainstRelatedTrait;
use App\Policies\Traits\AuthorizeViewAnyForUserTrait;
use App\Services\Admin\Permission;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Readable by whoever may see the assistant's other privileged relationships
 * (creator, org admin, site admin) so the creator sees the feedback; writable
 * only by a site admin — flags are a review tool, not something a creator or
 * org admin can plant or dismiss on their own assistant.
 */
class AssistantFieldFlagPolicy
{
    use HandlesAuthorization;
    use AuthorizeViewAnyForUserTrait;
    use AuthorizesCreationAgainstRelatedTrait;

    public function view(User $user, AssistantFieldFlag $flag): bool
    {
        return $this->assistant()->viewAssistantFieldFlags($user, $flag->assistant);
    }

    public function create(User $user): bool
    {
        if (!$user->can(Permission::ASSISTANTS_MANAGE->value)) {
            return false;
        }

        return $this->authorizeCreationAgainstRelated('assistant', Assistant::class, 'view');
    }

    public function update(User $user, AssistantFieldFlag $flag): bool
    {
        return $user->can(Permission::ASSISTANTS_MANAGE->value);
    }

    public function delete(User $user, AssistantFieldFlag $flag): bool
    {
        return $user->can(Permission::ASSISTANTS_MANAGE->value);
    }

    private function assistant(): AssistantPolicy
    {
        return app(AssistantPolicy::class);
    }
}
