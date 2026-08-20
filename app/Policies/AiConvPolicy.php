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

    public function view(User|null $user, AiConv $conv): bool
    {
        return $this->isSameUser($user, $conv->user_id);
    }

    public function create(User|null $user): bool
    {
        return $this->isUser($user);
    }

    public function update(User|null $user, AiConv $conv): bool
    {
        return $this->isSameUser($user, $conv->user_id);
    }

    public function delete(User|null $user, AiConv $conv): bool
    {
        return $this->isSameUser($user, $conv->user_id);
    }

    public function viewMessages(User|null $user, AiConv $conv): bool
    {
        return $this->isSameUser($user, $conv->user_id);
    }
}
