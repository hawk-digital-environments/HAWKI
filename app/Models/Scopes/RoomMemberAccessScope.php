<?php
declare(strict_types=1);


namespace App\Models\Scopes;


use App\Models\Scopes\Traits\UserAwareScopeTrait;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class RoomMemberAccessScope implements Scope
{
    use UserAwareScopeTrait;

    /**
     * @inheritDoc
     */
    public function apply(Builder $builder, Model $model): void
    {
        $this->runIfUserPresent(
            function (User $user) use ($builder) {
                $roomIds = $user->members()->withoutGlobalScopes()->pluck('room_id');
                $builder->whereIn('room_id', $roomIds);
            },
            callbackNoUserInCli: true
        );
    }
}
