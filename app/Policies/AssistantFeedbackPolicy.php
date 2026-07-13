<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Assistants\AssistantFeedback;
use App\Models\User;
use App\Policies\Traits\AuthorizeViewAnyForUserTrait;
use Illuminate\Auth\Access\HandlesAuthorization;

class AssistantFeedbackPolicy
{
    use HandlesAuthorization;
    use AuthorizeViewAnyForUserTrait;

    public function view(User $user, AssistantFeedback $feedback): bool
    {
        return $this->assistant()->view($user, $feedback->assistant);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, AssistantFeedback $feedback): bool
    {
        return $this->assistant()->update($user, $feedback->assistant);
    }

    public function delete(User $user, AssistantFeedback $feedback): bool
    {
        return $this->assistant()->update($user, $feedback->assistant);
    }

    private function assistant(): AssistantPolicy
    {
        return new AssistantPolicy();
    }
}
