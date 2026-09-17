<?php

declare(strict_types=1);

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $guard_name = 'web';
    protected $attributes = ['guard_name' => 'web'];
    protected $casts = ['is_system' => 'boolean'];
}
