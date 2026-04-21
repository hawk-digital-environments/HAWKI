<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Member extends Model
{
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
