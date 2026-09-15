<?php

declare(strict_types=1);

namespace App\Services\Admin\Repositories;

use App\Models\User;
use App\Services\Admin\EmployeeTypeRoleSyncer;
use App\Services\Admin\Permission;
use App\Services\Admin\PermissionService;
use App\Services\Admin\RoleGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserRepository extends ResourceRepository
{
    public const RESOURCE = 'users';
    protected const VERSION_RELATIONS = [['role_user', 'user_id', 'role_id']];

    public function __construct(
        private RoleGuard $guard,
        private PermissionService $permissions,
        private EmployeeTypeRoleSyncer $syncer,
    ) {
    }

    public function save(?int $id, array $values, User $actor): int
    {
        return $this->guard->mutate(function () use ($id, $values, $actor) {
            if (null === $id) {
                $this->permissions->authorize($actor, Permission::USERS_MANAGE);
                $data = Validator::make($values, [
                    'name' => 'required|string|max:255',
                    'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')],
                    'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
                    'employeetype' => 'required|string|max:255',
                    'password' => 'required|string|min:12|max:255|confirmed',
                    'password_confirmation' => 'required|string|max:255',
                    'admin_disabled' => 'sometimes|boolean',
                    'roles' => 'sometimes|array', 'roles.*' => 'integer|distinct|exists:roles,id',
                ])->validate();
                $roles = $data['roles'] ?? [];
                $this->guard->assertEmployeeTypeGrantable($data['employeetype'], $actor);

                if (\array_key_exists('roles', $data)) {
                    $this->permissions->authorize($actor, Permission::ROLES_MANAGE);
                    $this->guard->assertRolesGrantable($roles, $actor);
                }

                $password = $data['password'];
                unset($data['password'], $data['password_confirmation'], $data['roles']);
                $user = new User();
                $user->forceFill($data + [
                    'local_password' => Hash::make($password),
                    'publicKey' => '',
                    'avatar_id' => null,
                    'isRemoved' => false,
                    'registration_fingerprint' => null,
                ])->save();
                $id = (int) $user->id;

                foreach ($roles as $role) {
                    DB::table('role_user')->insert(['role_id' => $role, 'user_id' => $id, 'source' => 'manual', 'created_at' => now()]);
                }

                return $id;
            }

            $user = User::withoutGlobalScopes()->findOrFail($id);
            abort_if((int) $user->id === 1, 403);
            $data = Validator::make($values, [
                'name' => 'sometimes|required|string|max:255',
                'username' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($id)],
                'email' => ['sometimes', 'required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($id)],
                'employeetype' => 'sometimes|required|string|max:255',
                'admin_disabled' => 'sometimes|boolean',
                'roles' => 'sometimes|array', 'roles.*' => 'integer|distinct|exists:roles,id',
                'password' => 'sometimes|nullable|string|min:12|max:255|confirmed',
                'password_confirmation' => 'required_with:password|nullable|string|max:255',
            ])->validate();

            if (isset($data['username']) && $data['username'] !== $user->username) {
                throw ValidationException::withMessages(['username' => __('admin.errors.immutable')]);
            }

            $identity = array_intersect_key($data, array_flip(['name', 'email', 'employeetype']));

            if (array_intersect_key($data, array_flip(['name', 'username', 'email', 'employeetype', 'password', 'password_confirmation']))) {
                $this->permissions->authorize($actor, Permission::USERS_MANAGE);
                $this->guard->assertGrantable($this->permissions->assignedPermissionsOf($user), $actor);
            }

            if ($identity && !filled($user->local_password)) {
                throw ValidationException::withMessages(['name' => __('admin.errors.external_identity')]);
            }

            if (filled($data['password'] ?? null) && !filled($user->local_password)) {
                throw ValidationException::withMessages(['password' => __('admin.errors.external_identity')]);
            }

            if ($identity) {
                if (isset($identity['employeetype'])) {
                    $this->guard->assertEmployeeTypeGrantable($identity['employeetype'], $actor);
                }

                $user->forceFill($identity)->save();
                $this->syncer->sync($user);
            }

            if (filled($data['password'] ?? null)) {
                $user->forceFill(['local_password' => Hash::make($data['password'])])->save();
            }

            if (\array_key_exists('admin_disabled', $data)) {
                $this->permissions->authorize($actor, Permission::USERS_MANAGE);

                if ($user->id === $actor->id && $data['admin_disabled']) {
                    throw ValidationException::withMessages(['admin_disabled' => __('admin.errors.self_disable')]);
                }

                $this->guard->assertGrantable($this->permissions->assignedPermissionsOf($user), $actor);
                $user->forceFill(['admin_disabled' => $data['admin_disabled']])->save();

                if ($data['admin_disabled']) {
                    $user->tokens()->delete();
                }
            }

            if (\array_key_exists('roles', $data)) {
                $this->permissions->authorize($actor, Permission::ROLES_MANAGE);
                $existing = DB::table('role_user')->where('user_id', $id)->where('source', 'manual')->pluck('role_id')->all();
                $this->guard->assertRolesGrantable(array_unique(array_merge($existing, $data['roles'])), $actor);
                DB::table('role_user')->where('user_id', $id)->where('source', 'manual')->delete();

                foreach ($data['roles'] as $role) {
                    DB::table('role_user')->insert(['role_id' => $role, 'user_id' => $id, 'source' => 'manual', 'created_at' => now()]);
                }
            }

            $this->guard->assertActorRetainsAccess($actor);

            return (int) $id;
        });
    }

    public function revokeTokens(User $actor, ?string $id): array
    {
        $this->permissions->authorize($actor, Permission::USERS_MANAGE);
        $user = User::withoutGlobalScopes()->findOrFail($id);
        abort_if([] !== array_diff($this->permissions->assignedPermissionsOf($user), $this->permissions->permissionsOf($actor)), 403);

        return ['revoked' => $user->tokens()->delete()];
    }

    public function tokens(User $actor, ?string $id): array
    {
        $this->permissions->authorize($actor, Permission::USERS_MANAGE);

        return ['tokens' => User::withoutGlobalScopes()->findOrFail($id)->tokens()->get(['id', 'name', 'created_at', 'last_used_at', 'expires_at'])->toArray()];
    }

    protected function fields(User $user): array
    {
        return array_values(array_filter(parent::fields($user), fn ($field) => $this->permissions->has($user, 'roles' === $field['key'] ? Permission::ROLES_MANAGE : Permission::USERS_MANAGE)));
    }

    protected function canCreate(User $user): bool
    {
        return $this->permissions->has($user, Permission::USERS_MANAGE);
    }

    protected function definition(): array
    {
        $fields = new \App\Services\Admin\ResourceFields();

        return ['table' => 'users', 'delete' => false, 'columns' => ['name', 'username', 'email', 'employeetype', 'admin_disabled', 'last_login_at'], 'fields' => [
            $fields->text('name', true), $fields->field('username', 'text', 'required|string|max:255') + ['immutable' => true],
            $fields->field('email', 'text', 'required|email:rfc|max:255'), $fields->text('employeetype', true),
            $fields->field('password', 'secret', 'nullable|string|min:12|max:255'),
            $fields->field('password_confirmation', 'secret', 'nullable|string|max:255'),
            $fields->boolean('admin_disabled'), $fields->multiple('roles', 'roles'),
        ]];
    }

    protected function rowAttributes(array $row): array
    {
        $result = [];

        if ((int) $row['id'] === 1) {
            $result['is_system'] = true;
        }

        $roles = DB::table('role_user')->where('user_id', $row['id'])->get();
        $result['roles'] = $roles->where('source', 'manual')->pluck('role_id')->map(static fn ($id) => (int) $id)->all();
        $result['mapped_roles'] = $roles->where('source', 'employeetype')->pluck('role_id')->map(static fn ($id) => (int) $id)->all();
        $result['local_account'] = filled($row['local_password'] ?? null);

        return $result;
    }
}
