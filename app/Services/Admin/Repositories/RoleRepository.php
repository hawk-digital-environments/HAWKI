<?php

declare(strict_types=1);

namespace App\Services\Admin\Repositories;

use App\Models\Role;
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
    protected const VERSION_RELATIONS = [['role_has_permissions', 'role_id', 'permission_id']];

    public function __construct(private RoleGuard $guard)
    {
    }

    public function save(?int $id, array $values, User $actor): int
    {
        return $this->guard->mutate(function () use ($id, $values, $actor) {
            $data = Validator::make($values, [
                'name' => 'required|string|max:255', 'description' => 'nullable|string|max:2000',
                'slug' => ['required', 'alpha_dash', 'max:80', Rule::unique('roles', 'name')->ignore($id)],
                'permissions' => 'present|array', 'permissions.*' => ['string', Rule::in(Permission::values())],
            ])->validate();
            $this->guard->assertGrantable($data['permissions'], $actor);
            $existing = $id ? Role::query()->find($id) : null;
            abort_if($id && !$existing, 404);

            if ($existing) {
                $this->guard->assertRolesGrantable([$id], $actor);
            }

            if ($existing?->is_system && $existing->name !== $data['slug']) {
                throw ValidationException::withMessages(['slug' => __('admin.errors.system_role')]);
            }

            $permissions = $data['permissions'];
            $role = $existing ?? new Role();
            $role->fill(['name' => $data['slug'], 'display_name' => $data['name'], 'description' => $data['description'] ?? null])->save();
            $id = $role->getKey();
            $role->syncPermissions(array_values(array_unique($permissions)));

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
            Role::query()->findOrFail($id)->delete();
            $this->guard->assertActorRetainsAccess($actor);
        });
    }

    protected function readContent(User $user, array $filters): array
    {
        $content = parent::readContent($user, $filters);
        $grants = app(\App\Services\Admin\PermissionService::class)->permissionsOf($user);
        $content['permission_catalog'] = array_map(static fn (string $name) => [
            'name' => $name,
            'group' => str_starts_with($name, 'tools.') || str_starts_with($name, 'ai.capabilities.') ? 'tools' : 'administration',
            'title_label' => "admin.permissions.$name.title",
            'description_label' => "admin.permissions.$name.description",
            'grantable' => in_array($name, $grants, true),
        ], Permission::values());
        return $content;
    }

    protected function definition(): array
    {
        $fields = new \App\Services\Admin\ResourceFields();

        return ['table' => 'roles', 'column_map' => ['name' => 'display_name', 'slug' => 'name'], 'columns' => ['name', 'slug', 'description', 'is_system'], 'fields' => [
            $fields->text('name', true), $fields->text('slug', true), $fields->field('description', 'textarea', 'nullable|string|max:2000'), $fields->multiple('permissions', Permission::values()),
        ]];
    }

    protected function rowAttributes(array $row): array
    {
        $result = [];
        $result['permissions'] = app(\App\Services\Admin\PermissionService::class)->permissionsForRoles([(int) $row['id']]);

        return $result;
    }
}
