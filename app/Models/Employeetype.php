<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employeetype extends Model
{
    use HasFactory;

    protected $fillable = [
        'raw_value',
        'auth_method',
        'display_name',
        'is_active',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the role assignments for this employeetype
     */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(EmployeetypeRole::class);
    }

    /**
     * Alias for roleAssignments - for consistency with screen code
     */
    public function employeetypeRoles(): HasMany
    {
        return $this->roleAssignments();
    }

    /**
     * Get the primary role assignment for this employeetype
     */
    public function primaryRoleAssignment()
    {
        return $this->roleAssignments()->where('is_primary', true)->first();
    }

    /**
     * Get the primary role slug for this employeetype
     */
    public function getPrimaryRoleSlugAttribute(): ?string
    {
        $primaryRole = $this->primaryRoleAssignment();

        return $primaryRole?->role?->slug;
    }

    /**
     * Scope to get active employeetypes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get employeetypes by auth method
     */
    public function scopeByAuthMethod($query, string $authMethod)
    {
        return $query->where('auth_method', $authMethod);
    }

    /**
     * Find or create an employeetype for given raw value and auth method
     */
    public static function findOrCreateForAuth(string $rawValue, string $authMethod): self
    {
        return self::firstOrCreate(
            [
                'raw_value' => $rawValue,
                'auth_method' => $authMethod,
            ],
            [
                'display_name' => "Auto-detected: {$rawValue} ({$authMethod})",
                'is_active' => true,
                'description' => "Automatically created from {$authMethod} authentication",
            ]
        );
    }

    /**
     * Get the mapped role slug for this employeetype, or 'guest' as fallback
     */
    public function getMappedRoleSlug(): string
    {
        $primaryRole = $this->primaryRoleAssignment();

        if ($primaryRole && $primaryRole->role) {
            return $primaryRole->role->slug;
        }

        // Fallback to guest if no mapping exists
        return 'guest';
    }
}
