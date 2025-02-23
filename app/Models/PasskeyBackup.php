<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PasskeyBackup extends Model
{
    use HasFactory;

    protected $fillable = [
        'username', 
        'ciphertext',
        'iv',
        'tag',
    ];
}
