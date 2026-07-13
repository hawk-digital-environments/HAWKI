<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Assistants\AssistantAvatar;
use App\Models\User;

class AssistantAvatarPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AssistantAvatar $avatar): bool
    {
        return (new AssistantPolicy())->view($user, $avatar->assistant);
    }

    public function viewAssistant(User $user, AssistantAvatar $avatar): bool
    {
        return (new AssistantPolicy())->view($user, $avatar->assistant);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, AssistantAvatar $avatar): bool
    {
        return (new AssistantPolicy())->update($user, $avatar->assistant);
    }

    public function delete(User $user, AssistantAvatar $avatar): bool
    {
        return (new AssistantPolicy())->update($user, $avatar->assistant);
    }
}
