<?php
declare(strict_types=1);


namespace App\Models\Scopes;


use App\Models\Scopes\Traits\UserAwareScopeTrait;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class KnownUsersAccessScope implements Scope
{
    use UserAwareScopeTrait;

    /**
     * @inheritDoc
     */
    public function apply(EloquentBuilder $builder, Model $model): void
    {
        try {
            $this->runIfUserPresent(
                function (User $user) use ($builder) {
                    $builder
                        // Allow all users to see records of groupchats they are a member of
                        ->whereHas('members', function (EloquentBuilder $query) use ($user) {
                            $query->where('user_id', $user->id);
                        })
                        // Allow users to see their own record, even if they are not a member of any team
                        ->orWhere('users.id', $user->id)
                        // Allow all users to see the HAWKI user record
                        ->orWhere('users.id', 1);
                },
                callbackNoUserInCli: true
            );
        } catch (\Throwable $e) {
            dbge($e);
        }
    }
}
