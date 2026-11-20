<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantSettingValue;
use App\Models\User;
use App\Policies\Traits\AuthorizesCreationAgainstRelatedTrait;
use App\Policies\Traits\AuthorizeViewAnyForUserTrait;
use Illuminate\Auth\Access\HandlesAuthorization;

class AssistantSettingValuePolicy
{
    use HandlesAuthorization;
    use AuthorizeViewAnyForUserTrait;
    use AuthorizesCreationAgainstRelatedTrait;

    public function view(User $user, AssistantSettingValue $value): bool
    {
        return $this->assistant()->view($user, $value->assistant);
    }

    public function create(User $user): bool
    {
        return $this->authorizeCreationAgainstRelated('assistant', Assistant::class, 'update');
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
        return app(AssistantPolicy::class);
    }
}
