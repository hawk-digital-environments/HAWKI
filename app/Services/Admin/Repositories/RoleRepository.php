<?php

declare(strict_types=1);

namespace App\Services\Admin\Repositories;

use App\Models\User;
use App\Services\Admin\Permission;
use App\Services\Admin\RoleGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoleRepository extends ResourceRepository
{
    public const RESOURCE = 'roles';
    protected const VERSION_RELATIONS = [['role_permissions', 'role_id', 'permission']];

    public function __construct(private RoleGuard $guard)
    {
    }

    public function save(?int $id, array $values, User $actor): int
    {
        return $this->guard->mutate(function () use ($id, $values, $actor) {
            $data = Validator::make($values, [
                'name' => 'required|string|max:255', 'description' => 'nullable|string|max:2000',
                'slug' => ['required', 'alpha_dash', 'max:80', Rule::unique('roles')->ignore($id)],
                'permissions' => 'present|array', 'permissions.*' => ['string', Rule::in(Permission::values())],
            ])->validate();
            $this->guard->assertGrantable($data['permissions'], $actor);
            $existing = $id ? DB::table('roles')->where('id', $id)->first() : null;
            abort_if($id && !$existing, 404);

            if ($existing) {
                $this->guard->assertRolesGrantable([$id], $actor);
            }

            if ($existing?->is_system && $existing->slug !== $data['slug']) {
                throw ValidationException::withMessages(['slug' => __('admin.errors.system_role')]);
            }

            $permissions = $data['permissions'];
            unset($data['permissions']);
            $data['updated_at'] = now();

            if ($id) {
                DB::table('roles')->where('id', $id)->update($data);
            } else {
                $id = DB::table('roles')->insertGetId($data + ['created_at' => now()]);
            }

            DB::table('role_permissions')->where('role_id', $id)->delete();

            foreach (array_unique($permissions) as $permission) {
                DB::table('role_permissions')->insert(['role_id' => $id, 'permission' => $permission]);
            }

            $this->guard->assertActorRetainsAccess($actor);

            return (int) $id;
        });
    }

    public function delete(int $id, User $actor): void
    {
        $this->guard->mutate(function () use ($id, $actor): void {
            $role = DB::table('roles')->where('id', $id)->first();
            abort_unless(null !== $role, 404);

            if ($role->is_system || DB::table('employee_type_role_mappings')->where('role_id', $id)->exists() || DB::table('role_user')->where('role_id', $id)->exists()) {
                throw ValidationException::withMessages(['role' => __('admin.errors.role_in_use')]);
            }

            $this->guard->assertRolesGrantable([$id], $actor);
            DB::table('roles')->where('id', $id)->delete();
            $this->guard->assertActorRetainsAccess($actor);
        });
    }

    protected function definition(): array
    {
        $fields = new \App\Services\Admin\ResourceFields();

        return ['table' => 'roles', 'columns' => ['name', 'slug', 'description', 'is_system'], 'fields' => [
            $fields->text('name', true), $fields->text('slug', true), $fields->field('description', 'textarea', 'nullable|string|max:2000'), $fields->multiple('permissions', Permission::values()),
        ]];
    }

    protected function rowAttributes(array $row): array
    {
        $result = [];
        $result['permissions'] = DB::table('role_permissions')->where('role_id', $row['id'])->pluck('permission')->all();

        return $result;
    }
}
