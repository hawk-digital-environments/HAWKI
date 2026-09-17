<?php

declare(strict_types=1);

namespace App\Services\Ai\Models\Access;

use App\Models\Ai\AiModel;
use App\Models\User;
use App\Services\Admin\PermissionService;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Models\Access\Exceptions\ModelAccessException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ModelAuthorization
{
    public function constrain(Builder $query, ?User $actor): Builder
    {
        if (null === $actor && app()->runningInConsole()) {
            return $query;
        }

        $roleIds = $this->roleIds($actor);

        return $query->where(static function (Builder $models) use ($roleIds): void {
            $models->whereDoesntHave('allowedRoles');

            if ([] !== $roleIds) {
                $models->orWhereHas('allowedRoles', static fn (Builder $roles) => $roles->whereIn('roles.id', $roleIds));
            }
        });
    }

    public function isAllowed(AiModel $model, ?User $actor): bool
    {
        $allowedRoleIds = DB::table('ai_model_roles')
            ->where('ai_model_id', $model->getKey())
            ->pluck('role_id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        if ([] === $allowedRoleIds) {
            return true;
        }

        return [] !== array_intersect($allowedRoleIds, $this->roleIds($actor));
    }

    public function authorize(AgentRequestContext $context): void
    {
        $actor = null === $context->actorId
            ? null
            : User::withoutGlobalScopes()->find($context->actorId);
        $model = AiModel::withoutGlobalScopes()->find($context->model->getKey());

        if (!$model || !$this->isAllowed($model, $actor)) {
            throw ModelAccessException::denied();
        }
    }

    private function roleIds(?User $actor): array
    {
        $permissions = app(PermissionService::class);

        if (!$actor || !$permissions->isEligible($actor)) {
            return [];
        }

        return $permissions->roleIds($actor);
    }
}
