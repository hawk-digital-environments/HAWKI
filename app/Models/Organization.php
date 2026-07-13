<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Assistants\Assistant;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property \Illuminate\Database\Eloquent\Collection<int, Assistant> $assistants
 * @property int                                                      $id
 * @property string                                                   $name
 * @property \Illuminate\Database\Eloquent\Collection<int, User>      $users
 */
#[Table('organizations')]
class Organization extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
    ];

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function adminUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_user')
            ->withPivot('role')
            ->withTimestamps()
            ->wherePivot('role', 'admin');
    }

    /**
     * @return HasMany<Assistant, $this>
     */
    public function assistants(): HasMany
    {
        return $this->hasMany(Assistant::class);
    }
}
