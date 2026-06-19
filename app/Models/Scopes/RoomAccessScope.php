<?php

namespace App\Models\Scopes;

use App\Models\Scopes\Traits\UserAwareScopeTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class RoomAccessScope implements Scope
{
    use UserAwareScopeTrait;

    public function apply(Builder $builder, Model $model): void
    {
        $this->runIfUserPresent(
            function ($user) use ($builder) {
                // Only allow access to rooms that the user is a member of
                $builder->whereHas('members', function (Builder $query) use ($user) {
                    $query->where('user_id', $user->id);
                });
            },
            callbackNoUserInCli: true
        );
    }
}
