<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

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
        'isRemoved',
        'isMember'
    ];

    // public function room()
    // {
    //     return $this->belongsTo(Room::class);
    // }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function hasRole($role)
    {
        return $this->role === $role;
    }
    
    public function isAdmin()
    {
        return $this->role === self::ROLE_ADMIN;
    }
    
    public function isEditor()
    {
        return $this->role === self::ROLE_EDITOR;
    }
    
    public function isViewer()
    {
        return $this->role === self::ROLE_VIEWER;
    }
    
    public function canSendMessages()
    {
        // Admins and Editors can send messages, Viewers cannot
        return $this->isAdmin() || $this->isEditor();
    }
    
    public function canModifyRoom()
    {
        // Only Admins can modify room settings
        return $this->isAdmin();
    }
    
    public function canAddMembers()
    {
        // Only Admins can add members
        return $this->isAdmin();
    }
    
    public function canRemoveMembers()
    {
        // Only Admins can remove members
        return $this->isAdmin();
    }
    
    public function canDeleteRoom()
    {
        // Only Admins can delete room
        return $this->isAdmin();
    }
    
    public function canViewAllMembers()
    {
        // Admins and Editors can view all members, Viewers cannot
        return $this->isAdmin() || $this->isEditor();
    }

    public function updateRole($role){
        $this->update(['role' => $role]);
    }

    public function updateLastRead(){
        $this->update(['last_read' => Carbon::now()]);
    }

    public function revokeMembership(){
        $this->update(['isRemoved'=> 1]);
    }

    public function recreateMembership($role = null){
        // Re-invite user: reset both isMember and isRemoved
        $updates = [
            'isMember' => true,
            'isRemoved' => false
        ];
        
        // Optionally update role if provided
        if ($role !== null) {
            $updates['role'] = $role;
        }
        
        $this->update($updates);
    }
}