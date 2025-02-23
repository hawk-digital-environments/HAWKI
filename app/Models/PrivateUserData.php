<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrivateUserData extends Model
{

    protected $fillable = [
        'user_id',
        
        'KCIV',
        'KCTAG',
        'keychain'
    ];

}

