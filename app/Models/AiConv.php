<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiConv extends Model
{
    use HasFactory;

    protected $fillable = [
        'conv_name', 
        'slug',
        'user_id',
        'system_prompt'
    ];

    // Define the relationship with User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Define the relationship with AiConvMsg
    public function messages()
    {
        return $this->hasMany(AiConvMsg::class, 'conv_id');
    }
}
