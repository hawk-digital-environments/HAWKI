<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Assistants\AssistantSettingValue;
use App\Models\User;
use App\Policies\Traits\AuthorizeViewAnyForUserTrait;
use Illuminate\Auth\Access\HandlesAuthorization;

class AssistantSettingValuePolicy
{
    use HandlesAuthorization;
    use AuthorizeViewAnyForUserTrait;

    public function view(User $user, AssistantSettingValue $value): bool
    {
        return $this->assistant()->view($user, $value->assistant);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, AssistantSettingValue $value): bool
    {
        return $this->assistant()->update($user, $value->assistant);
    }

    public function delete(User $user, AssistantSettingValue $value): bool
    {
        return $this->assistant()->update($user, $value->assistant);
    }

    private function assistant(): AssistantPolicy
    {
        return new AssistantPolicy();
    }
}
