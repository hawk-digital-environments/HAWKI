<?php

namespace App\Models;

use App\Models\Scopes\RoomMemberAccessScope;
use App\Policies\RoomMemberPolicy;
use App\Services\System\Database\Eloquent\ContextualScopes\HasContextualScopesTrait;
use App\Services\System\Database\Eloquent\ContextualScopes\ScopeRegistrar;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UsePolicy(RoomMemberPolicy::class)]
class Member extends Model
{
    use HasContextualScopesTrait;

    const ROLE_ADMIN = 'admin';
    const ROLE_EDITOR = 'editor';
    const ROLE_VIEWER = 'viewer';
    const ROLE_ASSISTANT = 'assistant';

    protected $fillable = [
        'room_id',
        'user_id',
        'role',
        'last_read',
        'isRemoved'
    ];

    protected static function registerScopes(ScopeRegistrar $registrar): void
    {
        $registrar->addScope('access', new RoomMemberAccessScope());
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param $role
     * @return bool
     */
    public function hasRole($role): bool
    {
        return $this->role === $role;
    }

    /**
     * @param string $role
     * @return void
     */
    public function updateRole(string $role): void
    {
        $this->update(['role' => $role]);
    }

    /**
     * @return void
     */
    public function updateLastRead(): void
    {
        $this->update(['last_read' => Carbon::now()]);
    }

    /**
     * @return void
     */
    public function revokeMembership(): void
    {
        $this->update(['isRemoved' => 1]);
    }

    /**
     * @return void
     */
    public function recreateMembership(): void
    {
        $this->update(['isRemoved' => 0]);
    }
}
