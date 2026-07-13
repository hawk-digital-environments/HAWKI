<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantAvatar;
use App\Models\User;
use App\Policies\Traits\AuthorizesCreationAgainstRelatedTrait;
use App\Policies\Traits\AuthorizeViewAnyForUserTrait;
use Illuminate\Auth\Access\HandlesAuthorization;

class AssistantAvatarPolicy
{
    use HandlesAuthorization;
    use AuthorizesCreationAgainstRelatedTrait;
    use AuthorizeViewAnyForUserTrait;
    
    public function view(User $user, AssistantAvatar $avatar): bool
    {
        return app(AssistantPolicy::class)->view($user, $avatar->assistant);
    }

    public function viewAssistant(User $user, AssistantAvatar $avatar): bool
    {
        return app(AssistantPolicy::class)->view($user, $avatar->assistant);
    }

    public function create(User $user): bool
    {
        return $this->authorizeCreationAgainstRelated('assistant', Assistant::class, 'update');
    }

    public function update(User $user, AssistantAvatar $avatar): bool
    {
        return app(AssistantPolicy::class)->update($user, $avatar->assistant);
    }

    public function delete(User $user, AssistantAvatar $avatar): bool
    {
        return app(AssistantPolicy::class)->update($user, $avatar->assistant);
    }
}
