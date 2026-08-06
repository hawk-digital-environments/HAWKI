<?php

declare(strict_types=1);

namespace Tests\Unit\Models\Scopes\Traits\UserAwareScopeTraitTestFixtures;

use App\Models\Scopes\Traits\UserAwareScopeTrait;
use App\Models\User;

class UserAwareScopeHost
{
    use UserAwareScopeTrait;

    public function exposeCurrentUser(): User|null
    {
        return $this->getCurrentUser();
    }
}
