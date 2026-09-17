<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class EmployeeTypeRoleSyncer
{
    public function __construct(
        private RoleAssignmentService $assignments,
        private RoleGuard $guard,
    ) {
    }

    public function sync(User $user): void
    {
        $this->guard->mutate(function () use ($user): void {
            $mapping = DB::table('employee_type_role_mappings')->where('employee_type', $user->employeetype)->first();
            $this->assignments->replace($user, $mapping ? [(int) $mapping->role_id] : [], 'employeetype');
        });
    }
}
