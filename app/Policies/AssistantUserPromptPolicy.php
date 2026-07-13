<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Assistants\AssistantUserPrompt;
use App\Models\User;
use App\Policies\Traits\AuthorizeViewAnyForUserTrait;
use Illuminate\Auth\Access\HandlesAuthorization;

class AssistantUserPromptPolicy
{
    use HandlesAuthorization;
    use AuthorizeViewAnyForUserTrait;

    public function view(User $user, AssistantUserPrompt $prompt): bool
    {
        return $this->assistant()->view($user, $prompt->assistant);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, AssistantUserPrompt $prompt): bool
    {
        return $this->assistant()->update($user, $prompt->assistant);
    }

    public function delete(User $user, AssistantUserPrompt $prompt): bool
    {
        return $this->assistant()->update($user, $prompt->assistant);
    }

    private function assistant(): AssistantPolicy
    {
        return new AssistantPolicy();
    }
}
