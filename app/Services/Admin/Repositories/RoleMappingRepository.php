<?php

declare(strict_types=1);

namespace App\Services\Admin\Repositories;

use App\Models\User;
use App\Services\Admin\RoleGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RoleMappingRepository extends ResourceRepository
{
    public const RESOURCE = 'mappings';

    public function __construct(private RoleGuard $guard)
    {
    }

    public function save(?int $id, array $values, User $actor): int
    {
        return $this->guard->mutate(function () use ($id, $values, $actor) {
            $data = Validator::make($values, [
                'employee_type' => ['required', 'string', 'max:255', Rule::unique('employee_type_role_mappings')->ignore($id)],
                'role_id' => 'required|integer|exists:roles,id',
            ])->validate();
            $this->guard->assertRolesGrantable([$data['role_id']], $actor);
            $existing = $id ? DB::table('employee_type_role_mappings')->where('id', $id)->first() : null;
            abort_if($id && !$existing, 404);

            if ($existing) {
                $this->guard->assertRolesGrantable([$existing->role_id], $actor);
            }

            $data['updated_at'] = now();

            if ($id) {
                DB::table('employee_type_role_mappings')->where('id', $id)->update($data);
            } else {
                $id = DB::table('employee_type_role_mappings')->insertGetId($data + ['created_at' => now()]);
            }

            $this->guard->resyncTypes(array_filter([$existing?->employee_type, $data['employee_type']]));
            $this->guard->assertActorRetainsAccess($actor);

            return (int) $id;
        });
    }

    public function delete(int $id, User $actor): void
    {
        $this->guard->mutate(function () use ($id, $actor): void {
            $mapping = DB::table('employee_type_role_mappings')->where('id', $id)->first();
            abort_unless(null !== $mapping, 404);
            $this->guard->assertRolesGrantable([$mapping->role_id], $actor);
            DB::table('employee_type_role_mappings')->where('id', $id)->delete();
            $this->guard->resyncTypes([$mapping->employee_type]);
            $this->guard->assertActorRetainsAccess($actor);
        });
    }

    protected function definition(): array
    {
        $fields = new \App\Services\Admin\ResourceFields();

        return ['table' => 'employee_type_role_mappings', 'columns' => ['employee_type', 'role_id'], 'fields' => [
            $fields->text('employee_type', true), $fields->reference('role_id', 'roles', 'roles'),
        ]];
    }
}
