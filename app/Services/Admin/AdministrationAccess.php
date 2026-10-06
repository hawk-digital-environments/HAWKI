<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\User;
use App\Services\Users\UserCondition;

final class AdministrationAccess
{
    public function authorize(?User $user): void
    {
        abort_unless(UserCondition::isAdmin($user ? User::withoutGlobalScopes()->find($user->getKey()) : null), 403);
    }
}
