<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeetypeRole extends Model
{
    use HasFactory;

    protected $fillable = [
        'employeetype_id',
        'role_id',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    /**
     * Get the employeetype that owns this role assignment
     */
    public function employeetype(): BelongsTo
    {
        return $this->belongsTo(Employeetype::class);
    }

    /**
     * Get the Orchid role for this assignment
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(\Orchid\Platform\Models\Role::class, 'role_id', 'id');
    }

    /**
     * Get the Orchid role for this assignment (legacy attribute access)
     */
    public function getOrchidRoleAttribute()
    {
        return $this->role;
    }

    /**
     * Scope to get primary role assignments
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope to get assignments by role ID
     */
    public function scopeByRoleId($query, int $roleId)
    {
        return $query->where('role_id', $roleId);
    }

    /**
     * Create or update role assignment for an employeetype
     */
    public static function assignRole(int $employeetypeId, int $roleId, bool $isPrimary = true): self
    {
        // If setting as primary, remove primary flag from other assignments
        if ($isPrimary) {
            self::where('employeetype_id', $employeetypeId)
                ->update(['is_primary' => false]);
        }

        return self::updateOrCreate(
            [
                'employeetype_id' => $employeetypeId,
                'role_id' => $roleId,
            ],
            [
                'is_primary' => $isPrimary,
            ]
        );
    }
}
