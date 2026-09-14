<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class EmployeeTypeRoleSyncer
{
    public function sync(User $user): void
    {
        DB::transaction(function () use ($user) {
            // All RBAC mutations take the same lock, including login and bulk mapping changes.
            DB::table('roles')->where('slug', 'admin')->lockForUpdate()->first();
            $mapping = DB::table('employee_type_role_mappings')->where('employee_type', $user->employeetype)->first();
            DB::table('role_user')->where('user_id', $user->id)->where('source', 'employeetype')->delete();
            if ($mapping) {
                DB::table('role_user')->insert(['user_id' => $user->id, 'role_id' => $mapping->role_id, 'source' => 'employeetype', 'created_at' => now()]);
            }
        });
    }
}
