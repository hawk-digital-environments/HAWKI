<?php
declare(strict_types=1);


namespace App\Models\Scopes\Generic;


use App\Models\Scopes\Traits\UserAwareScopeTrait;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class BelongsToUserScope implements Scope
{
    use UserAwareScopeTrait;

    public function __construct(
        private readonly string $userIdColumn = 'user_id'
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function apply(Builder $builder, Model $model): void
    {
        $this->runIfUserPresent(
            function (User $user) use ($builder) {
                $builder->where($this->userIdColumn, $user->id);
            },
            callbackNoUserInCli: true);
    }
}
