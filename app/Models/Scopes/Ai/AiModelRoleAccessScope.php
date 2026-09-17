<?php

declare(strict_types=1);

namespace App\Models\Scopes\Ai;

use App\Models\Scopes\Traits\UserAwareScopeTrait;
use App\Models\User;
use App\Services\Ai\Models\Access\ModelAuthorization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class AiModelRoleAccessScope implements Scope
{
    use UserAwareScopeTrait;

    public function apply(Builder $builder, Model $model): void
    {
        $this->withOnNoUser(static fn () => app(ModelAuthorization::class)->constrain($builder, null));
        $this->runIfUserPresent(
            static fn (User $user) => app(ModelAuthorization::class)->constrain($builder, $user),
            callbackNoUserInCli: true
        );
    }
}
