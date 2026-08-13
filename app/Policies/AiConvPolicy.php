<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AiConv;
use App\Models\User;
use App\Policies\Traits\AuthorizeViewAnyForUserTrait;
use Illuminate\Auth\Access\HandlesAuthorization;

class AiConvPolicy
{
    use HandlesAuthorization;
    use AuthorizeViewAnyForUserTrait;

    public function view(User $user, AiConv $conv): bool
    {
        return $conv->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, AiConv $conv): bool
    {
        return $conv->user_id === $user->id;
    }

    public function delete(User $user, AiConv $conv): bool
    {
        return $conv->user_id === $user->id;
    }

    public function viewMessages(User $user, AiConv $conv): bool
    {
        return $conv->user_id === $user->id;
    }
}
