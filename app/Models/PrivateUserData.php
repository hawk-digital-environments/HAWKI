<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrivateUserData extends Model
{

    protected $fillable = [
        'user_id',
        
        'KCIV',
        'KCTAG',
        'keychain'
    ];
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
}
